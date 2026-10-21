<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Resilience;

use LogicException;
use RuntimeException;
use Throwable;
use Twila\Credicarro\Config\ResilienceOptions;
use Twila\Credicarro\Errors\CredicarroTimeoutException;
use Twila\Credicarro\Internal\Http\ApiResponse;

/**
 * Retry com backoff exponencial e jitter, orçamento de tempo e circuit breaker. Só repete quando a chamada é
 * segura para repetição ($retrySafe). Todo sleep é limitado ao orçamento restante.
 *
 * @internal
 */
final class ResiliencePipeline
{
    /** @var ResilienceOptions */
    private $options;

    /** @var CircuitBreaker */
    private $circuitBreaker;

    public function __construct(ResilienceOptions $options)
    {
        $this->options = $options;
        $this->circuitBreaker = new CircuitBreaker($options);
    }

    /**
     * @param callable():ApiResponse $attempt
     * @param int|null $budgetMs orçamento total da chamada; por padrão, `totalTimeoutMs`
     */
    public function execute(bool $retrySafe, callable $attempt, ?int $budgetMs = null): ApiResponse
    {
        $deadline = microtime(true) + (($budgetMs ?? $this->options->totalTimeoutMs) / 1000);
        $maxAttempts = max(1, $this->options->maxRetryAttempts);

        for ($attemptNumber = 1; $attemptNumber <= $maxAttempts; $attemptNumber++) {
            $this->ensureCanCall($deadline);
            $canRetry = $retrySafe && $attemptNumber < $maxAttempts;

            try {
                $response = $attempt();
            } catch (Throwable $error) {
                $this->awaitNetworkRetry($error, $canRetry, $attemptNumber, $deadline);
                continue;
            }

            $delay = $this->delayBeforeRetry($response, $canRetry, $attemptNumber, $deadline);
            if ($delay === null) {
                return $response;
            }

            $this->sleepSeconds($delay);
        }

        throw new LogicException('unreachable: resilience pipeline loop did not return or raise');
    }

    private function ensureCanCall(float $deadline): void
    {
        if (microtime(true) >= $deadline) {
            throw new CredicarroTimeoutException('A requisição excedeu o tempo limite configurado.');
        }

        try {
            $this->circuitBreaker->beforeCall();
        } catch (BrokenCircuitException $broken) {
            throw new CredicarroTimeoutException(
                'O circuit breaker está aberto — chamadas recentes falharam de forma consistente.'
            );
        }
    }

    /**
     * Espera o backoff antes de repetir uma falha de rede. Relança qualquer outra exceção e, sem tentativa ou
     * orçamento, lança o timeout com a causa sanitizada.
     */
    private function awaitNetworkRetry(Throwable $error, bool $canRetry, int $attemptNumber, float $deadline): void
    {
        if (!TransientFailureClassifier::isNetworkError($error)) {
            // Qualquer exceção que não seja falha de rede (401 persistente, erro do token, bug) encerra o
            // teste de meia-abertura: sem isso o circuito ficaria preso para sempre.
            $this->circuitBreaker->release();
            throw $error;
        }

        $this->circuitBreaker->onFailure();

        $remaining = $deadline - microtime(true);
        if (!$canRetry || $remaining <= 0) {
            throw new CredicarroTimeoutException(
                'Falha de rede ou timeout ao chamar a API do Credicarro.',
                self::sanitizedCause($error)
            );
        }

        $this->sleepSeconds(min($this->backoffSeconds($attemptNumber), $remaining));
    }

    /**
     * Registra a resposta no circuit breaker e devolve a espera até a próxima tentativa, ou null quando a resposta
     * deve ser devolvida já.
     */
    private function delayBeforeRetry(
        ApiResponse $response,
        bool $canRetry,
        int $attemptNumber,
        float $deadline
    ): ?float {
        $transient = TransientFailureClassifier::isTransientResponse($response, $this->options);
        $this->recordOutcome($transient);

        $remaining = $deadline - microtime(true);
        if (!$transient || !$canRetry || $remaining <= 0) {
            return null;
        }

        $retryAfter = RetryAfter::seconds($response->header('retry-after'));
        if ($retryAfter !== null && $retryAfter > $remaining) {
            // Esperar além do orçamento seria inútil: devolve já (o chamador lança o erro com o Retry-After).
            return null;
        }

        return min($retryAfter ?? $this->backoffSeconds($attemptNumber), $remaining);
    }

    private function recordOutcome(bool $failed): void
    {
        if ($failed) {
            $this->circuitBreaker->onFailure();

            return;
        }

        $this->circuitBreaker->onSuccess();
    }

    /**
     * Causa sem o objeto de requisição/resposta do Guzzle, sem headers e sem corpo: só o tipo e o código.
     */
    private static function sanitizedCause(Throwable $error): RuntimeException
    {
        $name = substr((string) strrchr('\\' . get_class($error), '\\'), 1);

        return new RuntimeException(sprintf('%s (código %d)', $name, $error->getCode()));
    }

    private function backoffSeconds(int $attemptNumber): float
    {
        $baseDelayMs = $this->options->retryBaseDelayMs;
        $exponential = $baseDelayMs * (2 ** max(0, $attemptNumber - 1));
        $jitterFactor = 0.5 + (mt_rand() / mt_getrandmax());

        return ($exponential * $jitterFactor) / 1000;
    }

    private function sleepSeconds(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        usleep((int) round($seconds * 1000000));
    }
}
