<?php

namespace KonspyracyLabs\FilamentFuse\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use KonspyracyLabs\FilamentFuse\Authorization\Ability;
use KonspyracyLabs\FilamentFuse\Authorization\Authorizer;
use KonspyracyLabs\FilamentFuse\FilamentFuse;
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;
use KonspyracyLabs\FilamentFuse\Support\CircuitInspector;
use UnitEnum;

/**
 * Shared behaviour for every page this package adds to a panel.
 *
 * Three things are settled here rather than three times over: where the pages sit in
 * the URL space, where they sit in the navigation, and who is allowed to open them.
 * All of it is read from the plugin registered on the panel, which in turn falls back
 * to config — see {@see FilamentFusePlugin::resolve()}.
 */
abstract class FusePage extends Page
{
    /**
     * The umbrella gate a host may define to restrict everything at once.
     *
     * Kept as a constant for the hosts that already reference it; the ability the
     * package actually checks is {@see Ability::View}.
     */
    public const GATE = 'viewFilamentFuse';

    /**
     * What a user must be allowed to do to open this page.
     */
    protected static function ability(): Ability
    {
        return Ability::View;
    }

    /**
     * Whether the current user may open this page.
     *
     * One rule for every page and the topbar indicator — see {@see Authorizer}.
     */
    public static function canAccess(): bool
    {
        return app(Authorizer::class)->allows(static::ability());
    }

    /**
     * Keep the pages out of the navigation until they would actually work.
     *
     * A menu item that 403s is worse than no menu item.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * The URL segment, taken from the plugin so a panel can move the whole set.
     *
     * Filament passes the panel while registering routes, and nothing otherwise; in the
     * second case the current or default panel supplies the plugin.
     */
    public static function getSlug(?Panel $panel = null): string
    {
        return static::slugWithin(static::plugin($panel)->getSlug());
    }

    /**
     * This page's path relative to the plugin's base slug.
     */
    protected static function slugWithin(string $base): string
    {
        return $base;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::plugin()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()->getNavigationSort();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::plugin()->getNavigationIcon();
    }

    /**
     * Livewire polling interval, or null to leave the page static.
     */
    public function getPollingInterval(): ?string
    {
        return static::plugin()->getPollInterval();
    }

    protected static function plugin(?Panel $panel = null): FilamentFusePlugin
    {
        return FilamentFusePlugin::resolve($panel);
    }

    protected function fuse(): FilamentFuse
    {
        return app(FilamentFuse::class);
    }

    protected function inspector(): CircuitInspector
    {
        return app(CircuitInspector::class);
    }
}
