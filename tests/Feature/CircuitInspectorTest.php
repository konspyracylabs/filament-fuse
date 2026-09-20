<?php

use Harris21\Fuse\CircuitBreaker;
use Harris21\Fuse\Enums\CircuitState;
use Illuminate\Support\Facades\DB;
use KonspyracyLabs\FilamentFuse\Filament\Pages\Circuits;
use KonspyracyLabs\FilamentFuse\Support\CircuitInspector;
use Livewire\Livewire;

/**
 * The inspector reads Fuse's cache only. It runs on every poll, so both what it reports
 * and what it costs are worth pinning down.
 */
beforeEach(function (): void {
    config()->set('filament-fuse.enabled', true);
    config()->set('fuse.services', ['stripe' => [], 'sendgrid' => []]);
    config()->set('filament-fuse.testing_circuit.enabled', true);
});

it('reports every monitored circuit', function (): void {
    // Only 'stripe' (the first declared) plus the testing circuit — this package
    // monitors one real circuit, never every one config/fuse.php happens to declare.
    expect(app(CircuitInspector::class)->all()->pluck('service')->all())
        ->toEqualCanonicalizing(['stripe', 'testing-circuit-breaker']);
});

it('reports nothing when no circuits are declared and the testing circuit is off', function (): void {
    config()->set('fuse.services', []);
    config()->set('filament-fuse.testing_circuit.enabled', false);

    expect(app(CircuitInspector::class)->all())->toBeEmpty();
});

it('reads live state from the cache', function (): void {
    (new CircuitBreaker('stripe'))->forceOpen();

    $snapshots = app(CircuitInspector::class)->all()->keyBy('service');

    expect($snapshots['stripe']->state)->toBe(CircuitState::Open)
        ->and($snapshots['testing-circuit-breaker']->state)->toBe(CircuitState::Closed);
});

it('sorts the broken circuit to the front', function (): void {
    (new CircuitBreaker('testing-circuit-breaker'))->forceOpen();

    // Config order puts the testing circuit last; severity has to override that,
    // because at three in the morning the broken one is the only tile anybody wants.
    expect(app(CircuitInspector::class)->all()->first()->service)->toBe('testing-circuit-breaker');
});

it('keeps circuit order among circuits of equal severity', function (): void {
    // A healthy dashboard that reshuffles itself between polls is unreadable.
    expect(app(CircuitInspector::class)->all()->pluck('service')->all())
        ->toBe(['stripe', 'testing-circuit-breaker']);
});

it('counts the circuits that are not serving traffic', function (): void {
    (new CircuitBreaker('stripe'))->forceOpen();
    (new CircuitBreaker('testing-circuit-breaker'))->forceOpen();

    expect(app(CircuitInspector::class)->downCount())->toBe(2);
});

it('counts what is down once per request, however many times it is asked', function (): void {
    // The navigation badge and the topbar indicator both ask on every panel request.
    // The inspector is scoped so they share one pass over the cache.
    $inspector = app(CircuitInspector::class);

    expect($inspector)->toBe(app(CircuitInspector::class))
        ->and($inspector->downCount())->toBe(0);

    (new CircuitBreaker('stripe'))->forceOpen();

    expect($inspector->downCount())->toBe(0);

    $inspector->flush();

    expect($inspector->downCount())->toBe(1);
});

it('reads no database at all to count what is down', function (): void {
    // The topbar indicator renders on every panel request, including pages that have
    // nothing to do with this package. It must not add a query to all of them.
    DB::enableQueryLog();
    app(CircuitInspector::class)->downCount();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});

it('renders the dashboard with no usable database connection at all', function (): void {
    // Point the default connection at a name nothing configures. If the dashboard
    // reached for a database anywhere in its path, resolving it would throw rather
    // than let the page render.
    config()->set('database.default', 'nothing-configured-this-name');

    Livewire::test(Circuits::class)->assertOk();
});

it('spends no database query on the whole dashboard', function (): void {
    // This package keeps no database of its own, so a snapshot of every circuit costs
    // nothing beyond Fuse's own cache reads — regardless of how many circuits are shown.
    DB::enableQueryLog();
    app(CircuitInspector::class)->all();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});
