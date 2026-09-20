<?php

namespace KonspyracyLabs\FilamentFuse;

use BackedEnum;
use Closure;
use DateTimeZone;
use Filament\Contracts\Plugin;
use Filament\Exceptions\NoDefaultPanelSetException;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;
use KonspyracyLabs\FilamentFuse\Authorization\Ability;
use KonspyracyLabs\FilamentFuse\Filament\Pages\CircuitDetail;
use KonspyracyLabs\FilamentFuse\Filament\Pages\Circuits;
use KonspyracyLabs\FilamentFuse\Support\CircuitInspector;
use KonspyracyLabs\FilamentFuse\Support\Coerce;
use Throwable;
use UnitEnum;

/**
 * The plugin, and the per-panel settings that ride on it.
 *
 * Every setting here has a default in config/filament-fuse.php. Set nothing on the
 * plugin and the config applies to every panel; set something and it applies to this
 * panel only — so an admin panel and an ops panel can mount the same package at
 * different slugs, with different polling and different navigation, without either
 * knowing the other exists.
 *
 * Resolution is always plugin first, config second, and the getters below are the one
 * place that rule lives. Nothing else in the package reads these config keys.
 */
class FilamentFusePlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'filament-fuse';

    protected ?string $slug = null;

    protected string|UnitEnum|false|null $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected string|BackedEnum|null $navigationIcon = null;

    protected string|BackedEnum|null $circuitsNavigationIcon = null;

    protected ?bool $indicator = null;

    protected ?bool $badge = null;

    protected ?int $poll = null;

    protected ?string $timeFormat = null;

    protected ?string $timezone = null;

    protected ?Closure $authorize = null;

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Add the pages to the panel.
     *
     * Registered unconditionally. Whether they are reachable or appear in the
     * navigation is decided per-request by FusePage, which can see the config and the
     * current user; registration happens once at boot, when neither is settled.
     */
    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                Circuits::class,
                CircuitDetail::class,
            ])
            ->navigationGroups([$this->circuitsNavigationGroup()])
            ->navigationItems($this->circuitNavigationItems());
    }

    /**
     * The collapsible "Circuits" group the per-circuit items live in.
     *
     * A group rather than a parent item with children: Filament only shows child items
     * while the parent is active, and cannot collapse them. A group collapses, and
     * remembers that it was collapsed.
     */
    public function circuitsNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::make(fn (): string => __('filament-fuse::filament-fuse.nav.circuits_parent'))
            ->icon(fn (): string|BackedEnum => $this->getCircuitsNavigationIcon())
            ->collapsible();
    }

    /**
     * One navigation item per monitored circuit, in the Circuits group.
     *
     * The registry is read when the panel is registered, so a circuit added to
     * config/fuse.php appears in the navigation from the next request — everything else
     * about it is read live. An item carries a red dot while its circuit is down.
     *
     * @return list<NavigationItem>
     */
    public function circuitNavigationItems(): array
    {
        $items = [];

        foreach (app(FilamentFuse::class)->circuits() as $index => $service) {
            $items[] = NavigationItem::make("filament-fuse-circuit-{$service}")
                ->label($service)
                ->group(fn (): string => __('filament-fuse::filament-fuse.nav.circuits_parent'))
                ->sort($index)
                ->url(fn (): string => CircuitDetail::getUrl(['service' => $service]))
                ->isActiveWhen(fn (): bool => request()->url() === CircuitDetail::getUrl(['service' => $service]))
                ->visible(fn (): bool => Circuits::canAccess())
                ->badge(fn (): ?string => app(CircuitInspector::class)->isDown($service) ? '●' : null, 'danger');
        }

        return $items;
    }

    /**
     * Put the status indicator in the panel topbar.
     *
     * The view decides whether to render anything, so a host that has switched the
     * indicator off — or has not enabled the package at all — gets an empty string
     * rather than a hidden element taking up layout.
     *
     * The hook itself has no panel scope, so a second panel with no plugin of its own
     * would otherwise render this one's indicator — reading a plugin that never
     * registered on it and 500ing the panel it does not belong to. The panel is
     * captured here and checked against the one currently rendering.
     */
    public function boot(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_END,
            // Rendered as the component rather than as its template, so the Indicator
            // class runs and decides what — if anything — there is to show. Pointing a
            // render hook straight at the view skips the class entirely and leaves the
            // template with none of the variables it expects.
            static fn (): string => Filament::getCurrentPanel() === $panel
                ? Blade::render('<x-filament-fuse::indicator />')
                : '',
        );
    }

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The plugin instance registered on the current panel.
     *
     * Throws when the plugin is not on that panel — use this where its absence is a
     * programming error, and {@see self::resolve()} everywhere else.
     */
    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * The settings that apply right now.
     *
     * The plugin registered on the given panel — or, with no panel given, on the
     * current or default one. When there is no such plugin, an unconfigured instance
     * is returned instead, whose getters all fall through to config. Callers therefore
     * never branch on "is there a panel": they ask for a value and get one.
     */
    public static function resolve(?Panel $panel = null): static
    {
        try {
            $panel ??= filament()->getCurrentOrDefaultPanel();
        } catch (NoDefaultPanelSetException) {
            $panel = null;
        }

        $id = app(static::class)->getId();

        if ($panel instanceof Panel && $panel->hasPlugin($id)) {
            /** @var static $plugin */
            $plugin = $panel->getPlugin($id);

            return $plugin;
        }

        return static::make();
    }

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */

    /**
     * The URL segment the dashboard lives at. The detail page nests under it:
     * `{slug}/circuit/{service}`.
     */
    public function slug(string $slug): static
    {
        $this->slug = trim($slug, '/');

        return $this;
    }

    public function getSlug(): string
    {
        $slug = $this->slug ?? config('filament-fuse.slug', 'fuse');

        return is_string($slug) && trim($slug, '/') !== '' ? trim($slug, '/') : 'fuse';
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    /**
     * Pass false to leave the pages ungrouped.
     */
    public function navigationGroup(string|UnitEnum|false|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        $group = $this->navigationGroup ?? config('filament-fuse.navigation.group');

        if ($group === false || $group === '') {
            return null;
        }

        if ($group instanceof UnitEnum || is_string($group)) {
            return $group;
        }

        // Null means "use the package default", which lives in the translation file
        // rather than in config so it follows the panel's locale.
        return __('filament-fuse::filament-fuse.nav.group');
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        $sort = $this->navigationSort ?? config('filament-fuse.navigation.sort');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public function navigationIcon(string|BackedEnum $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string|BackedEnum
    {
        return $this->navigationIcon
            ?? $this->iconFromConfig('filament-fuse.navigation.icon', 'heroicon-o-bolt');
    }

    public function circuitsNavigationIcon(string|BackedEnum $icon): static
    {
        $this->circuitsNavigationIcon = $icon;

        return $this;
    }

    public function getCircuitsNavigationIcon(): string|BackedEnum
    {
        return $this->circuitsNavigationIcon
            ?? $this->iconFromConfig('filament-fuse.navigation.circuits_icon', 'heroicon-o-cpu-chip');
    }

    /**
     * The small status element in the panel topbar.
     */
    public function indicator(bool $condition = true): static
    {
        $this->indicator = $condition;

        return $this;
    }

    public function hasIndicator(): bool
    {
        return $this->indicator ?? (bool) config('filament-fuse.navigation.indicator', true);
    }

    /**
     * The count of circuits currently down, on the navigation item.
     */
    public function badge(bool $condition = true): static
    {
        $this->badge = $condition;

        return $this;
    }

    public function hasBadge(): bool
    {
        return $this->badge ?? (bool) config('filament-fuse.navigation.badge', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    /**
     * Seconds between refreshes. Zero stops polling.
     */
    public function poll(int $seconds): static
    {
        $this->poll = max(0, $seconds);

        return $this;
    }

    public function getPollSeconds(): int
    {
        return max(0, Coerce::int($this->poll ?? config('filament-fuse.dashboard.poll'), 5));
    }

    /**
     * The Livewire interval string, or null when not polling.
     */
    public function getPollInterval(): ?string
    {
        $seconds = $this->getPollSeconds();

        return $seconds > 0 ? "{$seconds}s" : null;
    }

    /**
     * PHP date format for time-only values — "last known good", "updated at".
     */
    public function timeFormat(string $format): static
    {
        $this->timeFormat = $format;

        return $this;
    }

    public function getTimeFormat(): string
    {
        return $this->timeFormat !== null && $this->timeFormat !== '' ? $this->timeFormat : 'H:i:s';
    }

    /**
     * The timezone every timestamp this package renders is shown in.
     */
    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Falls back through Filament's timezone and the application's to UTC.
     *
     * UTC is the floor rather than an exception: an application that never set
     * `app.timezone` is misconfigured, but a monitoring panel that throws instead of
     * drawing is worse than one showing the right instant under the wrong label.
     * A zone PHP does not recognise is treated as if it were not set at all.
     */
    public function getTimezone(): string
    {
        return $this->zone($this->timezone)
            ?? $this->zone(config('filament-fuse.dashboard.timezone'))
            ?? $this->zone($this->filamentTimezone())
            ?? $this->zone(config('app.timezone'))
            ?? 'UTC';
    }

    /*
    |--------------------------------------------------------------------------
    | Authorisation
    |--------------------------------------------------------------------------
    */

    /**
     * Decide who may see the dashboard, per panel.
     *
     * The closure receives the panel's authenticated user as `$user` and the thing being
     * attempted as `$ability` — the sole {@see Ability} case. When set it replaces the
     * gate entirely for this panel; that remains the way to authorise every panel at once.
     *
     * `$user` is nullable. Filament's own `Authenticate` middleware closes an
     * unauthenticated request before this is normally reached, but a guest-accessible
     * panel, or a test, can still call it with nobody signed in — a callback that assumes
     * a user is always present should type-hint `?Authenticatable` and guard for null.
     */
    public function authorize(Closure $callback): static
    {
        $this->authorize = $callback;

        return $this;
    }

    public function hasAuthorization(): bool
    {
        return $this->authorize instanceof Closure;
    }

    public function isAuthorized(Ability $ability = Ability::View): bool
    {
        if (! $this->authorize instanceof Closure) {
            return true;
        }

        $user = $this->currentUser();

        $typed = [Ability::class => $ability];

        if ($user instanceof Authenticatable) {
            $typed[Authenticatable::class] = $user;
            $typed[$user::class] = $user;
        }

        return (bool) $this->evaluate($this->authorize, ['user' => $user, 'ability' => $ability], $typed);
    }

    private function currentUser(): ?Authenticatable
    {
        try {
            return filament()->auth()->user();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * What Filament's own timezone facade says, if a panel is there to ask.
     *
     * A queue worker or a console command has no current panel, and asking anyway
     * throws rather than returning null.
     */
    private function filamentTimezone(): ?string
    {
        try {
            return FilamentTimezone::get();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A usable timezone name, or null for anything PHP would refuse.
     */
    private function zone(mixed $timezone): ?string
    {
        if (! is_string($timezone) || $timezone === '') {
            return null;
        }

        // The identifier list is fixed for the life of the process, and this runs once
        // per timestamp rendered — every tile, every hero. Reading it once per request
        // rather than once per timestamp is the entire saving.
        static $ids = null;
        $ids ??= DateTimeZone::listIdentifiers();

        return in_array($timezone, $ids, true) ? $timezone : null;
    }

    private function iconFromConfig(string $key, string $default): string|BackedEnum
    {
        $icon = config($key);

        if ($icon instanceof BackedEnum || (is_string($icon) && $icon !== '')) {
            return $icon;
        }

        return $default;
    }
}
