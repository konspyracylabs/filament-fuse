<?php

namespace KonspyracyLabs\FilamentFuse\View\Components;

/**
 * Shown when Fuse's cache could not be read.
 *
 * This is not an empty state and must never look like one. If the cache is down the
 * package knows nothing about the circuit, and the honest thing to report is that it
 * cannot see — not that everything is fine.
 */
class ErrorState extends FuseComponent
{
    public function __construct(
        public string $heading,
        public string $body,
    ) {}
}
