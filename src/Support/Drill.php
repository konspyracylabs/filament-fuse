<?php

namespace KonspyracyLabs\FilamentFuse\Support;

use Harris21\Fuse\CircuitBreaker;
use Harris21\Fuse\Enums\CircuitState;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Opens a circuit on purpose, so an operator can watch the dashboard work.
 *
 * A drill is not a simulation. Fuse really transitions, the tile really turns red and
 * moves into the Open section, the topbar indicator really counts it. That is the
 * entire value: the question "does my dashboard actually show an outage" has no useful
 * answer short of causing one.
 *
 * It can only ever open the package's own testing circuit, and there is no flag to aim
 * it elsewhere. Opening a circuit that carries traffic stops that traffic — jobs for
 * the service are held and released on a timer — so a drill against a real one is an
 * outage you caused. The testing circuit has no job routed through it, so the signal
 * is identical and the blast radius is nothing.
 *
 * That guarantee depends on the testing circuit's name actually being free. If it
 * collides with a circuit config/fuse.php already declares, the provider leaves that
 * circuit alone rather than take it over — and this class refuses too, rather than
 * quietly opening (or closing) a breaker that carries real traffic under the name it
 * expected to be its own.
 *
 * The deadline lives in the cache beside Fuse's own state, deliberately. If the whole
 * cache store is lost at once — a restart, a flush — the breaker resets to closed and
 * the drill is forgotten in the same instant, so the two cannot disagree in that failure.
 *
 * They can still disagree on their own clocks: the deadline key carries a TTL of
 * `duration + 3600` as a safety margin, but Fuse's own open-circuit state has no matching
 * expiry and the testing circuit carries no real traffic to probe it back closed. Left
 * long enough past that margin, the deadline key can expire while the circuit is still
 * forced open — which is why stopping a drill always closes the breaker, whether or not
 * a deadline is still on record, rather than trusting the deadline's absence to mean the
 * circuit already closed itself.
 */
class Drill
{
    private const string DEADLINE_KEY = 'filament-fuse:drill:ends-at';

    /**
     * Why a drill cannot run right now, or null if it can.
     */
    public function refusal(): ?string
    {
        if (! config('filament-fuse.testing_circuit.enabled', false)) {
            return 'The testing circuit is switched off. Set FILAMENT_FUSE_TESTING_CIRCUIT=true to allow drills.';
        }

        // Switching the testing circuit on is usually what registers it — but not when
        // its name collides with a circuit the host already declared in
        // config/fuse.php: registerTestingCircuit() leaves that one alone rather than
        // take it over, and a drill must refuse for the same reason. Without this check
        // "the testing circuit" here would quietly mean the host's real one instead.
        if ($this->collidesWithHostCircuit()) {
            return sprintf(
                'The testing circuit\'s name, "%s", is already a circuit declared in config/fuse.php. '.
                'A drill would open that real circuit instead of rehearsing anything. Rename '.
                'FILAMENT_FUSE_TESTING_CIRCUIT_NAME, or rename the circuit in config/fuse.php, so the two stop colliding.',
                $this->circuit(),
            );
        }

        // A drill opens a real circuit breaker, and anyone watching the dashboard will
        // read it as an outage. In production that has to be somebody's decision, not
        // the side effect of a command pasted into the wrong terminal. Stopping and
        // ticking are never refused: closing a circuit is always safe, and a drill
        // already running must be able to end.
        if (app()->environment('production') && ! config('filament-fuse.testing_circuit.production', false)) {
            return 'Drills are refused in production. A drill really opens a circuit breaker, and anyone watching the dashboard will read it as an outage. Run it locally or in staging, or set FILAMENT_FUSE_TESTING_CIRCUIT_PRODUCTION=true if a production drill is what you mean.';
        }

        return null;
    }

    /**
     * Whether the testing circuit's configured name is already a circuit the host
     * declared in config/fuse.php — the one case where switching the testing circuit on
     * does not actually register it. {@see FilamentFuseServiceProvider::registerTestingCircuit()}
     * is what decides this; a drill only ever reads the answer.
     */
    public function collidesWithHostCircuit(): bool
    {
        return (bool) config('filament-fuse.testing_circuit._collides_with_host', false);
    }

    /**
     * The package's own circuit. The only one a drill may ever open.
     */
    public function circuit(): string
    {
        return Coerce::string(config('filament-fuse.testing_circuit.name'), 'testing-circuit-breaker');
    }

    public function duration(): int
    {
        return max(1, Coerce::int(config('filament-fuse.testing_circuit.duration'), 300));
    }

    public function isRunning(): bool
    {
        return $this->deadline() !== null;
    }

    /**
     * When the running drill is due to end, or null if none is running.
     */
    public function deadline(): ?int
    {
        try {
            $deadline = Cache::get(self::DEADLINE_KEY);
        } catch (Throwable) {
            // An unreachable cache has already closed the breaker as far as Fuse is
            // concerned, so there is no drill left to end either.
            return null;
        }

        return is_int($deadline) ? $deadline : null;
    }

    /**
     * Trip the circuit by failing it, the way traffic would.
     *
     * Deliberately not forceOpen(): that reports no statistics, so the dashboard would
     * show a tile with zeroes where a real trip shows a failure rate. Recording real
     * failures makes the rehearsal look like the thing it is rehearsing.
     *
     * @return bool Whether the circuit actually opened.
     */
    public function start(int $seconds): bool
    {
        $breaker = new CircuitBreaker($this->circuit());

        if ($breaker->getState() !== CircuitState::Closed) {
            return false;
        }

        $minRequests = max(1, Coerce::int(
            config("fuse.services.{$this->circuit()}.min_requests") ?? config('fuse.default_min_requests'),
            10,
        ));

        // Bounded rather than a while: a threshold of zero would otherwise spin here.
        for ($i = 0; $i < $minRequests * 20 && $breaker->getState() === CircuitState::Closed; $i++) {
            $breaker->recordFailure();
        }

        if ($breaker->getState() === CircuitState::Closed) {
            return false;
        }

        // now() rather than time(), so the deadline follows the same clock as every other
        // timestamp this package writes — including a frozen one under test.
        Cache::put(self::DEADLINE_KEY, now()->getTimestamp() + $seconds, $seconds + 3600);

        return true;
    }

    /**
     * Close the circuit and forget the drill.
     *
     * The collision guard runs before anything else, deliberately. Forgetting the
     * deadline key first and only then refusing would destroy the one record that a
     * drill was running while reporting that nothing happened to it — the next `--tick`
     * would find no deadline, assume there was never a drill, and leave a real circuit
     * that this method refused to touch looking exactly like one nobody ever opened.
     *
     * @return bool Whether the breaker was actually closed. False only when the
     *              testing circuit's name collides with a circuit the host declared —
     *              a colliding name was never this package's to open, so it is not
     *              this package's to close either, and neither is its deadline record
     *              this package's to erase.
     */
    public function stop(): bool
    {
        if ($this->collidesWithHostCircuit()) {
            return false;
        }

        Cache::forget(self::DEADLINE_KEY);

        (new CircuitBreaker($this->circuit()))->forceClose();

        return true;
    }

    /**
     * Close the drill if its time is up. What the scheduler calls.
     *
     * @return bool Whether a drill was ended by this call.
     */
    public function endIfDue(): bool
    {
        $deadline = $this->deadline();

        if ($deadline === null || $deadline > now()->getTimestamp()) {
            return false;
        }

        return $this->stop();
    }
}
