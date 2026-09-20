<?php

namespace KonspyracyLabs\FilamentFuse\View\Components;

/**
 * One figure with its label and a sub-caption, under an accent hairline.
 *
 * Deliberately the same four-card shape Fuse's own status dashboard uses for
 * Attempts / Failures / Failure rate / Min requests, so an operator moving between the
 * two reads the same layout twice rather than learning it again.
 */
class Metric extends FuseComponent
{
    /** @var list<string> */
    public array $classes;

    /**
     * @param  string  $accent  cyan | closed | open | half-open
     */
    public function __construct(
        public string $label,
        public string $value,
        public ?string $caption = null,
        string $accent = 'cyan',
    ) {
        $this->classes = ['fuse-metric', "fuse-metric--{$accent}"];
    }
}
