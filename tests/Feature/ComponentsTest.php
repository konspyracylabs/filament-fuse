<?php

use Harris21\Fuse\CircuitBreaker;
use Harris21\Fuse\Enums\CircuitState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;
use KonspyracyLabs\FilamentFuse\Support\CircuitInspector;

/**
 * The view components, rendered directly.
 *
 * These classes hold every display decision the package makes — which icon, which
 * colour, singular or plural, what to say when a value is missing. Testing them only
 * through the pages would leave that logic covered by accident rather than on purpose,
 * and a component that renders nothing at all still leaves a page that looks entirely
 * plausible.
 */
beforeEach(function (): void {
    config()->set('filament-fuse.enabled', true);
    config()->set('fuse.services', ['stripe' => []]);
    Carbon::setTestNow('2026-08-28 14:06:31');

    // What ShareErrorsFromSession does on a real panel request. Rendering a component
    // outside the middleware stack leaves @error with nothing to read, which is a gap
    // in the harness rather than something the component should defend against.
    View::share('errors', new ViewErrorBag);
});

/*
|--------------------------------------------------------------------------
| Metric
|--------------------------------------------------------------------------
*/

it('renders a metric card with its accent', function (): void {
    $html = render('<x-filament-fuse::metric label="Attempts" value="412" caption="in current window" accent="open" />');

    expect($html)
        ->toContain('fuse-metric--open')
        ->toContain('Attempts')
        ->toContain('412')
        ->toContain('in current window');
});

it('omits the caption when a metric has none', function (): void {
    expect(render('<x-filament-fuse::metric label="Attempts" value="412" />'))
        ->not->toContain('fuse-metric__caption');
});

/*
|--------------------------------------------------------------------------
| Tile
|--------------------------------------------------------------------------
*/

it('words a tile duration to match the state', function (CircuitState $state, string $key): void {
    $tile = render('<x-filament-fuse::tile :snapshot="$s" />', ['s' => snapshot(
        $state,
        openedAt: Carbon::now()->subSeconds(252),
    )]);

    expect($tile)->toContain(__("filament-fuse::filament-fuse.tile.{$key}", ['duration' => '4m 12s']));
})->with([
    'open' => [CircuitState::Open, 'open_for'],
    'half-open' => [CircuitState::HalfOpen, 'half_open_for'],
]);

it('shows no duration for a healthy circuit', function (): void {
    // This package keeps no history of its own, so a closed circuit has no "closed
    // for" instant to report — inventing one would be a fabricated number.
    expect(render('<x-filament-fuse::tile :snapshot="$s" />', ['s' => snapshot(CircuitState::Closed)]))
        ->not->toContain('fuse-tile__duration');
});

it('says a healthy circuit is working now, and a broken one when it stopped', function (): void {
    $healthy = render('<x-filament-fuse::tile :snapshot="$s" />', ['s' => snapshot(CircuitState::Closed)]);
    $broken = render('<x-filament-fuse::tile :snapshot="$s" />', [
        's' => snapshot(CircuitState::Open, openedAt: Carbon::parse('2026-08-28 14:02:19')),
    ]);

    expect($healthy)->toContain(__('filament-fuse::filament-fuse.tile.last_good_now'))
        // To the minute, matching the probe's due time beside it.
        ->and($broken)->toContain('14:02')->not->toContain('14:02:19');
});

it('says never worked when the circuit could not be read at all', function (): void {
    // Unreadable has no openedAt to fall back on either, so this is a third answer
    // distinct from "now" and from a timestamp — never say the raw translation key.
    $tile = render('<x-filament-fuse::tile :snapshot="$s" />', ['s' => CircuitSnapshot::unreadable('stripe')]);

    expect($tile)->toContain(__('filament-fuse::filament-fuse.tile.last_good_never'))
        ->not->toContain('filament-fuse::filament-fuse.tile');
});

it('counts down to the next probe', function (): void {
    $tile = render('<x-filament-fuse::tile :snapshot="$s" />', ['s' => snapshot(
        CircuitState::Open,
        recoveryAt: Carbon::now()->addSeconds(48),
    )]);

    expect($tile)->toContain(__('filament-fuse::filament-fuse.tile.next_probe_in', ['duration' => '0:48']));
});

it('keeps counters and thresholds off the tile', function (): void {
    // A tile is read from across the room. Anything that needs a caption to be
    // understood lives on the circuit's page, beside its caption.
    // Values chosen not to collide with digits inside the badge's SVG path data.
    $tile = render('<x-filament-fuse::tile :snapshot="$s" />', [
        's' => snapshot(CircuitState::Open, attempts: 1234, failures: 987, failureRate: 63.7, threshold: 45),
    ]);

    expect($tile)->not->toContain('1,234')->not->toContain('987')->not->toContain('63.7')->not->toContain('45%');
});

it('makes the whole tile the link', function (): void {
    // Not a "view" affordance inside it: there is nothing to aim at on a phone.
    $html = render('<x-filament-fuse::tile :snapshot="$s" url="/admin/fuse/circuit/stripe" />', [
        's' => snapshot(),
    ]);

    expect($html)->toStartWith('<a href="/admin/fuse/circuit/stripe"');
});

/*
|--------------------------------------------------------------------------
| Orb and live strip
|--------------------------------------------------------------------------
*/

it('renders the orb in the circuit state', function (): void {
    expect(render('<x-filament-fuse::orb :snapshot="$s" />', ['s' => snapshot(CircuitState::Open)]))
        ->toContain('fuse-orb--open')
        ->toContain('fuse-orb__ring')
        ->toContain(__('filament-fuse::filament-fuse.state.open'));
});

it('says how fresh the dashboard is and how often it refreshes', function (): void {
    // A page that looks identical whether it is polling or has quietly stopped is
    // worse than one that never claimed to be live.
    expect(render('<x-filament-fuse::live-strip poll="5s" />'))
        ->toContain(__('filament-fuse::filament-fuse.updated_at', ['time' => '14:06:31']))
        ->toContain(__('filament-fuse::filament-fuse.live.polling', ['seconds' => 5]));
});

it('omits the cadence when the dashboard is not polling', function (): void {
    expect(render('<x-filament-fuse::live-strip />'))
        ->toContain(__('filament-fuse::filament-fuse.live.live'))
        ->not->toContain(__('filament-fuse::filament-fuse.live.polling', ['seconds' => 5]));
});

/*
|--------------------------------------------------------------------------
| Topbar indicator
|--------------------------------------------------------------------------
*/

it('stays quiet in the topbar while everything is fine', function (): void {
    $html = render('<x-filament-fuse::indicator />');

    expect($html)
        ->toContain(__('filament-fuse::filament-fuse.indicator.ok'))
        ->toContain('fuse-indicator--ok')
        ->not->toContain('fuse-indicator--down');
});

it('goes loud once something is open', function (): void {
    (new CircuitBreaker('stripe'))->forceOpen();
    app(CircuitInspector::class)->flush();

    $html = render('<x-filament-fuse::indicator />');

    expect($html)
        ->toContain(__('filament-fuse::filament-fuse.indicator.down', ['count' => 1]))
        ->toContain('fuse-indicator--down');
});

it('renders nothing while the package is disabled', function (): void {
    config()->set('filament-fuse.enabled', false);

    expect(trim(render('<x-filament-fuse::indicator />')))->toBe('');
});

/*
|--------------------------------------------------------------------------
| Empty and error states
|--------------------------------------------------------------------------
*/

it('marks an empty state that is good news', function (): void {
    $good = render('<x-filament-fuse::empty-state heading="Never failed" body="Good." good />');
    $plain = render('<x-filament-fuse::empty-state heading="Never failed" body="Good." />');

    expect($good)->toContain('fuse-empty--good')
        ->and($plain)->not->toContain('fuse-empty--good');
});

it('shows no icon when none is given', function (): void {
    expect(render('<x-filament-fuse::empty-state heading="Nothing" body="Nothing here." />'))
        ->not->toContain('fuse-empty__icon');
});

it('renders the error state distinctly from the empty state', function (): void {
    expect(render('<x-filament-fuse::error-state heading="Cannot read" body="Check the cache." />'))
        ->toContain('role="alert"')
        ->toContain('fuse-error');
});

/*
|--------------------------------------------------------------------------
| Circuit hero
|--------------------------------------------------------------------------
*/

it('renders the hero with the orb, duration and the four metric cards', function (): void {
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

    expect($html)
        ->toContain('fuse-orb')
        ->toContain(__('filament-fuse::filament-fuse.metric.attempts'))
        ->toContain(__('filament-fuse::filament-fuse.metric.failures'))
        ->toContain(__('filament-fuse::filament-fuse.metric.failure_rate'))
        ->toContain(__('filament-fuse::filament-fuse.metric.min_requests'));
});

it('shows the error state instead of the hero when the cache is unreadable', function (): void {
    $html = render('<x-filament-fuse::circuit-hero :snapshot="$s" />', [
        's' => CircuitSnapshot::unreadable('stripe'),
    ]);

    expect($html)
        ->toContain(__('filament-fuse::filament-fuse.error.unavailable.heading'))
        ->not->toContain('fuse-orb-ctn');
});
