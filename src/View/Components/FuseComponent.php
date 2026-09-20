<?php

namespace KonspyracyLabs\FilamentFuse\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\View\Component;
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;
use RuntimeException;

/**
 * Base for the package's view components.
 *
 * Every component resolves its own strings, classes and icons in PHP and exposes them
 * as plain public properties, so the Blade file next to it is markup and nothing else.
 * A template that has to ask a question — which icon, which colour, singular or plural
 * — is a template that cannot be read at a glance, and it puts logic somewhere neither
 * static analysis nor a test can reach it.
 */
abstract class FuseComponent extends Component
{
    /**
     * Where this component's template lives.
     *
     * Derived from the class name so a new component needs no wiring: LiveStrip
     * renders filament-fuse::components.live-strip.
     */
    public function render(): View
    {
        // Through the factory rather than the view() helper: the helper's parameter is
        // typed as a literal view name, which a name derived from the class can never be.
        $view = 'filament-fuse::components.'.$this->viewName();

        // exists() narrows $view to PHPStan's view-string for make() below; it is also a
        // genuine guard, since a component with no matching template is a wiring mistake
        // this should say plainly rather than fail deep inside the view factory.
        if (! view()->exists($view)) {
            throw new RuntimeException("View [{$view}] not found.");
        }

        return view()->make($view);
    }

    /**
     * Translate a state or outcome key into its CSS modifier.
     *
     * Enum values are snake_case because that is what Fuse stores in the cache; CSS
     * modifiers are kebab-case because that is what the stylesheet was written in.
     * The conversion belongs here rather than in a data object, which should not know
     * that a stylesheet exists.
     */
    protected function modifier(string $key): string
    {
        return str_replace('_', '-', $key);
    }

    /**
     * Look up one of this package's translations.
     *
     * @param  array<string, bool|float|int|string|null>  $replace
     */
    protected function label(string $key, array $replace = []): string
    {
        return __("filament-fuse::filament-fuse.{$key}", $replace);
    }

    /**
     * Look up a translation that varies with a count.
     *
     * @param  array<string, bool|float|int|string|null>  $replace
     */
    protected function choice(string $key, int $count, array $replace = []): string
    {
        return trans_choice("filament-fuse::filament-fuse.{$key}", $count, [...$replace, 'count' => $count]);
    }

    /**
     * A time-only value — "last known good", "updated at" — in the panel's timezone.
     */
    protected function time(Carbon $at): string
    {
        return $this->stamp($at, $this->plugin()->getTimeFormat());
    }

    /**
     * Hours and minutes only — for a "due" time read at a glance, where seconds are noise.
     */
    protected function clock(Carbon $at): string
    {
        return $this->stamp($at, 'H:i');
    }

    /**
     * The settings in force for the panel rendering this component.
     */
    protected function plugin(): FilamentFusePlugin
    {
        return FilamentFusePlugin::resolve();
    }

    /**
     * Format for display without disturbing the instance.
     *
     * Models hand out their cached Carbon attributes by reference, and setTimezone()
     * mutates; a copy keeps a component from shifting the timestamp under everything
     * else that reads the same model in the same request.
     */
    private function stamp(Carbon $at, string $format): string
    {
        return $at->copy()->setTimezone($this->plugin()->getTimezone())->format($format);
    }

    /**
     * The kebab-case template name for this component.
     */
    private function viewName(): string
    {
        $short = class_basename(static::class);

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    }
}
