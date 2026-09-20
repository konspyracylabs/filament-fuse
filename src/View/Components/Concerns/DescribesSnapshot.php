<?php

namespace KonspyracyLabs\FilamentFuse\View\Components\Concerns;

use Illuminate\Support\Carbon;
use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;
use KonspyracyLabs\FilamentFuse\Support\Duration;

/**
 * The sentences a tile and the detail hero both say about a circuit.
 *
 * Shared so the two never disagree, and so the wording lives in one place: every phrase
 * here has to survive being read at 3am by someone who did not build the package.
 */
trait DescribesSnapshot
{
    /**
     * How long the circuit has been in its present condition, worded for that condition.
     *
     * Null when there is nothing to measure from — a healthy circuit that has never been
     * recorded failing has no "healthy since" instant, and inventing one would be a lie.
     */
    protected function describeDuration(CircuitSnapshot $snapshot): ?string
    {
        if ($snapshot->isUnknown()) {
            return $this->label('tile.state_unknown');
        }

        $seconds = $snapshot->durationInSeconds();

        if ($seconds === null) {
            return null;
        }

        if ($snapshot->isOpen()) {
            return $this->label('tile.open_for', ['duration' => Duration::humanize($seconds)]);
        }

        if ($snapshot->isHalfOpen()) {
            return $this->label('tile.half_open_for', ['duration' => Duration::humanize($seconds)]);
        }

        return null;
    }

    /**
     * When the next recovery attempt is due.
     *
     * A countdown while the cooldown runs; the clock time once it has passed — Fuse admits
     * the next job for the circuit as the probe, and the time says since when that has
     * been true. "In flight" while a probe is out. Nothing for a healthy circuit.
     */
    protected function describeNextProbe(CircuitSnapshot $snapshot): string
    {
        if ($snapshot->isHalfOpen()) {
            return $this->label('tile.probe_in_flight');
        }

        $seconds = $snapshot->secondsUntilProbe();

        if ($seconds === null) {
            return $this->label('tile.none');
        }

        if ($seconds > 0) {
            return $this->label('tile.next_probe_in', ['duration' => Duration::countdown($seconds)]);
        }

        $since = $snapshot->recoveryAt;

        return $since instanceof Carbon
            ? $this->label('tile.next_probe_due', ['time' => $this->clock($since)])
            : $this->label('tile.next_probe_due_untimed');
    }
}
