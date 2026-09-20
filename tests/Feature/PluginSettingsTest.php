<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Harris21\Fuse\CircuitBreaker;
use Harris21\Fuse\Enums\CircuitState;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use KonspyracyLabs\FilamentFuse\Filament\Pages\CircuitDetail;
use KonspyracyLabs\FilamentFuse\Filament\Pages\Circuits;
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;

/**
 * Per-panel settings on the plugin, and their defaults in config.
 *
 * The rule under test is a simple one — plugin first, config second — but it is read
 * from a dozen places: route slugs, navigation, polling, timestamp formats,
 * authorisation. Each test here pins one of those places to the rule, so a page that
 * quietly goes back to reading config directly fails a test rather than a user.
 */
beforeEach(function (): void {
    config()->set('filament-fuse.enabled', true);
    config()->set('fuse.services', ['stripe' => []]);
    Carbon::setTestNow('2026-08-28 14:06:31');
    View::share('errors', new ViewErrorBag);
});

/*
|--------------------------------------------------------------------------
| Resolution
|--------------------------------------------------------------------------
*/

it('falls back to config when nothing is set on the plugin', function (): void {
    config()->set('filament-fuse.slug', 'breakers');
    config()->set('filament-fuse.dashboard.poll', 9);
    config()->set('filament-fuse.navigation.indicator', false);

    $plugin = FilamentFusePlugin::make();

    expect($plugin->getSlug())->toBe('breakers')
        ->and($plugin->getPollSeconds())->toBe(9)
        ->and($plugin->getPollInterval())->toBe('9s')
        ->and($plugin->hasIndicator())->toBeFalse()
        ->and($plugin->hasBadge())->toBeTrue();
});

it('defaults the time format to H:i:s, with no config key behind it', function (): void {
    // Unlike the other settings in this file, there is no filament-fuse.dashboard
    // key for this one to fall back to — this package's config is cut to the bare
    // minimum, and a time format nobody publishes is not worth a key. The setter is
    // the only way to change it.
    expect(FilamentFusePlugin::make()->getTimeFormat())->toBe('H:i:s');
});

it('prefers a setting made on the plugin over the config default', function (): void {
    config()->set('filament-fuse.slug', 'breakers');
    config()->set('filament-fuse.dashboard.poll', 9);

    $plugin = FilamentFusePlugin::make()
        ->slug('/ops/')
        ->poll(30)
        ->timeFormat('H:i')
        ->navigationSort(7)
        ->navigationIcon('heroicon-o-fire')
        ->circuitsNavigationIcon('heroicon-o-bell')
        ->indicator(false)
        ->badge(false);

    expect($plugin->getSlug())->toBe('ops')
        ->and($plugin->getPollSeconds())->toBe(30)
        ->and($plugin->getTimeFormat())->toBe('H:i')
        ->and($plugin->getNavigationSort())->toBe(7)
        ->and($plugin->getNavigationIcon())->toBe('heroicon-o-fire')
        ->and($plugin->getCircuitsNavigationIcon())->toBe('heroicon-o-bell')
        ->and($plugin->hasIndicator())->toBeFalse()
        ->and($plugin->hasBadge())->toBeFalse();
});

it('uses the documented default when config holds something that is not a number', function (): void {
    // A blind (int) cast turned FILAMENT_FUSE_POLL=abc into 0 — polling silently off.
    config()->set('filament-fuse.dashboard.poll', 'abc');

    expect(FilamentFusePlugin::make()->getPollSeconds())->toBe(5);
});

it('still honours a numeric string from the environment', function (): void {
    // .env values arrive as strings; "10" must mean ten, not the default.
    config()->set('filament-fuse.dashboard.poll', '10');

    expect(FilamentFusePlugin::make()->getPollSeconds())->toBe(10);
});

it('stops polling at zero rather than going negative', function (): void {
    expect(FilamentFusePlugin::make()->poll(0)->getPollInterval())->toBeNull()
        ->and(FilamentFusePlugin::make()->poll(-5)->getPollInterval())->toBeNull();
});

it('resolves the plugin registered on the current panel', function (): void {
    panelWith(FilamentFusePlugin::make()->slug('ops'));

    expect(FilamentFusePlugin::resolve()->getSlug())->toBe('ops');
});

it('resolves the plugin for the panel it is handed, current or not', function (): void {
    // Filament passes the panel while registering routes. Two panels mounting the
    // package at different slugs must each get their own.
    $other = Panel::make()->id('other')->path('other')->plugin(FilamentFusePlugin::make()->slug('other'));

    expect(Circuits::getSlug($other))->toBe('other')
        ->and(Circuits::getSlug())->toBe('fuse');
});

it('falls back to config when no panel is registered at all', function (): void {
    // Artisan commands and queue workers run with no current panel and, in an app that
    // has not registered one, no default either. Settings must still resolve.
    config()->set('filament-fuse.slug', 'breakers');

    filament()->setCurrentPanel(null);
    app(PanelRegistry::class)->panels = [];

    expect(FilamentFusePlugin::resolve()->getSlug())->toBe('breakers');
});

it('falls back to config on a panel that has no plugin', function (): void {
    config()->set('filament-fuse.slug', 'breakers');

    $bare = Panel::make()->id('bare')->path('bare');
    Filament::registerPanel($bare);
    Filament::setCurrentPanel($bare);

    expect(FilamentFusePlugin::resolve()->getSlug())->toBe('breakers');
});

/*
|--------------------------------------------------------------------------
| Routing and navigation
|--------------------------------------------------------------------------
*/

it('nests the detail page under the plugin slug', function (): void {
    panelWith(FilamentFusePlugin::make()->slug('ops'));

    expect(Circuits::getSlug())->toBe('ops')
        ->and(CircuitDetail::getSlug())->toBe('ops/circuit');
});

it('uses the translated group name until told otherwise', function (): void {
    expect(Circuits::getNavigationGroup())->toBe(__('filament-fuse::filament-fuse.nav.group'));

    config()->set('filament-fuse.navigation.group', 'Ops');
    expect(Circuits::getNavigationGroup())->toBe('Ops');

    panelWith(FilamentFusePlugin::make()->navigationGroup('Reliability'));
    expect(Circuits::getNavigationGroup())->toBe('Reliability');
});

it('leaves the pages ungrouped when asked', function (): void {
    expect(FilamentFusePlugin::make()->navigationGroup(false)->getNavigationGroup())->toBeNull();

    config()->set('filament-fuse.navigation.group', false);
    expect(FilamentFusePlugin::make()->getNavigationGroup())->toBeNull();
});

it('takes the navigation icons from the plugin', function (): void {
    $plugin = FilamentFusePlugin::make()->navigationIcon('heroicon-o-fire')->circuitsNavigationIcon('heroicon-o-bell');
    panelWith($plugin);

    expect(Circuits::getNavigationIcon())->toBe('heroicon-o-fire')
        ->and($plugin->getCircuitsNavigationIcon())->toBe('heroicon-o-bell');
});

it('hides the navigation badge when the plugin switches it off', function (): void {
    (new CircuitBreaker('stripe'))->forceOpen();

    expect(Circuits::getNavigationBadge())->toBe('1');

    panelWith(FilamentFusePlugin::make()->badge(false));

    expect(Circuits::getNavigationBadge())->toBeNull();
});

it('renders no topbar indicator when the plugin switches it off', function (): void {
    expect(render('<x-filament-fuse::indicator />'))->toContain('fuse-indicator');

    panelWith(FilamentFusePlugin::make()->indicator(false));

    expect(trim(render('<x-filament-fuse::indicator />')))->toBe('');
});

/*
|--------------------------------------------------------------------------
| Authorisation
|--------------------------------------------------------------------------
*/

it('lets a panel decide who may open the pages', function (): void {
    panelWith(FilamentFusePlugin::make()->authorize(fn (): bool => false));
    expect(Circuits::canAccess())->toBeFalse()
        ->and(Circuits::shouldRegisterNavigation())->toBeFalse();

    panelWith(FilamentFusePlugin::make()->authorize(fn (): bool => true));
    expect(Circuits::canAccess())->toBeTrue();
});

it('hands the panel user to the authorisation callback', function (): void {
    $user = new GenericUser(['id' => 7]);
    $this->actingAs($user, 'web');

    panelWith(FilamentFusePlugin::make()->authorize(fn (Authenticatable $user): bool => $user->getAuthIdentifier() === 7));
    expect(Circuits::canAccess())->toBeTrue();

    panelWith(FilamentFusePlugin::make()->authorize(fn (Authenticatable $user): bool => $user->getAuthIdentifier() === 8));
    expect(Circuits::canAccess())->toBeFalse();
});

it('renders the topbar indicator through the panel render hook', function (): void {
    // boot() is where the hook is registered. Rendering the hook is what a real panel
    // page does at TOPBAR_END, and it must produce the component — not the bare template.
    $panel = Filament::getDefaultPanel();

    FilamentFusePlugin::make()->boot($panel);
    Filament::setCurrentPanel($panel);

    expect((string) FilamentView::renderHook(PanelsRenderHook::TOPBAR_END))
        ->toContain('fuse-indicator')
        ->toContain(__('filament-fuse::filament-fuse.indicator.ok'));
});

it('renders no indicator for a panel other than the one that registered the hook', function (): void {
    // The hook itself carries no panel scope. Without the guard in boot(), a second
    // panel with no plugin of its own would render this one's indicator and 500 trying
    // to resolve a plugin that was never registered on it.
    $withPlugin = Filament::getDefaultPanel();
    FilamentFusePlugin::make()->boot($withPlugin);

    $other = Panel::make()->id('plainer')->path('plainer');
    Filament::registerPanel($other);
    Filament::setCurrentPanel($other);

    expect((string) FilamentView::renderHook(PanelsRenderHook::TOPBAR_END))->toBe('');
});

it('lets a panel authorisation override the global gate', function (): void {
    Gate::define(Circuits::GATE, fn (?object $user): bool => false);

    panelWith(FilamentFusePlugin::make()->authorize(fn (): bool => true));

    expect(Circuits::canAccess())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

it('polls at the interval the plugin sets', function (): void {
    panelWith(FilamentFusePlugin::make()->poll(30));

    expect((new Circuits)->getPollingInterval())->toBe('30s');
});

/*
|--------------------------------------------------------------------------
| Timestamps
|--------------------------------------------------------------------------
*/

it('renders times in the panel timezone', function (): void {
    // Stored in UTC, shown to an operator in Athens: three hours later in August.
    FilamentTimezone::set('Europe/Athens');

    $tile = render('<x-filament-fuse::tile :snapshot="$s" />', [
        's' => snapshot(CircuitState::Open, openedAt: Carbon::parse('2026-08-28 14:02:19', 'UTC')),
    ]);

    expect($tile)->toContain('17:02')->not->toContain('14:02');
});

it('formats the live strip clock the way the plugin says', function (): void {
    panelWith(FilamentFusePlugin::make()->timeFormat('H.i'));

    expect(render('<x-filament-fuse::live-strip />'))
        ->toContain(__('filament-fuse::filament-fuse.updated_at', ['time' => '14.06']));
});

it('reads the panel timezone from the plugin before Filament\'s', function (): void {
    // The leg Filament cannot do at all: one panel on a different clock from the rest of
    // the application, set where the rest of this package's settings are set.
    panelWith(FilamentFusePlugin::make()->timezone('Europe/Athens'));

    $tile = render('<x-filament-fuse::tile :snapshot="$s" />', [
        's' => snapshot(CircuitState::Open, openedAt: Carbon::parse('2026-08-28 14:02:19', 'UTC')),
    ]);

    expect($tile)->toContain('17:02')->not->toContain('14:02');
});

it('prefers the plugin timezone to the one Filament was given', function (): void {
    FilamentTimezone::set('Europe/Athens');

    expect(FilamentFusePlugin::make()->timezone('Asia/Tokyo')->getTimezone())->toBe('Asia/Tokyo');
});

it('falls back to UTC when the application has no timezone', function (): void {
    // A monitoring panel that throws instead of drawing is worse than one showing the
    // right instant under the wrong label. Filament hands a null zone straight to Carbon.
    config()->set('app.timezone');
    config()->set('filament-fuse.dashboard.timezone');
    FilamentTimezone::set(null);

    expect(FilamentFusePlugin::make()->getTimezone())->toBe('UTC');

    $tile = render('<x-filament-fuse::tile :snapshot="$s" />', [
        's' => snapshot(CircuitState::Open, openedAt: Carbon::parse('2026-08-28 14:02:19', 'UTC')),
    ]);

    expect($tile)->toContain('14:02');
});

it('ignores a timezone it cannot use', function (): void {
    // A typo in an env file must not take the panel down.
    config()->set('app.timezone', 'Europe/Athens');
    config()->set('filament-fuse.dashboard.timezone', 'Mars/Olympus');

    expect(FilamentFusePlugin::make()->getTimezone())->toBe('Europe/Athens');
});

it('does not shift the snapshot timestamp while formatting it', function (): void {
    // Carbon's setTimezone() mutates. Rendering a tile must not move openedAt under
    // everything else reading the same snapshot in the same request.
    FilamentTimezone::set('Europe/Athens');

    $snapshot = snapshot(CircuitState::Open, openedAt: Carbon::parse('2026-08-28 14:02:19', 'UTC'));

    render('<x-filament-fuse::tile :snapshot="$s" />', ['s' => $snapshot]);

    expect($snapshot->openedAt?->timezoneName)->toBe('UTC')
        ->and($snapshot->openedAt?->format('H:i:s'))->toBe('14:02:19');
});

it('keeps the tile facts to the minute whatever the time format', function (): void {
    panelWith(FilamentFusePlugin::make()->timeFormat('H:i:s'));

    expect(render('<x-filament-fuse::tile :snapshot="$s" />', [
        's' => snapshot(CircuitState::Open, openedAt: Carbon::parse('2026-08-28 14:02:19')),
    ]))->toContain('14:02')->not->toContain('14:02:19');
});
