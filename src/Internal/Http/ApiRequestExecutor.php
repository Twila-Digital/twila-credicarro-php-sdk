<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Twila\Credicarro\Config\ResilienceOptions;
use Twila\Credicarro\Errors\ExceptionFactory;
use Twila\Credicarro\Internal\Auth\AccessTokenProvider;
use Twila\Credicarro\Internal\Idempotency\IdempotencyClassifier;
use Twila\Credicarro\Internal\Resilience\ResiliencePipeline;

/**
 * @internal
 */
final class ApiRequestExecutor
{
    /** @var string */
    private $baseUrl;

    /** @var AccessTokenProvider */
    private $tokenProvider;

    /** @var ResilienceOptions */
    private $resilienceOptions;

    /** @var ClientInterface */
    private $http;

    /** @var ResiliencePipeline */
    private $resilience;

    public function __construct(
        string $baseUrl,
        AccessTokenProvider $tokenProvider,
        ResilienceOptions $resilienceOptions,
        ?ClientInterface $httpClient = null
    ) {
        $this->baseUrl = $baseUrl;
        $this->tokenProvider = $tokenProvider;
        $this->resilienceOptions = $resilienceOptions;
        $this->http = $httpClient ?? new Client(['http_errors' => false]);
        $this->resilience = new ResiliencePipeline($resilienceOptions);
    }

    public function get(string $path): ApiResponse
    {
        return $this->send('GET', $path, null, null, $this->resilienceOptions->attemptTimeoutMs, null);
    }

    /**
     * @param mixed $body
     * @param string|null $idempotencyKey chave informada pelo chamador; vence a chave automática
     * @param int|null $uploadAttemptTimeoutMs quando informado (envio de documento), é o timeout de cada tentativa
     *   e o orçamento total passa a ser esse valor × `maxRetryAttempts` (padrão 180 s), no lugar de `totalTimeoutMs`
     */
    public function post(
        string $path,
        $body,
        ?string $idempotencyKey = null,
        ?int $uploadAttemptTimeoutMs = null
    ): ApiResponse {
        $attemptTimeoutMs = $uploadAttemptTimeoutMs ?? $this->resilienceOptions->attemptTimeoutMs;
        $budgetMs = $uploadAttemptTimeoutMs === null
            ? null
            : $uploadAttemptTimeoutMs * max(1, $this->resilienceOptions->maxRetryAttempts);

        return $this->send('POST', $path, $body, $idempotencyKey, $attemptTimeoutMs, $budgetMs);
    }

    /**
     * @param mixed $body
     */
    public function put(string $path, $body): ApiResponse
    {
        return $this->send('PUT', $path, $body, null, $this->resilienceOptions->attemptTimeoutMs, null);
    }

    public function delete(string $path): ApiResponse
    {
        return $this->send('DELETE', $path, null, null, $this->resilienceOptions->attemptTimeoutMs, null);
    }

    public static function ensureSuccess(ApiResponse $response): void
    {
        if (!$response->isSuccess()) {
            throw ExceptionFactory::fromResponse($response);
        }
    }

    /**
     * @param mixed $body
     */
    private function send(
        string $method,
        string $path,
        $body,
        ?string $explicitKey,
        int $attemptTimeoutMs,
        ?int $budgetMs
    ): ApiResponse {
        // A chave é definida UMA vez, fora do laço de tentativas: todas as tentativas usam a mesma.
        $idempotencyKey = $explicitKey;
        if (
            $idempotencyKey === null
            && !$this->resilienceOptions->disableAutomaticIdempotencyKey
            && IdempotencyClassifier::requiresIdempotencyKey($method, $path)
        ) {
            $idempotencyKey = UuidGenerator::v4();
        }

        $retrySafe = IdempotencyClassifier::isRetrySafe($method, $path, $idempotencyKey !== null);

        // O corpo é serializado UMA vez (um documento de 10 MiB vira ~13 MiB de JSON): as tentativas e o replay do
        // 401 reutilizam a mesma string.
        $encodedBody = $body === null ? null : json_encode($body);
        if ($encodedBody === false) {
            throw new \InvalidArgumentException('Não foi possível serializar o corpo da requisição em JSON.');
        }

        return $this->resilience->execute(
            $retrySafe,
            function () use ($method, $path, $encodedBody, $idempotencyKey, $attemptTimeoutMs): ApiResponse {
                return $this->sendWithAuth($method, $path, $encodedBody, $idempotencyKey, $attemptTimeoutMs);
            },
            $budgetMs
        );
    }

    private function sendWithAuth(
        string $method,
        string $path,
        ?string $encodedBody,
        ?string $idempotencyKey,
        int $attemptTimeoutMs
    ): ApiResponse {
        $token = $this->tokenProvider->getToken();
        $response = $this->sendOnce($method, $path, $encodedBody, $idempotencyKey, $attemptTimeoutMs, $token);

        if ($response->statusCode !== 401) {
            return $response;
        }

        // 401: o token pode ter sido revogado antes de `expira_em`. Renova e repete UMA vez (a requisição
        // não foi processada, então repetir é seguro mesmo sem Idempotency-Key).
        $this->tokenProvider->invalidate($token);
        $newToken = $this->tokenProvider->getToken();
        $retried = $this->sendOnce($method, $path, $encodedBody, $idempotencyKey, $attemptTimeoutMs, $newToken);

        if ($retried->statusCode !== 401) {
            return $retried;
        }

        throw ExceptionFactory::fromResponse($retried);
    }

    private function sendOnce(
        string $method,
        string $path,
        ?string $encodedBody,
        ?string $idempotencyKey,
        int $attemptTimeoutMs,
        #[\SensitiveParameter]
        string $token
    ): ApiResponse {
        // baseUrl sempre termina com "/" (garantido em ClientOptionsResolver) e path nunca começa com "/":
        // concatenação direta evita a resolução de URI relativa (RFC 3986 §5.3) descartar o segmento "integration".
        $url = $this->baseUrl . $path;

        $headers = ['Authorization' => 'Bearer ' . $token];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $requestOptions = [
            'headers' => $headers,
            'timeout' => $attemptTimeoutMs / 1000,
            'connect_timeout' => $attemptTimeoutMs / 1000,
            'http_errors' => false,
            // Um 3xx nunca é seguido (levaria o Authorization para outro host): vira CredicarroApiException.
            'allow_redirects' => false,
        ];

        if ($encodedBody !== null) {
            $requestOptions['body'] = $encodedBody;
            $requestOptions['headers']['Content-Type'] = 'application/json';
        }

        $raw = $this->http->request($method, $url, $requestOptions);

        return ResponseConverter::toApiResponse($raw);
    }
}
