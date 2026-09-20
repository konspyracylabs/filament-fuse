<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch. On by default — this package keeps no database of its own,
    | so there is nothing to install before it is safe to be on. While this is
    | false the package adds nothing to your panel at all.
    |
    */

    'enabled' => env('FILAMENT_FUSE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Circuit
    |--------------------------------------------------------------------------
    |
    | This package monitors exactly one circuit from the 'services' array in
    | config/fuse.php, read live. Name it here; leave it blank and the first
    | circuit declared there is used instead, so a dashboard that was working
    | does not go blank because somebody renamed a service.
    |
    |     // config/fuse.php
    |     'services' => [
    |         'stripe' => [],
    |     ],
    |
    | `php artisan filament-fuse:install` asks which circuit to monitor and
    | writes this for you. Circuits created at runtime, such as `webhook:42`,
    | cannot appear in that array and are out of scope for this package.
    |
    */

    'circuit' => env('FILAMENT_FUSE_CIRCUIT'),

    /*
    |--------------------------------------------------------------------------
    | Per-panel overrides
    |--------------------------------------------------------------------------
    |
    | Everything under 'slug', 'navigation' and 'dashboard' is a default. Each
    | can be overridden for one panel on the plugin itself:
    |
    |     FilamentFusePlugin::make()
    |         ->slug('circuits')
    |         ->navigationGroup('Ops')
    |         ->poll(10)
    |
    | Set nothing on the plugin and these values apply to every panel.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Slug
    |--------------------------------------------------------------------------
    |
    | The URL segment the dashboard lives at, inside the panel's own path. The
    | circuit detail page nests under it: {slug}/circuit/{service}.
    |
    */

    'slug' => 'fuse',

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | 'poll' is how often the dashboard refreshes, in seconds. Each tick reads
    | Fuse's cache for the monitored circuit — Fuse's own getStats() is a
    | handful of reads, not one — so the right number depends on your cache
    | driver and on how many people leave the tab open. Set it to 0 to stop
    | polling and refresh by hand.
    |
    | 'timezone' is the zone every timestamp this package renders is shown in.
    | Left null it takes the panel's timezone (FilamentTimezone), then the
    | application's, and finally UTC — an application with no timezone
    | configured gets UTC rather than an error.
    |
    */

    'dashboard' => [
        'poll' => env('FILAMENT_FUSE_POLL', 5),
        'timezone' => env('FILAMENT_FUSE_TIMEZONE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Where this package's pages appear in the Filament panel.
    |
    | 'group' null uses the package's translated default ("Monitoring" in
    | English). A string or enum names a group of your own; false leaves the
    | pages ungrouped.
    |
    | 'indicator' renders a small status element in the panel topbar, visible on
    | every page. 'badge' puts the count of circuits currently down on the
    | navigation item instead. They are independent — run both, one, or neither.
    |
    */

    'navigation' => [
        'group' => null,
        'sort' => null,
        'icon' => 'heroicon-o-bolt',
        'circuits_icon' => 'heroicon-o-cpu-chip',
        'indicator' => true,
        'badge' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | The package asks one question of the Gate: viewFilamentFuse. With
    | 'enforce' false (the default) any authenticated panel user is allowed to
    | see the dashboard unless a gate of that name says otherwise. With
    | 'enforce' true the Gate decides everything and an ability that has not
    | been granted is denied — the setting for a panel that already has roles.
    | Create a permission named viewFilamentFuse in spatie/laravel-permission
    | (or any package that registers permissions as gate abilities), assign it
    | to roles, and nothing else needs wiring.
    |
    | A per-panel ->authorize() callback on the plugin overrides all of this for
    | that panel.
    |
    */

    'authorization' => [
        'enforce' => env('FILAMENT_FUSE_ENFORCE_PERMISSIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing circuit
    |--------------------------------------------------------------------------
    |
    | A circuit breaker this package owns, for rehearsing outages. Switch it on
    | here and the package registers it with Fuse for you — there is nothing to
    | add to config/fuse.php, because a circuit that exists only to be broken is
    | a panel concern and has no business in the circuit breaker's own registry.
    |
    | Once on, it appears on the dashboard like any other circuit and
    | `php artisan filament-fuse:drill` opens it for real: Fuse transitions, the
    | tile turns red and moves into the Open section, the topbar indicator
    | counts it. That is the point — the only useful answer to "does my
    | dashboard actually show an outage" is that it did.
    |
    | Nothing routes a job through it, so opening it holds no work and affects
    | nobody. That is also why a drill can only ever open this one and has no
    | flag to aim it elsewhere: opening a circuit that carries traffic is not a
    | test, it is an outage you caused.
    |
    | 'name' is what it is called on the dashboard.
    |
    | 'duration' is how long a drill holds it open, in seconds.
    |
    | 'schedule' closes finished drills from the scheduler, once a minute. A
    | drill records when it should end, so nothing is left open if the process
    | that started it dies; without the schedule you close it yourself with
    | `php artisan filament-fuse:drill --stop`.
    |
    | 'production' allows a drill to start while APP_ENV is production. It is
    | off by default: a drill opens a real circuit and anyone watching the
    | dashboard will read it as an outage, so in production it has to be a
    | decision somebody made. Stopping the package's own testing circuit is
    | never refused, in any environment — but if this name belongs to a
    | circuit you declared yourself, --stop and --tick refuse to touch it
    | instead.
    |
    | `php artisan config:cache` freezes this circuit, and the switch that
    | registers it, into the cached config('fuse.services') along with
    | everything else here — changing 'enabled' or 'name' afterwards does
    | nothing until you re-cache. The name this package last registered under
    | is recorded alongside it, so that a cached config carrying this
    | package's own entry from an earlier boot is never mistaken for a
    | circuit you declared.
    | That record is the package's own; do not set it yourself.
    |
    | Do not rename the testing circuit while a drill is running: the drill
    | is remembered by one deadline, not by name, so --stop would close the
    | new name and leave the old breaker open. If that happens, close it with
    | `php artisan fuse:close {old name}`.
    |
    */

    'testing_circuit' => [
        'enabled' => env('FILAMENT_FUSE_TESTING_CIRCUIT', false),
        'name' => env('FILAMENT_FUSE_TESTING_CIRCUIT_NAME', 'testing-circuit-breaker'),
        'duration' => env('FILAMENT_FUSE_TESTING_CIRCUIT_DURATION', 300),
        'schedule' => env('FILAMENT_FUSE_TESTING_CIRCUIT_SCHEDULE', false),
        'production' => env('FILAMENT_FUSE_TESTING_CIRCUIT_PRODUCTION', false),
    ],

];
