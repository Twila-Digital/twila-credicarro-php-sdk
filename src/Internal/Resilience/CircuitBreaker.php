<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Resilience;

use Twila\Credicarro\Config\ResilienceOptions;

/**
 * @internal
 */
final class CircuitBreaker
{
    /** @var ResilienceOptions */
    private $options;

    /** @var array<int, array{0: float, 1: bool}> */
    private $events = [];

    /** @var float|null */
    private $openedAt;

    /** @var bool */
    private $halfOpenTrialInFlight = false;

    public function __construct(ResilienceOptions $options)
    {
        $this->options = $options;
    }

    /**
     * @throws BrokenCircuitException
     */
    public function beforeCall(): void
    {
        if ($this->openedAt === null) {
            return;
        }

        $elapsedMs = (microtime(true) - $this->openedAt) * 1000;
        if ($elapsedMs < $this->options->circuitBreakerBreakDurationMs) {
            throw new BrokenCircuitException();
        }

        if ($this->halfOpenTrialInFlight) {
            throw new BrokenCircuitException();
        }

        $this->halfOpenTrialInFlight = true;
    }

    /**
     * Encerra a tentativa de meia-abertura sem veredito (exceção que não é falha de rede, por exemplo um 401
     * persistente ou um erro do token): libera a vaga para um novo teste em vez de deixar o circuito preso.
     */
    public function release(): void
    {
        $this->halfOpenTrialInFlight = false;
    }

    public function onSuccess(): void
    {
        if ($this->openedAt !== null) {
            $this->openedAt = null;
            $this->halfOpenTrialInFlight = false;
            $this->events = [];

            return;
        }

        $this->record(false);
    }

    public function onFailure(): void
    {
        if ($this->openedAt !== null) {
            $this->openedAt = microtime(true);
            $this->halfOpenTrialInFlight = false;

            return;
        }

        $this->record(true);
        $this->maybeOpen();
    }

    private function record(bool $isFailure): void
    {
        $now = microtime(true);
        $this->events[] = [$now, $isFailure];

        $cutoff = $now - ($this->options->circuitBreakerSamplingDurationMs / 1000);
        while (!empty($this->events) && $this->events[0][0] < $cutoff) {
            array_shift($this->events);
        }
    }

    private function maybeOpen(): void
    {
        $total = count($this->events);
        if ($total < $this->options->circuitBreakerMinimumThroughput) {
            return;
        }

        $failures = 0;
        foreach ($this->events as $event) {
            if ($event[1]) {
                $failures++;
            }
        }

        if (($failures / $total) >= $this->options->circuitBreakerFailureRatio) {
            $this->openedAt = microtime(true);
            $this->events = [];
        }
    }
}
