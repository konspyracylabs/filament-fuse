<?php

use Illuminate\Support\ServiceProvider;
use KonspyracyLabs\FilamentFuse\FilamentFuseServiceProvider;

it('registers the service provider', function (): void {
    expect(app()->getLoadedProviders())
        ->toHaveKey(FilamentFuseServiceProvider::class);
});

it('exposes the package name used for config, views and publishing', function (): void {
    expect(FilamentFuseServiceProvider::$name)->toBe('filament-fuse')
        ->and(FilamentFuseServiceProvider::$viewNamespace)->toBe('filament-fuse');
});

it('merges the package config', function (): void {
    expect(config('filament-fuse'))->toBeArray();
});

it('exposes the navigation config with documented defaults', function (): void {
    // Null group means "the translated default", so the label follows the locale
    // rather than being pinned to English in a config file nobody translates.
    expect(config('filament-fuse.navigation.group'))->toBeNull()
        ->and(config('filament-fuse.navigation.sort'))->toBeNull()
        ->and(config('filament-fuse.slug'))->toBe('fuse');
});

it('is on by default, with nothing to install first', function (): void {
    // This package keeps no database of its own, so there is nothing to run before it
    // is safe to be on.
    expect(config('filament-fuse.enabled'))->toBeTrue();
});

it('registers one local stylesheet and makes no third-party request', function (): void {
    // Nothing remote, ever: a request to somebody else's server tells them who is looking
    // at the panel, and has to be allowed by hand behind a strict CSP.
    $assets = (new FilamentFuseServiceProvider(app()))->assets();

    expect($assets)->toHaveCount(1)
        ->and($assets[0]->getId())->toBe('filament-fuse')
        ->and($assets[0]->isRemote())->toBeFalse();
});

it('ships no @import in the stylesheet itself', function (): void {
    // An @import would be a request to somebody else's server made from inside the
    // stylesheet, where no host would think to look for one.
    expect(file_get_contents(__DIR__.'/../../resources/dist/filament-fuse.css'))
        ->not->toContain('@import');
});

it('publishes the config file under a predictable tag', function (): void {
    $paths = collect(ServiceProvider::pathsToPublish(FilamentFuseServiceProvider::class))
        ->keys()
        ->map(fn (string $path): string => basename($path));

    expect($paths)->toContain('filament-fuse.php');
});

it('boots without a Filament panel present', function (): void {
    // The service provider must not assume a panel exists — a host app may install
    // the package before registering the plugin on any panel.
    expect(app()->isBooted())->toBeTrue();
});

it('publishes the config, translations and views tags the README documents', function (): void {
    foreach (['filament-fuse-config', 'filament-fuse-translations', 'filament-fuse-views'] as $tag) {
        expect(ServiceProvider::pathsToPublish(FilamentFuseServiceProvider::class, $tag))->not->toBe([]);
    }
});

it('publishes no migrations, because it has none', function (): void {
    // NO database, NO migrations: nothing under a migrations tag, and no migrations
    // directory in the package at all.
    expect(ServiceProvider::pathsToPublish(FilamentFuseServiceProvider::class, 'filament-fuse-migrations'))
        ->toBe([])
        ->and(is_dir(__DIR__.'/../../database/migrations'))->toBeFalse();
});

it('declines to register the testing circuit over a name the host already declared', function (): void {
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.enabled', true);
    config()->set('filament-fuse.testing_circuit.name', 'stripe');

    (new FilamentFuseServiceProvider(app()))->registerTestingCircuit();

    // stripe's own declaration must come back exactly as the host wrote it — not
    // replaced with the testing circuit's threshold, min_requests and timeout.
    expect(config('fuse.services.stripe'))->toBe([]);
});

it('registers the testing circuit under a name nothing else has claimed', function (): void {
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.enabled', true);
    config()->set('filament-fuse.testing_circuit.name', 'testing-circuit-breaker');

    (new FilamentFuseServiceProvider(app()))->registerTestingCircuit();

    expect(config('fuse.services'))->toHaveKey('testing-circuit-breaker');
});

it('merges filament-fuse config with exactly the documented key set', function (): void {
    expect(array_keys(config('filament-fuse')))->toBe([
        'enabled',
        'circuit',
        'slug',
        'dashboard',
        'navigation',
        'authorization',
        'testing_circuit',
    ]);
});
