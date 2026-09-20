<?php

use Harris21\Fuse\CircuitBreaker;
use Harris21\Fuse\Enums\CircuitState;
use KonspyracyLabs\FilamentFuse\FilamentFuse;
use KonspyracyLabs\FilamentFuse\Support\Drill;

/**
 * A drill opens a real circuit, which is the point and also the hazard.
 *
 * Two things are worth more than the happy path here: that it refuses to run where it
 * should, and that it always closes. Everything else is recoverable; a circuit left open
 * because a drill forgot about itself is an outage nobody ordered.
 */
beforeEach(function (): void {
    config()->set('filament-fuse.enabled', true);
    // Only the host's real circuit. The testing one is the package's to register.
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.enabled', true);
    config()->set('filament-fuse.testing_circuit.name', 'testing-circuit-breaker');
    config()->set('filament-fuse.testing_circuit.duration', 300);
});

/**
 * Pose as a production host for the rest of the test.
 */
function drillPosesAsProduction(): void
{
    app()->detectEnvironment(fn (): string => 'production');
}

/*
|--------------------------------------------------------------------------
| What it refuses to do
|--------------------------------------------------------------------------
*/

it('refuses to run until somebody switches drills on', function (): void {
    config()->set('filament-fuse.testing_circuit.enabled', false);

    expect(app(Drill::class)->refusal())->toContain('switched off');
});

it('refuses to start in production unless that was somebody\'s decision, and mentions no mail', function (): void {
    // A drill really opens a circuit breaker, and anyone watching the dashboard will
    // read it as an outage. In production that cannot be the side effect of a command
    // in the wrong terminal.
    drillPosesAsProduction();

    $refusal = app(Drill::class)->refusal();

    expect($refusal)->toContain('refused in production')
        ->and($refusal)->not->toContain('mail')
        ->and($refusal)->not->toContain('email');

    $this->artisan('filament-fuse:drill')->assertFailed();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed);
});

it('drills in production once the host has said so', function (): void {
    drillPosesAsProduction();
    config()->set('filament-fuse.testing_circuit.production', true);

    expect(app(Drill::class)->refusal())->toBeNull();

    $this->artisan('filament-fuse:drill')->assertSuccessful();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Open);
});

it('never refuses to close a drill, whatever the environment', function (): void {
    // Closing is always safe. A drill started elsewhere, or allowed and then
    // disallowed, must still be able to end.
    $this->artisan('filament-fuse:drill')->assertSuccessful();

    drillPosesAsProduction();

    $this->artisan('filament-fuse:drill', ['--stop' => true])->assertSuccessful();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed);
});

it('reminds whoever starts a drill, with no mail wording anywhere in the output', function (): void {
    $this->artisan('filament-fuse:drill')
        ->expectsOutputToContain('Reminder: this is a real trip')
        ->assertSuccessful();
});

it('needs no entry in the Fuse config', function (): void {
    // A circuit that exists only to be broken is a panel concern. Making the host add a
    // fake service to config/fuse.php would put a testing fixture in the circuit
    // breaker's list of real dependencies and leave it there long after anyone recalled
    // why. Switching it on here is the whole setup.
    expect(config('fuse.services'))->not->toHaveKey('testing-circuit-breaker')
        ->and(app(FilamentFuse::class)->isMonitored('testing-circuit-breaker'))->toBeTrue()
        ->and(app(Drill::class)->refusal())->toBeNull();
});

it('is not monitored at all until it is switched on', function (): void {
    config()->set('filament-fuse.testing_circuit.enabled', false);

    expect(app(FilamentFuse::class)->circuits())->toBe(['stripe'])
        ->and(app(FilamentFuse::class)->isMonitored('testing-circuit-breaker'))->toBeFalse();
});

it('appears on the dashboard beside the real circuit once it is on', function (): void {
    expect(app(FilamentFuse::class)->circuits())->toBe(['stripe', 'testing-circuit-breaker']);
});

it('falls back to the documented name when the configured one is blank', function (): void {
    // A half-finished config should still put a circuit somebody recognises on the
    // dashboard, rather than an unnamed one nobody can account for.
    config()->set('filament-fuse.testing_circuit.name', '');

    expect(app(FilamentFuse::class)->circuits())->toBe(['stripe', 'testing-circuit-breaker'])
        ->and(app(Drill::class)->circuit())->toBe('testing-circuit-breaker');
});

it('opens only the circuit named in config, whatever else is monitored', function (): void {
    app(Drill::class)->start(300);

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Open)
        ->and((new CircuitBreaker('stripe'))->getState())->toBe(CircuitState::Closed);
});

/*
|--------------------------------------------------------------------------
| Colliding with a real circuit
|--------------------------------------------------------------------------
*/

it('refuses when the testing circuit\'s name is already a real circuit, and never opens it', function (): void {
    // stripe is a real circuit here, not the package's fixture. Naming the testing
    // circuit after it must never let a drill quietly open the one that carries
    // traffic.
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.name', 'stripe');
    registerTestingCircuit();

    $refusal = app(Drill::class)->refusal();

    expect($refusal)->toContain('stripe')
        ->and($refusal)->toContain('already a circuit declared in config/fuse.php');

    $this->artisan('filament-fuse:drill')->assertFailed();

    expect((new CircuitBreaker('stripe'))->getState())->toBe(CircuitState::Closed);
});

it('leaves an open real circuit alone when --stop is run against a colliding name', function (): void {
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.name', 'stripe');
    registerTestingCircuit();

    (new CircuitBreaker('stripe'))->forceOpen();

    // Non-zero on purpose: a script that only checks the exit code must not read this
    // refusal as a drill closed.
    $this->artisan('filament-fuse:drill', ['--stop' => true])
        ->expectsOutputToContain('not the testing circuit')
        ->expectsOutputToContain('php artisan fuse:close stripe')
        ->assertFailed();

    expect((new CircuitBreaker('stripe'))->getState())->toBe(CircuitState::Open)
        ->and(app(Drill::class)->stop())->toBeFalse();
});

it('does nothing on a scheduled tick against a colliding name', function (): void {
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.name', 'stripe');
    registerTestingCircuit();

    (new CircuitBreaker('stripe'))->forceOpen();

    $this->artisan('filament-fuse:drill', ['--tick' => true])->assertSuccessful();

    expect((new CircuitBreaker('stripe'))->getState())->toBe(CircuitState::Open);
});

it('still shows the colliding circuit as the chosen one, with its subheading intact', function (): void {
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.name', 'stripe');
    registerTestingCircuit();

    $fuse = app(FilamentFuse::class);

    expect($fuse->testingCircuit())->toBeNull()
        ->and($fuse->chosenCircuit())->toBe('stripe')
        ->and($fuse->circuits())->toBe(['stripe']);
});

/*
|--------------------------------------------------------------------------
| What it actually does
|--------------------------------------------------------------------------
*/

it('trips the circuit with real figures rather than forcing it', function (): void {
    // forceOpen() reports nothing, so the dashboard would show a tile with zeroes
    // where a real trip shows a failure rate. A rehearsal should look like the thing
    // it is rehearsing.
    expect(app(Drill::class)->start(300))->toBeTrue();

    $stats = (new CircuitBreaker('testing-circuit-breaker'))->getStats();

    expect($stats['attempts'])->toBeGreaterThan(0)
        ->and($stats['failure_rate'])->toBeGreaterThan(0.0);
});

/*
|--------------------------------------------------------------------------
| That it always closes
|--------------------------------------------------------------------------
*/

it('closes on its own once the time is up', function (): void {
    app(Drill::class)->start(1);

    expect(app(Drill::class)->endIfDue())->toBeFalse('not due yet');

    $this->travel(2)->seconds();

    expect(app(Drill::class)->endIfDue())->toBeTrue()
        ->and((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed)
        ->and(app(Drill::class)->isRunning())->toBeFalse();
});

it('closes when told to, before its time is up', function (): void {
    app(Drill::class)->start(300);

    app(Drill::class)->stop();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed)
        ->and(app(Drill::class)->isRunning())->toBeFalse();
});

it('does not need the process that started it to survive', function (): void {
    // The deadline is recorded, not held in memory, so a crashed console or a deploy
    // mid-drill still ends on time. This is the property that makes it safe to schedule.
    app(Drill::class)->start(1);
    $this->travel(2)->seconds();

    // A completely separate instance, as the scheduler would have.
    expect(app()->make(Drill::class)->endIfDue())->toBeTrue();
});

it('forgets the drill when the cache that held the circuit is lost', function (): void {
    // Fuse keeps breaker state in the same cache, so losing it closes the circuit and
    // forgets the drill in one stroke. The two can never disagree.
    app(Drill::class)->start(300);

    cache()->flush();

    expect(app(Drill::class)->isRunning())->toBeFalse()
        ->and((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed);
});

/*
|--------------------------------------------------------------------------
| Through the command
|--------------------------------------------------------------------------
*/

it('runs a drill from the console', function (): void {
    $this->artisan('filament-fuse:drill', ['--for' => 300])
        ->assertSuccessful();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Open);

    $this->artisan('filament-fuse:drill', ['--stop' => true])->assertSuccessful();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed);
});

it('says plainly when nothing is scheduled to close it', function (): void {
    // The alternative is a circuit somebody forgot about, which is the failure this
    // whole feature has to avoid being the cause of.
    config()->set('filament-fuse.testing_circuit.schedule', false);

    $this->artisan('filament-fuse:drill')
        ->expectsOutputToContain('Nothing is scheduled to close it')
        ->assertSuccessful();
});

it('refuses a second drill while one is running', function (): void {
    $this->artisan('filament-fuse:drill')->assertSuccessful();

    $this->artisan('filament-fuse:drill')->assertFailed();
});

it('fails the command rather than drilling when it is switched off', function (): void {
    config()->set('filament-fuse.testing_circuit.enabled', false);

    $this->artisan('filament-fuse:drill')->assertFailed();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed);
});

it('closes a due drill from the scheduler tick', function (): void {
    app(Drill::class)->start(1);
    $this->travel(2)->seconds();

    $this->artisan('filament-fuse:drill', ['--tick' => true])->assertSuccessful();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed);
});

it('leaves a running drill alone on a tick', function (): void {
    app(Drill::class)->start(300);

    $this->artisan('filament-fuse:drill', ['--tick' => true])->assertSuccessful();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Open);
});

/*
|--------------------------------------------------------------------------
| When it cannot do what was asked
|--------------------------------------------------------------------------
*/

it('will not drill a circuit that is already open', function (): void {
    // Somebody else's outage, or a real one. Either way the drill did not cause it.
    (new CircuitBreaker('testing-circuit-breaker'))->forceOpen();

    expect(app(Drill::class)->start(300))->toBeFalse();

    $this->artisan('filament-fuse:drill')->assertFailed();
});

it('reports no drill running when the cache cannot be read', function (): void {
    // Fuse keeps the breaker in the same cache, so an unreachable one has already closed
    // the circuit. Reporting a drill still in flight would invent work to undo.
    app(Drill::class)->start(300);

    config()->set('cache.default', 'nonexistent-store');

    expect(app(Drill::class)->isRunning())->toBeFalse()
        ->and(app(Drill::class)->endIfDue())->toBeFalse();
});

it('says so when asked to stop with nothing running', function (): void {
    $this->artisan('filament-fuse:drill', ['--stop' => true])
        ->expectsOutputToContain('No drill was running')
        ->assertSuccessful();
});

it('still closes the breaker through --stop when the deadline key has been forgotten', function (): void {
    // The deadline key carries its own TTL and can expire while the breaker is still
    // forced open (see Drill's docblock). Trusting the deadline's absence to mean the
    // circuit already closed itself is exactly the bug this pins.
    app(Drill::class)->start(300);

    cache()->forget('filament-fuse:drill:ends-at');

    expect(app(Drill::class)->isRunning())->toBeFalse()
        ->and((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Open);

    $this->artisan('filament-fuse:drill', ['--stop' => true])
        ->expectsOutputToContain('No drill was running; closed testing-circuit-breaker anyway.')
        ->assertSuccessful();

    expect((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed);
});

it('gives up rather than spinning when a circuit refuses to trip', function (): void {
    // A threshold no number of failures can cross would otherwise loop forever, which is
    // a worse outcome than a drill that did not happen.
    config()->set('fuse.services.testing-circuit-breaker', ['threshold' => 101, 'min_requests' => 2]);

    expect(app(Drill::class)->start(300))->toBeFalse()
        ->and((new CircuitBreaker('testing-circuit-breaker'))->getState())->toBe(CircuitState::Closed);
});
