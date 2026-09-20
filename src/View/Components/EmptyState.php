<?php

namespace KonspyracyLabs\FilamentFuse\View\Components;

/**
 * An empty state.
 *
 * `good` marks the case where emptiness is the desired outcome — greeting it with a
 * grey box and a shrug reads as a fault rather than as the best possible result.
 */
class EmptyState extends FuseComponent
{
    /** @var list<string> */
    public array $classes;

    public function __construct(
        public string $heading,
        public string $body,
        public ?string $icon = null,
        bool $good = false,
    ) {
        $this->classes = array_filter([
            'fuse-empty',
            $good ? 'fuse-empty--good' : null,
        ]);
    }
}
