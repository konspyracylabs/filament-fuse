<?php

use Harris21\Fuse\CircuitBreaker;
use Harris21\Fuse\Enums\CircuitState;
use Harris21\Fuse\FuseServiceProvider;
use Illuminate\Contracts\Console\Kernel;

/**
 * harris21/laravel-fuse is pinned at ^0.10, which on a 0.x package locks the minor.
 * These tests are the reason for that pin: they assert the exact upstream surface this
 * plugin builds on, so a dependency bump fails here with a clear message rather than
 * somewhere deep in a component later.
 */
it('loads the Fuse service provider alongside this package', function (): void {
    expect(app()->getLoadedProviders())->toHaveKey(FuseServiceProvider::class);
});

it('exposes the CircuitBreaker methods the plugin will read', function (): void {
    $methods = get_class_methods(CircuitBreaker::class);

    expect($methods)->toContain(
        'getState',
        'getStats',
        'isOpen',
        'isHalfOpen',
        'isClosed',
        'recordSuccess',
        'recordFailure',
        'reset',
        'key',
    );
});

it('returns the stats keys the dashboard will render', function (): void {
    $stats = (new CircuitBreaker('contract-test'))->getStats();

    expect($stats)->toHaveKeys([
        'state',
        'attempts',
        'failures',
        'failure_rate',
        'opened_at',
        'recovery_at',
        'timeout',
        'threshold',
        'min_requests',
        'window',
    ]);
});

it('models all three circuit states', function (): void {
    expect(array_column(CircuitState::cases(), 'value'))
        ->toEqualCanonicalizing(['closed', 'open', 'half_open']);
});

it('ships the open and close commands this package documents but does not wrap', function (): void {
    // filament-fuse:drill is the only open/close pair this package ships; a real
    // circuit is opened and closed with Fuse's own commands, documented in the README.
    expect(array_keys(app()->make(Kernel::class)->all()))
        ->toContain('fuse:open')
        ->toContain('fuse:close');
});
