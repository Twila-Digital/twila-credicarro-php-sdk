<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Twila\Credicarro\Errors\CredicarroAuthenticationException;
use Twila\Credicarro\Errors\CredicarroException;
use Twila\Credicarro\Errors\ExceptionFactory;
use Twila\Credicarro\Errors\ProblemDetails;
use Twila\Credicarro\Internal\Http\ApiResponse;
use Twila\Credicarro\Internal\Http\ResponseConverter;
use Twila\Credicarro\Internal\Mapping\Wire;
use Twila\Credicarro\Internal\Resilience\ResiliencePipeline;

/**
 * @internal
 */
final class TokenApiClient
{
    private const SAFETY_MARGIN_SECONDS = 60;

    /** @var string */
    private $baseUrl;

    /** @var int */
    private $attemptTimeoutMs;

    /** @var ClientInterface */
    private $http;

    /** @var ResiliencePipeline */
    private $resilience;

    public function __construct(
        string $baseUrl,
        int $attemptTimeoutMs,
        ResiliencePipeline $resilience,
        ?ClientInterface $httpClient = null
    ) {
        $this->baseUrl = $baseUrl;
        $this->attemptTimeoutMs = $attemptTimeoutMs;
        $this->resilience = $resilience;
        $this->http = $httpClient ?? new Client(['http_errors' => false]);
    }

    /**
     * As credenciais viajam num objeto mascarado (nunca como string) para não aparecerem em stack traces.
     */
    public function generate(Credentials $credentials): AccessToken
    {
        $response = $this->resilience->execute(true, function () use ($credentials): ApiResponse {
            $raw = $this->http->request('POST', $this->baseUrl . 'v1/authentication/accesstoken', [
                'json' => $credentials->toTokenRequestBody(),
                'timeout' => $this->attemptTimeoutMs / 1000,
                'connect_timeout' => $this->attemptTimeoutMs / 1000,
                'http_errors' => false,
                'allow_redirects' => false,
            ]);

            return ResponseConverter::toApiResponse($raw);
        });

        if (!$response->isSuccess()) {
            throw $this->toAuthenticationFailure($response);
        }

        $body = is_array($response->body) ? $response->body : [];
        $value = Wire::nullableString($body, 'token_de_acesso');
        if ($value === null || $value === '') {
            throw new CredicarroAuthenticationException(
                'A API do Credicarro retornou uma resposta sem token de acesso.',
                null,
                $response->statusCode
            );
        }

        return new AccessToken($value, self::expiresAt($body, time()));
    }

    /**
     * `400` e `401` na geração do token são falhas de autenticação; os demais erros mantêm o tipo normal.
     */
    private function toAuthenticationFailure(ApiResponse $response): CredicarroException
    {
        if ($response->statusCode === 400 || $response->statusCode === 401) {
            $problem = ProblemDetails::parse($response->body);
            $message = sprintf('Falha ao gerar o token de acesso (%d)', $response->statusCode);
            if ($problem->type !== null) {
                $message .= sprintf(' [%s]', $problem->type);
            }

            return new CredicarroAuthenticationException($message . '.', null, $response->statusCode, $problem);
        }

        return ExceptionFactory::fromResponse($response);
    }

    /**
     * Com `expira_em` (já com 60 s de margem), usa-o. Sem ele: agora + max(expira_em_segundos / 2,
     * expira_em_segundos - 60).
     *
     * @param array<mixed> $body
     */
    public static function expiresAt(array $body, int $now): int
    {
        $expiresAtText = Wire::nullableString($body, 'expira_em');
        if ($expiresAtText !== null && $expiresAtText !== '') {
            $parsed = strtotime($expiresAtText);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        $seconds = max(0, Wire::int($body, 'expira_em_segundos', 0));

        return $now + (int) max($seconds / 2, $seconds - self::SAFETY_MARGIN_SECONDS);
    }
}
