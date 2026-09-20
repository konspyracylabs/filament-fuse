<?php

use Harris21\Fuse\Enums\CircuitState;
use Illuminate\Support\Carbon;
use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;

/**
 * Everything the UI needs to render one circuit, built from Fuse's cache alone.
 */
it('reports an unreadable circuit as its own state, never as healthy', function (): void {
    $snapshot = CircuitSnapshot::unreadable('stripe');

    expect($snapshot->isUnknown())->toBeTrue()
        ->and($snapshot->isClosed())->toBeFalse()
        ->and($snapshot->stateKey())->toBe('unknown')
        // The distinction that matters: an unreachable cache must not read as green.
        ->and($snapshot->isDown())->toBeFalse();
});

it('counts half-open as down', function (): void {
    // One probe is through; everything else is still parked on the queue.
    $snapshot = new CircuitSnapshot('stripe', CircuitState::HalfOpen);

    expect($snapshot->isDown())->toBeTrue();
});

it('measures an outage from when the circuit tripped', function (): void {
    Carbon::setTestNow('2026-08-25 14:06:31');

    $snapshot = new CircuitSnapshot(
        service: 'stripe',
        state: CircuitState::Open,
        openedAt: Carbon::parse('2026-08-25 14:02:19'),
    );

    expect($snapshot->durationInSeconds())->toBe(252);
});

it('reports no duration for a healthy circuit', function (): void {
    // This package keeps no history of its own, so there is no "healthy since" instant
    // to measure from — and inventing one would be a fabricated number.
    $snapshot = new CircuitSnapshot('stripe', CircuitState::Closed);

    expect($snapshot->durationInSeconds())->toBeNull();
});

it('counts down to the next probe only while the circuit is open', function (): void {
    Carbon::setTestNow('2026-08-25 14:00:00');
    $recoveryAt = Carbon::parse('2026-08-25 14:00:48');

    expect((new CircuitSnapshot('a', CircuitState::Open, recoveryAt: $recoveryAt))->secondsUntilProbe())->toBe(48)
        // Half-open means a probe is already through — there is nothing to wait for.
        ->and((new CircuitSnapshot('a', CircuitState::HalfOpen, recoveryAt: $recoveryAt))->secondsUntilProbe())->toBeNull()
        ->and((new CircuitSnapshot('a', CircuitState::Closed, recoveryAt: $recoveryAt))->secondsUntilProbe())->toBeNull();
});

it('floors an overdue probe at zero rather than counting backwards', function (): void {
    Carbon::setTestNow('2026-08-25 14:00:00');

    // Eligible, and waiting on the next job to arrive rather than on the clock.
    $snapshot = new CircuitSnapshot('a', CircuitState::Open, recoveryAt: Carbon::parse('2026-08-25 13:58:00'));

    expect($snapshot->secondsUntilProbe())->toBe(0);
});

it('knows when the failure rate has reached the tripping threshold', function (): void {
    expect((new CircuitSnapshot('a', CircuitState::Closed, failureRate: 71.0, threshold: 50))->isRateHot())->toBeTrue()
        ->and((new CircuitSnapshot('a', CircuitState::Closed, failureRate: 50.0, threshold: 50))->isRateHot())->toBeTrue()
        ->and((new CircuitSnapshot('a', CircuitState::Closed, failureRate: 4.9, threshold: 50))->isRateHot())->toBeFalse()
        // An unconfigured threshold must not make every circuit look critical.
        ->and((new CircuitSnapshot('a', CircuitState::Closed, failureRate: 4.9, threshold: 0))->isRateHot())->toBeFalse();
});
