<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use KonspyracyLabs\FilamentFuse\Authorization\Ability;
use KonspyracyLabs\FilamentFuse\Authorization\Authorizer;
use KonspyracyLabs\FilamentFuse\Filament\Pages\Circuits;
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;

/**
 * One rule for who may view the dashboard and a circuit's detail page, read from every
 * page and the topbar indicator. The pages have their own tests proving they ask it.
 */
beforeEach(function (): void {
    config()->set('filament-fuse.enabled', true);
    config()->set('fuse.services', ['stripe' => []]);
});

function allows(Ability $ability): bool
{
    return app(Authorizer::class)->allows($ability);
}

/*
|--------------------------------------------------------------------------
| The default: open to any signed-in panel user
|--------------------------------------------------------------------------
*/

it('allows the dashboard to any panel user when nobody has said otherwise', function (): void {
    // TestCase::setUp() signs a user in by default.
    expect(allows(Ability::View))->toBeTrue();
});

it('denies a guest even though the package is default-open', function (): void {
    // This package is on by default and default-open, so registering the plugin exposes circuit names
    // and thresholds to whoever reaches the panel. The default-open branch must still
    // require somebody to be signed in.
    Auth::forgetGuards();

    expect(allows(Ability::View))->toBeFalse();
});

it('allows nothing while the package is disabled, whoever is asking', function (): void {
    config()->set('filament-fuse.enabled', false);
    Gate::define(Ability::View->value, fn (?object $user): bool => true);

    expect(allows(Ability::View))->toBeFalse();
});

it('lets a gate of the ability name decide instead of the default-open rule', function (): void {
    Gate::define(Ability::View->value, fn (?object $user): bool => false);

    expect(allows(Ability::View))->toBeFalse();

    Gate::define(Ability::View->value, fn (?object $user): bool => true);

    expect(allows(Ability::View))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Enforced: the Gate decides everything
|--------------------------------------------------------------------------
*/

it('denies the ability when permissions are enforced and nobody has granted it', function (): void {
    // The mode for a panel that already has roles: silence means no, as it does for
    // every other permission in that panel.
    config()->set('filament-fuse.authorization.enforce', true);

    expect(allows(Ability::View))->toBeFalse();
});

it('grants exactly what the Gate grants when permissions are enforced', function (): void {
    config()->set('filament-fuse.authorization.enforce', true);
    Gate::define(Ability::View->value, fn (?object $user): bool => true);

    expect(allows(Ability::View))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Per panel
|--------------------------------------------------------------------------
*/

it('hands the ability to a per-panel callback', function (): void {
    panelWith(FilamentFusePlugin::make()->authorize(fn (Ability $ability): bool => $ability === Ability::View));

    expect(allows(Ability::View))->toBeTrue();
});

it('lets the per-panel callback override gates and enforcement alike', function (): void {
    config()->set('filament-fuse.authorization.enforce', true);
    Gate::define(Ability::View->value, fn (?object $user): bool => false);

    panelWith(FilamentFusePlugin::make()->authorize(fn (): bool => true));

    expect(allows(Ability::View))->toBeTrue();
});

it('lets a panel authorisation override the global gate', function (): void {
    Gate::define(Ability::View->value, fn (?object $user): bool => false);

    panelWith(FilamentFusePlugin::make()->authorize(fn (): bool => true));

    expect(allows(Ability::View))->toBeTrue();
});

it('hands the signed-in user to the per-panel callback', function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));

    panelWith(FilamentFusePlugin::make()->authorize(fn (?object $user): bool => $user?->id === 7));

    expect(allows(Ability::View))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Ability itself
|--------------------------------------------------------------------------
*/

it('exposes exactly one ability, and it guards the dashboard', function (): void {
    expect(Ability::cases())->toHaveCount(1)
        ->and(Ability::View->value)->toBe('viewFilamentFuse');
});

it('is what the dashboard and the circuit page both ask', function (): void {
    expect(Circuits::canAccess())->toBeTrue();

    Auth::forgetGuards();

    expect(Circuits::canAccess())->toBeFalse();
});
