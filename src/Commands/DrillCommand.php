<?php

namespace KonspyracyLabs\FilamentFuse\Commands;

use Illuminate\Console\Command;
use KonspyracyLabs\FilamentFuse\Support\Coerce;
use KonspyracyLabs\FilamentFuse\Support\Drill;

/**
 * Open a circuit on purpose and close it again, to prove the dashboard works.
 *
 * Three jobs in one command, because they are three views of one piece of state:
 *
 *     filament-fuse:drill              start one, for the configured duration
 *     filament-fuse:drill --stop       end it now
 *     filament-fuse:drill --tick       end it if its time is up (the scheduler's call)
 *
 * `--tick` is what makes this safe to schedule. A drill records when it should end, so
 * the process that started it does not have to survive to finish it, and a drill left
 * running by a crashed deploy still closes on its own.
 */
class DrillCommand extends Command
{
    protected $signature = 'filament-fuse:drill
        {--for= : How long to hold the circuit open, in seconds}
        {--stop : Close a running drill immediately}
        {--tick : Close a running drill if its time is up}';

    protected $description = 'Open a circuit on purpose to prove the dashboard works, and close it again';

    public function handle(Drill $drill): int
    {
        if ($this->option('tick')) {
            return $this->tick($drill);
        }

        if ($this->option('stop')) {
            return $this->stop($drill);
        }

        return $this->start($drill);
    }

    private function start(Drill $drill): int
    {
        $refusal = $drill->refusal();

        if ($refusal !== null) {
            $this->components->error($refusal);

            return self::FAILURE;
        }

        if ($drill->isRunning()) {
            $this->components->warn('A drill is already running. Use --stop to end it.');

            return self::FAILURE;
        }

        $seconds = $this->seconds($drill);

        if (! $drill->start($seconds)) {
            $this->components->error("Could not open [{$drill->circuit()}]. It may already be open, or its threshold may be unreachable.");

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Opened %s for %ds. The tile is red and the topbar indicator is counting it.',
            $drill->circuit(),
            $seconds,
        ));

        // Every time, in every environment: this is a real trip, and whoever ran it
        // should know that before anyone else asks them about it.
        $this->components->warn(sprintf(
            'Reminder: this is a real trip in [%s]. Never rehearse on a circuit that carries traffic.',
            app()->environment(),
        ));

        if (! config('filament-fuse.testing_circuit.schedule', false)) {
            // Said plainly, because the alternative is a circuit somebody forgot about.
            $this->components->warn('Nothing is scheduled to close it. Run --tick from the scheduler, or --stop when you are done.');
        }

        return self::SUCCESS;
    }

    private function stop(Drill $drill): int
    {
        // The one case --stop still has to refuse outright: this name belongs to a real
        // circuit the host declared, not to this package, and closing it here is not
        // this command's to do. Failing the exit code matters as much as the message —
        // a script that only checks whether the command succeeded must not read a
        // refusal as a drill closed.
        if ($drill->collidesWithHostCircuit()) {
            $this->components->error("{$drill->circuit()} is a real circuit declared in config/fuse.php, not the testing circuit — left untouched.");
            $this->components->warn("To close it deliberately, run: php artisan fuse:close {$drill->circuit()}");

            return self::FAILURE;
        }

        // Always close, even with no deadline on record: the deadline key carries its
        // own TTL and can expire while the breaker is still forced open (see Drill's
        // docblock). Trusting its absence to mean the circuit already closed itself is
        // exactly how a drill gets left open forever.
        $wasRunning = $drill->isRunning();

        $drill->stop();

        $this->components->info($wasRunning
            ? "Closed {$drill->circuit()}."
            : "No drill was running; closed {$drill->circuit()} anyway.");

        return self::SUCCESS;
    }

    private function tick(Drill $drill): int
    {
        // Always a silent success, collision or not: this is the scheduler's own call,
        // not somebody at a terminal to warn. endIfDue() already declines to touch
        // either the breaker or the deadline record when the name collides, so there is
        // nothing here to guard beyond staying quiet about it.
        if ($drill->endIfDue()) {
            $this->components->info("Closed {$drill->circuit()}: the drill was due to end.");
        }

        return self::SUCCESS;
    }

    /**
     * How long to hold it open: the flag if given, otherwise the configured default.
     */
    private function seconds(Drill $drill): int
    {
        $override = $this->option('for');

        if ($override === null) {
            return $drill->duration();
        }

        return max(1, Coerce::int($override, $drill->duration()));
    }
}
