<?php

namespace KonspyracyLabs\FilamentFuse\Data;

use Harris21\Fuse\Enums\CircuitState;
use Illuminate\Support\Carbon;

/**
 * Everything the UI needs to render one circuit, resolved at a single instant.
 *
 * Built entirely from Fuse's own cache — the current state, the counters in the
 * current window, and when the next probe becomes eligible. Fuse remembers nothing
 * beyond the present, and this package keeps no history of its own, so there is no
 * "healthy for six days" figure here: only what is true right now.
 *
 * A null {@see self::$state} means the cache could not be read. That is deliberately
 * distinct from Closed: a monitoring tool that renders an unreachable cache as green
 * has told the operator a lie at exactly the wrong moment.
 */
readonly class CircuitSnapshot
{
    /**
     * @param  string  $service  Circuit name, as it appears in config('fuse.services').
     * @param  CircuitState|null  $state  Null when Fuse's cache could not be read.
     * @param  Carbon|null  $openedAt  When the circuit tripped. Present only while open or half-open.
     * @param  Carbon|null  $recoveryAt  When the next probe becomes eligible.
     * @param  int  $attempts  Calls in Fuse's current failure window.
     * @param  int  $failures  Failures in that same window.
     * @param  float  $failureRate  Percentage, already rounded by Fuse.
     * @param  int  $threshold  Failure rate that trips this circuit.
     * @param  int  $minRequests  Calls required before the rate is evaluated at all.
     * @param  int  $timeout  Seconds a circuit stays open before a probe is admitted.
     * @param  int  $window  Length of the failure-tracking window, in seconds.
     */
    public function __construct(
        public string $service,
        public ?CircuitState $state,
        public ?Carbon $openedAt = null,
        public ?Carbon $recoveryAt = null,
        public int $attempts = 0,
        public int $failures = 0,
        public float $failureRate = 0.0,
        public int $threshold = 0,
        public int $minRequests = 0,
        public int $timeout = 0,
        public int $window = 0,
    ) {}

    /**
     * A circuit whose state could not be read.
     *
     * Rendered as its own visual state rather than folded into any of Fuse's three.
     */
    public static function unreadable(string $service): self
    {
        return new self(service: $service, state: null);
    }

    public function isClosed(): bool
    {
        return $this->state === CircuitState::Closed;
    }

    public function isOpen(): bool
    {
        return $this->state === CircuitState::Open;
    }

    public function isHalfOpen(): bool
    {
        return $this->state === CircuitState::HalfOpen;
    }

    /**
     * Whether the circuit is not currently serving traffic.
     *
     * Half-open counts as down: one probe is through, everything else is still parked.
     */
    public function isDown(): bool
    {
        return $this->isOpen() || $this->isHalfOpen();
    }

    public function isUnknown(): bool
    {
        return ! $this->state instanceof CircuitState;
    }

    /**
     * Suffix used to build state-specific CSS classes and translation keys.
     */
    public function stateKey(): string
    {
        return $this->state->value ?? 'unknown';
    }

    /**
     * How long the circuit has been in its present condition, in seconds.
     *
     * Null when there is nothing to measure from: a healthy circuit has no "healthy
     * since" instant to report — this package keeps no record of when it last tripped
     * — and inventing one would be a fabricated number.
     */
    public function durationInSeconds(): ?int
    {
        $since = $this->isDown() ? $this->openedAt : null;

        if (! $since instanceof Carbon) {
            return null;
        }

        return (int) $since->diffInSeconds(Carbon::now(), absolute: true);
    }

    /**
     * Seconds until the next probe is admitted, or null when that is not pending.
     *
     * Zero means the circuit is already eligible and is waiting on the next job to
     * arrive rather than on the clock.
     */
    public function secondsUntilProbe(): ?int
    {
        if (! $this->isOpen() || ! $this->recoveryAt instanceof Carbon) {
            return null;
        }

        return max(0, (int) Carbon::now()->diffInSeconds($this->recoveryAt, absolute: false));
    }

    /**
     * Whether the failure rate has reached the level that trips this circuit.
     */
    public function isRateHot(): bool
    {
        return $this->threshold > 0 && $this->failureRate >= $this->threshold;
    }
}
