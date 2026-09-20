<?php

namespace KonspyracyLabs\FilamentFuse;

use KonspyracyLabs\FilamentFuse\Support\Coerce;

/**
 * Entry point for the package's runtime state.
 *
 * There is nothing to install here: no tables, no migrations, no cache-backed
 * "installed" check. {@see self::isEnabled()} is a config read, and everything the
 * package does — the pages, the navigation, the topbar indicator — is gated behind it,
 * so a host can switch the whole thing off with one environment variable.
 */
class FilamentFuse
{
    /**
     * Whether the package should do anything at all.
     */
    public function isEnabled(): bool
    {
        return (bool) config('filament-fuse.enabled', true);
    }

    /**
     * The circuits this package monitors: one, plus the testing circuit when it is on.
     *
     * @return list<string>
     */
    public function circuits(): array
    {
        $circuits = [];

        $chosen = $this->chosenCircuit();

        if ($chosen !== null) {
            $circuits[] = $chosen;
        }

        $testing = $this->testingCircuit();

        if ($testing !== null && ! in_array($testing, $circuits, true)) {
            $circuits[] = $testing;
        }

        return $circuits;
    }

    /**
     * The one circuit from config/fuse.php this package shows.
     *
     * The configured name when it is still declared there; otherwise the first circuit
     * declared, so a dashboard that was working does not go blank because somebody
     * renamed a service. The package's own testing circuit is never eligible here — it
     * is merged into config('fuse.services') at boot, but it is not one of the host's
     * real circuits and must never be picked as "the" circuit by falling through.
     */
    public function chosenCircuit(): ?string
    {
        $names = $this->realCircuitNames();

        if ($names === []) {
            return null;
        }

        $configured = Coerce::stringOrNull(config('filament-fuse.circuit'));

        return $configured !== null && in_array($configured, $names, true) ? $configured : $names[0];
    }

    /**
     * A configured circuit name that is no longer declared in config/fuse.php.
     */
    public function missingCircuit(): ?string
    {
        $configured = Coerce::stringOrNull(config('filament-fuse.circuit'));

        return $configured !== null && $configured !== $this->chosenCircuit() ? $configured : null;
    }

    /**
     * How many circuits in config/fuse.php this package is not showing.
     */
    public function unmonitoredCount(): int
    {
        return max(0, count($this->realCircuitNames()) - 1);
    }

    /**
     * The package's own circuit for rehearsing outages, when it is switched on.
     *
     * Null, too, when the configured name collides with a circuit config/fuse.php
     * already declares: the provider leaves that circuit alone rather than take it
     * over, so the name belongs to the host's real circuit and not to this package —
     * treating it as the testing circuit here would drop it from realCircuitNames()
     * below and cost it its place as the chosen circuit and its subheading.
     */
    public function testingCircuit(): ?string
    {
        if (! config('filament-fuse.testing_circuit.enabled', false)) {
            return null;
        }

        if ((bool) config('filament-fuse.testing_circuit._collides_with_host', false)) {
            return null;
        }

        // Coerce turns a blank or malformed name into the documented default, so a
        // half-finished config still gets a circuit with a name somebody can recognise
        // rather than an unnamed one on the dashboard.
        return Coerce::string(config('filament-fuse.testing_circuit.name'), 'testing-circuit-breaker');
    }

    /**
     * Whether a circuit name is one this package tracks.
     */
    public function isMonitored(string $service): bool
    {
        return in_array($service, $this->circuits(), true);
    }

    /**
     * Every circuit declared in config/fuse.php, excluding the package's own testing
     * circuit — which the provider merges into that same array at boot, but which is
     * never one of the host's real circuits.
     *
     * @return list<string>
     */
    private function realCircuitNames(): array
    {
        /** @var array<string, mixed> $services */
        $services = config('fuse.services', []);

        // array_keys() already returns a list; strval() guards against a numeric-looking
        // service name such as '2024' coming back from config as an int key.
        $names = array_map(strval(...), array_keys($services));

        $testing = $this->testingCircuit();

        return $testing === null
            ? $names
            : array_values(array_filter($names, fn (string $name): bool => $name !== $testing));
    }
}
