<?php

use Filament\Navigation\NavigationItem;
use Harris21\Fuse\CircuitBreaker;
use KonspyracyLabs\FilamentFuse\Filament\Pages\CircuitDetail;
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;
use KonspyracyLabs\FilamentFuse\Support\CircuitInspector;

/**
 * The collapsible "Circuits" navigation group and its contents: one item for the
 * chosen circuit, plus one for the testing circuit when it is on.
 */
beforeEach(function (): void {
    config()->set('filament-fuse.enabled', true);
    config()->set('fuse.services', ['stripe' => [], 'sendgrid' => []]);
});

it('registers one item for the chosen circuit', function (): void {
    $items = FilamentFusePlugin::make()->circuitNavigationItems();

    expect(array_map(fn (NavigationItem $item): string => $item->getLabel(), $items))->toBe(['stripe'])
        ->and($items[0]->getGroup())->toBe(__('filament-fuse::filament-fuse.nav.circuits_parent'))
        ->and($items[0]->getUrl())->toBe(CircuitDetail::getUrl(['service' => 'stripe']))
        ->and($items[0]->getSort())->toBe(0);
});

it('adds the testing circuit as a second item once it is on', function (): void {
    config()->set('filament-fuse.testing_circuit.enabled', true);

    $items = FilamentFusePlugin::make()->circuitNavigationItems();

    expect(array_map(fn (NavigationItem $item): string => $item->getLabel(), $items))
        ->toBe(['stripe', 'testing-circuit-breaker']);
});

it('registers nothing when no circuits are declared and the testing circuit is off', function (): void {
    config()->set('fuse.services', []);

    expect(FilamentFusePlugin::make()->circuitNavigationItems())->toBe([]);
});

it('puts the circuits in a collapsible group carrying the plugin icon', function (): void {
    $group = FilamentFusePlugin::make()->circuitsNavigationIcon('heroicon-o-fire')->circuitsNavigationGroup();

    expect($group->getLabel())->toBe(__('filament-fuse::filament-fuse.nav.circuits_parent'))
        ->and($group->getIcon())->toBe('heroicon-o-fire')
        ->and($group->isCollapsible())->toBeTrue();
});

it('marks a circuit that is down with a red dot', function (): void {
    $items = FilamentFusePlugin::make()->circuitNavigationItems();

    expect($items[0]->getBadge())->toBeNull();

    (new CircuitBreaker('stripe'))->forceOpen();
    app()->forgetInstance(CircuitInspector::class);

    expect($items[0]->getBadge())->not->toBeNull()
        ->and($items[0]->getBadgeColor())->toBe('danger');
});

it('hides the items from anyone who may not view the monitor', function (): void {
    $items = FilamentFusePlugin::make()->circuitNavigationItems();

    expect($items[0]->isVisible())->toBeTrue();

    config()->set('filament-fuse.enabled', false);

    expect($items[0]->isVisible())->toBeFalse();
});

it('is registered on the panel alongside the pages', function (): void {
    $panel = panelWith(FilamentFusePlugin::make());

    $labels = array_map(fn (NavigationItem $item): string => $item->getLabel(), $panel->getNavigationItems());

    expect($labels)->toContain('stripe');
});
