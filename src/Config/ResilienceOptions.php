<?php

declare(strict_types=1);

namespace Twila\Credicarro\Config;

final class ResilienceOptions
{
    /** @var int */
    public $totalTimeoutMs;

    /** @var int */
    public $attemptTimeoutMs;

    /** @var int */
    public $documentUploadAttemptTimeoutMs;

    /** @var int */
    public $maxRetryAttempts;

    /** @var int */
    public $retryBaseDelayMs;

    /** @var float */
    public $circuitBreakerFailureRatio;

    /** @var int */
    public $circuitBreakerSamplingDurationMs;

    /** @var int */
    public $circuitBreakerMinimumThroughput;

    /** @var int */
    public $circuitBreakerBreakDurationMs;

    /**
     * Repete respostas 500 em requisições seguras para repetição. Padrão `true`: a documentação do Credicarro manda
     * repetir o `500` com a mesma Idempotency-Key.
     *
     * @var bool
     */
    public $retryOn500;

    /** @var bool */
    public $disableAutomaticIdempotencyKey;

    public function __construct(
        int $totalTimeoutMs = 30000,
        int $attemptTimeoutMs = 10000,
        int $documentUploadAttemptTimeoutMs = 60000,
        int $maxRetryAttempts = 3,
        int $retryBaseDelayMs = 500,
        float $circuitBreakerFailureRatio = 0.5,
        int $circuitBreakerSamplingDurationMs = 30000,
        int $circuitBreakerMinimumThroughput = 10,
        int $circuitBreakerBreakDurationMs = 15000,
        bool $retryOn500 = true,
        bool $disableAutomaticIdempotencyKey = false
    ) {
        $this->totalTimeoutMs = $totalTimeoutMs;
        $this->attemptTimeoutMs = $attemptTimeoutMs;
        $this->documentUploadAttemptTimeoutMs = $documentUploadAttemptTimeoutMs;
        $this->maxRetryAttempts = $maxRetryAttempts;
        $this->retryBaseDelayMs = $retryBaseDelayMs;
        $this->circuitBreakerFailureRatio = $circuitBreakerFailureRatio;
        $this->circuitBreakerSamplingDurationMs = $circuitBreakerSamplingDurationMs;
        $this->circuitBreakerMinimumThroughput = $circuitBreakerMinimumThroughput;
        $this->circuitBreakerBreakDurationMs = $circuitBreakerBreakDurationMs;
        $this->retryOn500 = $retryOn500;
        $this->disableAutomaticIdempotencyKey = $disableAutomaticIdempotencyKey;
    }
}
