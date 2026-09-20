<?php

namespace KonspyracyLabs\FilamentFuse\Filament\Pages;

use Filament\Panel;
use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;
use Livewire\Attributes\Locked;

/**
 * One circuit in detail: its live state and its four metric cards.
 *
 * Reached from a dashboard tile or from the Circuits entry in the navigation.
 */
class CircuitDetail extends FusePage
{
    protected string $view = 'filament-fuse::pages.circuit';

    /**
     * The circuit being viewed, set once from the route in mount(). #[Locked] is what
     * actually stops user input from reaching it — mount()'s isMonitored() guard on its
     * own only runs once, and without this a client `$set('service', …)` could rewrite
     * it afterwards to any string, monitored or not.
     */
    #[Locked]
    public string $service = '';

    /**
     * Never appears in the navigation on its own.
     *
     * The plugin registers one child item per circuit under "Circuits" instead, which
     * is the only way a page needing a service name can be linked from a menu.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static function slugWithin(string $base): string
    {
        return "{$base}/circuit";
    }

    /**
     * Accept the circuit name as a path segment.
     */
    public static function getRoutePath(Panel $panel): string
    {
        return '/'.static::getSlug($panel).'/{service}';
    }

    /**
     * Reject any name that is not in the registry.
     *
     * The route parameter is attacker-controlled, and every downstream consumer — the
     * cache key Fuse builds — takes it as a string. Refusing anything not already
     * declared in config keeps that surface closed.
     */
    public function mount(string $service): void
    {
        abort_unless($this->fuse()->isMonitored($service), 404);

        $this->service = $service;
    }

    public function getTitle(): string
    {
        return __('filament-fuse::filament-fuse.pages.circuit.title', ['service' => $this->service]);
    }

    /**
     * Everything the template renders, resolved here rather than in Blade.
     *
     * @return array{snapshot: CircuitSnapshot, poll: string|null}
     */
    protected function getViewData(): array
    {
        return [
            'snapshot' => $this->getSnapshot(),
            'poll' => $this->getPollingInterval(),
        ];
    }

    public function getSnapshot(): CircuitSnapshot
    {
        return $this->inspector()->snapshot($this->service);
    }
}
