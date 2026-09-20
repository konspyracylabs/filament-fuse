<?php

namespace KonspyracyLabs\FilamentFuse\Support;

use Harris21\Fuse\CircuitBreaker;
use Harris21\Fuse\Enums\CircuitState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;
use KonspyracyLabs\FilamentFuse\FilamentFuse;
use Throwable;

/**
 * Builds {@see CircuitSnapshot} objects from Fuse's own cache.
 *
 * This package keeps no database, so there is nothing to merge and nothing to query:
 * every snapshot is one call to Fuse's own `getStats()`, which is itself around five
 * cache reads — state, attempts, failures, opened_at, recovery_at — not one. A dozen
 * circuits would cost roughly five dozen cache reads; this package monitors at most
 * two (the chosen circuit, plus the testing circuit when it is on), so the true cost
 * is small and constant. Zero database queries, however many circuits are shown.
 */
class CircuitInspector
{
    /**
     * Memoised down-or-not per circuit for this request.
     *
     * The inspector is a scoped binding, so this is shared between the navigation
     * badges and the topbar indicator, which would otherwise each read every circuit's
     * state on every panel request.
     *
     * @var array<string, bool>|null
     */
    private ?array $downStates = null;

    /**
     * Services whose broken cache has already been reported this request.
     *
     * A dashboard poll reads the circuit's stats and, separately, its state — two
     * cache reads apiece during an outage of the cache itself. Without this, a single
     * broken cache floods the error tracker with the same failure once per read rather
     * than once per circuit.
     *
     * @var array<string, true>
     */
    private array $reportedFailures = [];

    public function __construct(
        private readonly FilamentFuse $fuse,
    ) {}

    /**
     * Snapshot every monitored circuit, worst first.
     *
     * Open circuits sort ahead of probing ones, which sort ahead of unreadable and
     * healthy ones. Within each group the order of {@see FilamentFuse::circuits()} is
     * kept, so a healthy dashboard never reshuffles itself between polls.
     *
     * @return Collection<int, CircuitSnapshot>
     */
    public function all(): Collection
    {
        $services = $this->fuse->circuits();

        if ($services === []) {
            return new Collection;
        }

        return (new Collection($services))
            ->map(fn (string $service): CircuitSnapshot => $this->build($service))
            ->sortBy(fn (CircuitSnapshot $s): int => $this->severity($s))
            ->values();
    }

    /**
     * Snapshot a single circuit.
     */
    public function snapshot(string $service): CircuitSnapshot
    {
        return $this->build($service);
    }

    /**
     * How many monitored circuits are not currently serving traffic.
     *
     * Used by the topbar indicator and the navigation badges, which run on every panel
     * request and so read the cache only — never a database — and only once between
     * them, thanks to the memo.
     */
    public function downCount(): int
    {
        return count(array_filter($this->downStates()));
    }

    /**
     * Whether one circuit is not currently serving traffic. Same memo as the count.
     */
    public function isDown(string $service): bool
    {
        return $this->downStates()[$service] ?? false;
    }

    /**
     * Forget the memoised states.
     *
     * Only useful where one process spans what would be several requests — tests, and
     * the smoke harness.
     */
    public function flush(): void
    {
        $this->downStates = null;
        $this->reportedFailures = [];
    }

    /**
     * Every monitored circuit's down-or-not, read from the cache once per request.
     *
     * @return array<string, bool>
     */
    private function downStates(): array
    {
        if ($this->downStates !== null) {
            return $this->downStates;
        }

        $states = [];

        foreach ($this->fuse->circuits() as $service) {
            $state = $this->readState($service);

            $states[$service] = $state === CircuitState::Open || $state === CircuitState::HalfOpen;
        }

        return $this->downStates = $states;
    }

    /**
     * Read one circuit's live state from Fuse's cache.
     */
    private function build(string $service): CircuitSnapshot
    {
        $stats = $this->readStats($service);

        if ($stats === null) {
            return CircuitSnapshot::unreadable($service);
        }

        return new CircuitSnapshot(
            service: $service,
            state: CircuitState::tryFrom(Coerce::string($stats['state'] ?? null, '')),
            openedAt: $this->timestamp($stats['opened_at'] ?? null),
            recoveryAt: $this->timestamp($stats['recovery_at'] ?? null),
            attempts: Coerce::int($stats['attempts'] ?? null, 0),
            failures: Coerce::int($stats['failures'] ?? null, 0),
            failureRate: Coerce::float($stats['failure_rate'] ?? null, 0.0),
            threshold: Coerce::int($stats['threshold'] ?? null, 0),
            minRequests: Coerce::int($stats['min_requests'] ?? null, 0),
            timeout: Coerce::int($stats['timeout'] ?? null, 0),
            window: Coerce::int($stats['window'] ?? null, 0),
        );
    }

    /**
     * Read Fuse's statistics for a circuit, or null if the cache is unreachable.
     *
     * @return array<string, mixed>|null
     */
    private function readStats(string $service): ?array
    {
        try {
            return (new CircuitBreaker($service))->getStats();
        } catch (Throwable $e) {
            $this->reportOnce($service, $e);

            return null;
        }
    }

    /**
     * Read just the state, for callers that do not need the full statistics.
     */
    private function readState(string $service): ?CircuitState
    {
        try {
            return (new CircuitBreaker($service))->getState();
        } catch (Throwable $e) {
            $this->reportOnce($service, $e);

            return null;
        }
    }

    /**
     * Report a cache failure once per circuit per request.
     */
    private function reportOnce(string $service, Throwable $e): void
    {
        if (isset($this->reportedFailures[$service])) {
            return;
        }

        $this->reportedFailures[$service] = true;

        report($e);
    }

    /**
     * Sort weight: lower sorts first.
     *
     * Unknown sits between probing and healthy. It is not an outage, but it is not
     * reassurance either, and it should not be buried at the bottom of the grid.
     */
    private function severity(CircuitSnapshot $snapshot): int
    {
        return match (true) {
            $snapshot->isOpen() => 0,
            $snapshot->isHalfOpen() => 1,
            $snapshot->isUnknown() => 2,
            default => 3,
        };
    }

    /**
     * Fuse stores instants as unix timestamps in the cache.
     */
    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value);
    }
}
