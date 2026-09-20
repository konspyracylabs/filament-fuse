<?php

use Harris21\Fuse\CircuitBreaker;
use Harris21\Fuse\Enums\CircuitState;
use KonspyracyLabs\FilamentFuse\FilamentFuse;
use KonspyracyLabs\FilamentFuse\Support\Drill;

/**
 * A cached config must behave exactly like an uncached one.
 *
 * `php artisan config:cache` boots the application to build the cache it dumps, so
 * whatever registerTestingCircuit() adds to `fuse.services` during that boot is baked
 * into the cache alongside everything else config/fuse.php declares. The very next
 * request boots the provider again and finds that entry already sitting in
 * `config('fuse.services')` — indistinguishable, by name alone, from a circuit the host
 * declared. Calling registerTestingCircuit() twice in one process stands in for that:
 * once for the boot that builds the cache, once for the boot that reads it back.
 */
beforeEach(function (): void {
    config()->set('filament-fuse.enabled', true);
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.enabled', true);
    config()->set('filament-fuse.testing_circuit.name', 'testing-circuit-breaker');
    config()->set('filament-fuse.testing_circuit.duration', 300);
});

it('does not mistake its own cached entry for a host declaration', function (): void {
    registerTestingCircuit(); // the boot that builds the cache
    registerTestingCircuit(); // the boot that reads it back

    expect(app(Drill::class)->collidesWithHostCircuit())->toBeFalse()
        ->and(app(Drill::class)->refusal())->toBeNull()
        ->and(app(FilamentFuse::class)->circuits())->toContain('testing-circuit-breaker')
        ->and(config('fuse.services'))->toHaveKey('testing-circuit-breaker');
});

it('still runs and closes normally once its own entry has come back from a cache', function (): void {
    registerTestingCircuit();
    registerTestingCircuit();

    $this->artisan('filament-fuse:drill')->assertSuccessful();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Open);

    $this->artisan('filament-fuse:drill', ['--stop' => true])->assertSuccessful();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed);
});

it('is idempotent no matter how many boots re-register it', function (): void {
    registerTestingCircuit();
    registerTestingCircuit();
    registerTestingCircuit();

    expect(app(Drill::class)->collidesWithHostCircuit())->toBeFalse()
        ->and(config('fuse.services.testing-circuit-breaker'))->toBe([
            'threshold' => 50,
            'min_requests' => 2,
            'timeout' => 30,
            'window' => 120,
            'release' => 5,
        ]);
});

it('still refuses, and preserves the host definition, when the host declares the same name after a cached registration', function (): void {
    // Simulates: a cache was built while this package registered "stripe" as its own
    // testing circuit; the host later adds a genuine "stripe" circuit to
    // config/fuse.php and runs config:cache again. config:cache always deletes the old
    // cache and boots a fresh application from the config files before dumping a new
    // one, so the runtime-only _registered_as flag from the earlier boot does not
    // survive into that fresh boot — cleared here to model exactly that.
    config()->set('fuse.services', []);
    config()->set('filament-fuse.testing_circuit.name', 'stripe');
    registerTestingCircuit();

    expect(config('fuse.services.stripe'))->not->toBeEmpty();

    config()->set('filament-fuse.testing_circuit._registered_as');
    config()->set('fuse.services', ['stripe' => ['real' => 'definition']]);
    registerTestingCircuit();

    expect(app(Drill::class)->collidesWithHostCircuit())->toBeTrue()
        ->and(config('fuse.services.stripe'))->toBe(['real' => 'definition'])
        ->and(app(Drill::class)->refusal())->toContain('already a circuit declared');

    $this->artisan('filament-fuse:drill')->assertFailed();

    expect((new CircuitBreaker('stripe'))->getState())->toBe(CircuitState::Closed);
});

it('has nothing change about the registered name until the config is actually rebuilt', function (): void {
    // Cached config wins: a name is frozen at whatever it was when the cache was built,
    // so changing FILAMENT_FUSE_TESTING_CIRCUIT_NAME in .env has no effect on a running
    // process until config:cache runs again. Registering again with that same frozen
    // name must stay a no-op rather than crash or flip the collision flag.
    registerTestingCircuit();
    registerTestingCircuit();

    expect(app(Drill::class)->circuit())->toBe('testing-circuit-breaker')
        ->and(app(Drill::class)->collidesWithHostCircuit())->toBeFalse();
});

it('leaves an open real circuit alone when drills are switched off and the name collides', function (): void {
    // The other half of the same defect: with drills off, the old code returned before
    // ever computing the collision flag, so it stayed false and --stop/--tick treated a
    // real circuit sharing the testing circuit's configured name as their own to close.
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.name', 'stripe');
    config()->set('filament-fuse.testing_circuit.enabled', false);
    registerTestingCircuit();

    (new CircuitBreaker('stripe'))->forceOpen();

    $this->artisan('filament-fuse:drill', ['--stop' => true])
        ->expectsOutputToContain('php artisan fuse:close stripe')
        ->assertFailed();

    expect((new CircuitBreaker('stripe'))->getState())->toBe(CircuitState::Open);

    $this->artisan('filament-fuse:drill', ['--tick' => true])->assertSuccessful();

    expect((new CircuitBreaker('stripe'))->getState())->toBe(CircuitState::Open);
});

it('still closes a testing circuit an earlier drill left open once drills are switched off', function (): void {
    // The documented recovery path must keep working: drills were on, a drill opened
    // the testing circuit, and somebody then switched FILAMENT_FUSE_TESTING_CIRCUIT off
    // before closing it. Turning drills off must not also turn off the ability to close
    // what an earlier boot left open, because the name here is not a host's.
    registerTestingCircuit();
    app(Drill::class)->start(300);

    config()->set('filament-fuse.testing_circuit.enabled', false);
    registerTestingCircuit();

    expect(app(Drill::class)->collidesWithHostCircuit())->toBeFalse();

    $this->artisan('filament-fuse:drill', ['--stop' => true])->assertSuccessful();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed);
});

it('keeps the deadline record intact when a refusal blocks --stop', function (): void {
    // The collision guard has to run before the deadline key is forgotten, not after.
    // Forgetting first and refusing second would destroy the one record that a drill
    // was running while reporting nothing happened to it.
    registerTestingCircuit();
    app(Drill::class)->start(300);

    expect(app(Drill::class)->isRunning())->toBeTrue();

    // As a config:cache rebuilt mid-drill would, once the host has since declared a
    // real circuit under this same name.
    config()->set('filament-fuse.testing_circuit._collides_with_host', true);

    $this->artisan('filament-fuse:drill', ['--stop' => true])->assertFailed();

    expect(app(Drill::class)->isRunning())->toBeTrue()
        ->and(app(Drill::class)->deadline())->not->toBeNull();

    $this->artisan('filament-fuse:drill', ['--tick' => true])->assertSuccessful();

    expect(app(Drill::class)->isRunning())->toBeTrue();
});

it('does not report a colliding name as its own testing circuit', function (): void {
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.name', 'stripe');
    registerTestingCircuit();

    expect(app(FilamentFuse::class)->testingCircuit())->toBeNull()
        ->and(app(FilamentFuse::class)->circuits())->toBe(['stripe']);
});

it('keeps a due deadline when the scheduler tick meets a colliding name', function (): void {
    // The tick is the one caller that reaches Drill::stop() past the command's own
    // refusal, and it runs unattended. If stop() forgot the deadline before checking
    // whose circuit it was, a drill running across a deploy would lose the only record
    // that it exists and nothing would ever close it.
    registerTestingCircuit();
    app(Drill::class)->start(300);

    $this->travel(400)->seconds();

    config()->set('filament-fuse.testing_circuit._collides_with_host', true);

    $this->artisan('filament-fuse:drill', ['--tick' => true])->assertSuccessful();

    expect(app(Drill::class)->isRunning())->toBeTrue()
        ->and(app(Drill::class)->deadline())->not->toBeNull();
});

it('keeps the deadline when stop() itself refuses', function (): void {
    registerTestingCircuit();
    app(Drill::class)->start(300);

    config()->set('filament-fuse.testing_circuit._collides_with_host', true);

    expect(app(Drill::class)->stop())->toBeFalse()
        ->and(app(Drill::class)->isRunning())->toBeTrue();
});
