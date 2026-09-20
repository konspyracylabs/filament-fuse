<?php

use Harris21\Fuse\Enums\CircuitState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;

/**
 * Structural snapshots of every component's rendered markup.
 *
 * ComponentsTest asserts that each component says the right thing. This asserts that
 * the markup around it does not change without somebody noticing — a dropped modifier
 * class, a lost aria-label, a changed wrapper element, an attribute that quietly
 * stopped being rendered. Those are the regressions that a behavioural assertion sails
 * straight past, because the string it looks for is still on the page.
 *
 * Everything here is built in memory with a frozen clock and explicit ids. A snapshot
 * that depends on an auto-increment value or on the time of day is a snapshot that
 * fails on a Tuesday for no reason, and gets regenerated without being read.
 *
 * Regenerate deliberately, and read the diff before committing:
 *
 *     php vendor/bin/pest --update-snapshots
 */
beforeEach(function (): void {
    config()->set('filament-fuse.enabled', true);
    config()->set('fuse.services', ['stripe' => []]);

    Carbon::setTestNow('2026-08-28 14:06:31');
    View::share('errors', new ViewErrorBag);
});

/*
|--------------------------------------------------------------------------
| Primitives
|--------------------------------------------------------------------------
*/

it('matches the metric card markup', function (): void {
    expect(render('<x-filament-fuse::metric label="Attempts" value="412" caption="in current window" accent="open" />'))
        ->toMatchSnapshot();
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

it('matches the tile markup for a broken circuit', function (): void {
    $html = render('<x-filament-fuse::tile :snapshot="$s" url="/admin/fuse/circuit/stripe" />', [
        's' => snapshot(
            CircuitState::Open,
            openedAt: Carbon::parse('2026-08-28 14:02:19'),
            recoveryAt: Carbon::parse('2026-08-28 14:07:19'),
            attempts: 38,
            failures: 27,
            failureRate: 71.0,
            threshold: 50,
        ),
    ]);

    expect($html)->toMatchSnapshot();
});

it('matches the tile markup for a healthy circuit', function (): void {
    $html = render('<x-filament-fuse::tile :snapshot="$s" url="/admin/fuse/circuit/stripe" />', [
        's' => snapshot(
            CircuitState::Closed,
            attempts: 412,
            failures: 0,
            threshold: 50,
        ),
    ]);

    expect($html)->toMatchSnapshot();
});

it('matches the tile markup for an unreadable circuit', function (): void {
    $html = render('<x-filament-fuse::tile :snapshot="$s" url="/admin/fuse/circuit/stripe" />', [
        's' => CircuitSnapshot::unreadable('stripe'),
    ]);

    expect($html)->toMatchSnapshot();
});

it('matches the live strip markup', function (): void {
    expect(render('<x-filament-fuse::live-strip poll="5s" />'))->toMatchSnapshot();
});

/*
|--------------------------------------------------------------------------
| Topbar indicator
|--------------------------------------------------------------------------
*/

it('matches the healthy indicator markup', function (): void {
    expect(render('<x-filament-fuse::indicator />'))->toMatchSnapshot();
});

/*
|--------------------------------------------------------------------------
| Detail page
|--------------------------------------------------------------------------
*/

it('matches the orb markup', function (): void {
    expect(render('<x-filament-fuse::orb :snapshot="$s" />', ['s' => snapshot(CircuitState::Open)]))
        ->toMatchSnapshot();
});

it('matches the circuit hero markup', function (): void {
    $html = render('<x-filament-fuse::circuit-hero :snapshot="$s" poll="5s" />', [
        's' => snapshot(
            CircuitState::Open,
            openedAt: Carbon::parse('2026-08-28 14:02:19'),
            recoveryAt: Carbon::parse('2026-08-28 14:07:19'),
            attempts: 38,
            failures: 27,
            failureRate: 71.0,
            threshold: 50,
            minRequests: 5,
        ),
    ]);

    expect($html)->toMatchSnapshot();
});

it('matches the circuit hero markup when the cache is unreadable', function (): void {
    expect(render('<x-filament-fuse::circuit-hero :snapshot="$s" />', [
        's' => CircuitSnapshot::unreadable('stripe'),
    ]))->toMatchSnapshot();
});

/*
|--------------------------------------------------------------------------
| Settings and states
|--------------------------------------------------------------------------
*/

it('matches the empty state markup', function (): void {
    expect(render('<x-filament-fuse::empty-state icon="heroicon-o-bolt-slash" heading="No circuits" body="Declare them in config." />'))
        ->toMatchSnapshot();
});

it('matches the good-news empty state markup', function (): void {
    expect(render('<x-filament-fuse::empty-state good icon="heroicon-o-check-circle" heading="Never failed" body="Nothing to show." />'))
        ->toMatchSnapshot();
});

it('matches the error state markup', function (): void {
    expect(render('<x-filament-fuse::error-state heading="Cannot read state" body="Check the cache connection." />'))
        ->toMatchSnapshot();
});
