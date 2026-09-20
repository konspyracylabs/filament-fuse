<?php

use KonspyracyLabs\FilamentFuse\FilamentFuse;

/**
 * The one-circuit rule. This package watches exactly one circuit from
 * config/fuse.php, plus the package's own testing circuit when it is on — never
 * every circuit the host happens to declare.
 */
it('returns the configured name when it is declared', function (): void {
    config()->set('fuse.services', ['stripe' => [], 'sendgrid' => []]);
    config()->set('filament-fuse.circuit', 'sendgrid');

    expect(app(FilamentFuse::class)->chosenCircuit())->toBe('sendgrid');
});

it('falls back to the first declared circuit when none is configured', function (): void {
    config()->set('fuse.services', ['stripe' => [], 'sendgrid' => []]);
    config()->set('filament-fuse.circuit');

    expect(app(FilamentFuse::class)->chosenCircuit())->toBe('stripe');
});

it('is null when no circuits are declared', function (): void {
    config()->set('fuse.services', []);

    expect(app(FilamentFuse::class)->chosenCircuit())->toBeNull();
});

it('ignores the testing circuit when choosing and when there is nothing else declared', function (): void {
    config()->set('fuse.services', []);
    config()->set('filament-fuse.testing_circuit.enabled', true);

    // The testing circuit is merged into config('fuse.services') at boot by the
    // provider, but it must never be picked as "the" circuit by falling through to it.
    config()->set('fuse.services', ['testing-circuit-breaker' => []]);

    expect(app(FilamentFuse::class)->chosenCircuit())->toBeNull();
});

it('falls back when the configured name is blank or not a string', function (): void {
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.circuit', '');

    expect(app(FilamentFuse::class)->chosenCircuit())->toBe('stripe');
});

it('returns a numeric-looking circuit key as a string', function (): void {
    config()->set('fuse.services', ['2024' => []]);

    expect(app(FilamentFuse::class)->chosenCircuit())->toBe('2024')
        ->and(app(FilamentFuse::class)->chosenCircuit())->toBeString();
});

it('monitors exactly the chosen circuit, plus the testing circuit when it is on', function (): void {
    config()->set('fuse.services', ['stripe' => [], 'sendgrid' => []]);

    expect(app(FilamentFuse::class)->circuits())->toBe(['stripe']);

    config()->set('filament-fuse.testing_circuit.enabled', true);

    expect(app(FilamentFuse::class)->circuits())->toBe(['stripe', 'testing-circuit-breaker']);
});

it('never lists the testing circuit twice when it is also the chosen name', function (): void {
    config()->set('fuse.services', ['testing-circuit-breaker' => []]);
    config()->set('filament-fuse.circuit', 'testing-circuit-breaker');
    config()->set('filament-fuse.testing_circuit.enabled', true);

    expect(app(FilamentFuse::class)->circuits())->toBe(['testing-circuit-breaker']);
});

it('reports a configured circuit that config/fuse.php no longer declares', function (): void {
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.circuit', 'gone');

    expect(app(FilamentFuse::class)->missingCircuit())->toBe('gone')
        ->and(app(FilamentFuse::class)->chosenCircuit())->toBe('stripe');
});

it('reports no missing circuit once the configured name is valid or blank', function (): void {
    config()->set('fuse.services', ['stripe' => []]);

    config()->set('filament-fuse.circuit', 'stripe');
    expect(app(FilamentFuse::class)->missingCircuit())->toBeNull();

    config()->set('filament-fuse.circuit');
    expect(app(FilamentFuse::class)->missingCircuit())->toBeNull();
});

it('counts the circuits it is not showing, ignoring the testing circuit', function (): void {
    config()->set('fuse.services', ['stripe' => [], 'sendgrid' => [], 'internal-api' => []]);
    expect(app(FilamentFuse::class)->unmonitoredCount())->toBe(2);

    config()->set('fuse.services', ['stripe' => []]);
    expect(app(FilamentFuse::class)->unmonitoredCount())->toBe(0);

    config()->set('fuse.services', []);
    expect(app(FilamentFuse::class)->unmonitoredCount())->toBe(0);

    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.enabled', true);
    expect(app(FilamentFuse::class)->unmonitoredCount())->toBe(0);
});

it('still excludes only the testing circuit once fuse.services holds its merged-in entry too', function (): void {
    // The shape config('fuse.services') is actually in after boot: two real circuits
    // plus the testing circuit's own entry, merged in by the provider — not just the
    // testing circuit switched on with nothing else declared.
    config()->set('fuse.services', ['stripe' => [], 'sendgrid' => []]);
    config()->set('filament-fuse.testing_circuit.enabled', true);
    registerTestingCircuit();

    expect(config('fuse.services'))->toHaveKey('testing-circuit-breaker')
        ->and(app(FilamentFuse::class)->unmonitoredCount())->toBe(1);
});

it('knows which circuits it monitors and which it does not', function (): void {
    config()->set('fuse.services', ['stripe' => [], 'sendgrid' => []]);

    $fuse = app(FilamentFuse::class);

    expect($fuse->isMonitored('stripe'))->toBeTrue()
        ->and($fuse->isMonitored('sendgrid'))->toBeFalse()
        ->and($fuse->isMonitored('not-a-circuit'))->toBeFalse()
        ->and($fuse->isMonitored('webhook:42'))->toBeFalse();
});
