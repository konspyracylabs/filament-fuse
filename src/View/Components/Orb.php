<?php

namespace KonspyracyLabs\FilamentFuse\View\Components;

use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;

/**
 * The hero state display on a circuit's detail page.
 *
 * Echoes Fuse's own 280px orb and its slowly rotating dashed ring, so the two tools look
 * like one product. The ring is the only purely decorative element in the package; it
 * stops under prefers-reduced-motion along with everything else.
 */
class Orb extends FuseComponent
{
    /** @var list<string> */
    public array $classes;

    public string $text;

    public function __construct(
        public CircuitSnapshot $snapshot,
    ) {
        $key = $snapshot->stateKey();

        $this->classes = ['fuse-orb', 'fuse-orb--'.$this->modifier($key)];
        $this->text = $this->label("state.{$key}");
    }
}
