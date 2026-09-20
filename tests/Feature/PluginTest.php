<?php

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use KonspyracyLabs\FilamentFuse\Filament\Pages\CircuitDetail;
use KonspyracyLabs\FilamentFuse\Filament\Pages\Circuits;
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;

it('implements the Filament plugin contract', function (): void {
    expect(FilamentFusePlugin::make())->toBeInstanceOf(Plugin::class);
});

it('uses a stable plugin id', function (): void {
    // The id is the public handle used by FilamentFusePlugin::get() and by host
    // applications calling filament('filament-fuse'). Changing it is a breaking change.
    expect(FilamentFusePlugin::make()->getId())->toBe('filament-fuse');
});

it('resolves through the container', function (): void {
    expect(FilamentFusePlugin::make())->toBeInstanceOf(FilamentFusePlugin::class);
});

it('registers onto a panel', function (): void {
    $panel = Panel::make()
        ->id('testing')
        ->path('testing')
        ->plugin(FilamentFusePlugin::make());

    expect($panel->hasPlugin('filament-fuse'))->toBeTrue()
        ->and($panel->getPlugin('filament-fuse'))->toBeInstanceOf(FilamentFusePlugin::class);
});

it('can be retrieved from the current panel via get()', function (): void {
    $panel = Panel::make()
        ->id('testing')
        ->path('testing')
        ->plugin(FilamentFusePlugin::make());

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    expect(FilamentFusePlugin::get())->toBeInstanceOf(FilamentFusePlugin::class)
        ->and(FilamentFusePlugin::get()->getId())->toBe('filament-fuse');
});

it('registers the two pages on the panel', function (): void {
    $panel = Panel::make()->id('testing')->path('testing');

    FilamentFusePlugin::make()->register($panel);

    expect($panel->getPages())
        ->toContain(Circuits::class)
        ->toContain(CircuitDetail::class);
});

it('returns itself from every fluent setter', function (): void {
    $plugin = FilamentFusePlugin::make();

    expect($plugin->slug('ops'))->toBe($plugin)
        ->and($plugin->navigationGroup('Ops'))->toBe($plugin)
        ->and($plugin->navigationSort(1))->toBe($plugin)
        ->and($plugin->navigationIcon('heroicon-o-fire'))->toBe($plugin)
        ->and($plugin->circuitsNavigationIcon('heroicon-o-bell'))->toBe($plugin)
        ->and($plugin->indicator())->toBe($plugin)
        ->and($plugin->badge())->toBe($plugin)
        ->and($plugin->poll(5))->toBe($plugin)
        ->and($plugin->timeFormat('H:i'))->toBe($plugin)
        ->and($plugin->authorize(fn (): bool => true))->toBe($plugin);
});
