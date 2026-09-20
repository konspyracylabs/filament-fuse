<?php

namespace KonspyracyLabs\FilamentFuse\View\Components;

use KonspyracyLabs\FilamentFuse\Authorization\Ability;
use KonspyracyLabs\FilamentFuse\Authorization\Authorizer;
use KonspyracyLabs\FilamentFuse\Filament\Pages\Circuits;
use KonspyracyLabs\FilamentFuse\FilamentFuse;
use KonspyracyLabs\FilamentFuse\Support\CircuitInspector;

/**
 * The always-visible topbar element.
 *
 * Reads the cache only — never a database — because it renders on every panel
 * request, including pages that have nothing to do with this package.
 *
 * Quiet when everything is fine: one dot, one word, no colour weight. It earns
 * attention only when a circuit is actually open.
 */
class Indicator extends FuseComponent
{
    /**
     * Whether to render anything at all.
     *
     * A host that has switched the indicator off, or has not enabled the package,
     * gets nothing rather than a hidden element still taking up layout.
     */
    public bool $visible;

    public string $url = '';

    public string $text = '';

    public string $aria = '';

    /** @var list<string> */
    public array $classes = [];

    public string $dotClass = '';

    public function __construct(
        FilamentFuse $fuse,
        CircuitInspector $inspector,
        Authorizer $authorizer,
    ) {
        $this->visible = $fuse->isEnabled()
            && $this->plugin()->hasIndicator()
            && $authorizer->allows(Ability::View);

        if (! $this->visible) {
            return;
        }

        $down = $inspector->downCount();
        $isDown = $down > 0;

        $this->url = Circuits::getUrl();
        $this->aria = $this->label('indicator.aria');

        $this->text = $isDown
            ? $this->choice('indicator.down', $down)
            : $this->label('indicator.ok');

        $this->classes = ['fuse-indicator', $isDown ? 'fuse-indicator--down' : 'fuse-indicator--ok'];
        $this->dotClass = 'fuse-dot '.($isDown ? 'fuse-dot--open' : 'fuse-dot--closed');
    }
}
