<?php

namespace KonspyracyLabs\FilamentFuse\View\Components;

use Illuminate\Support\Carbon;
use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;
use KonspyracyLabs\FilamentFuse\View\Components\Concerns\DescribesSnapshot;

/**
 * One circuit on the dashboard grid.
 *
 * Name, state, how long it has been that way, and two facts: when it last worked and
 * when the next probe is due. Nothing that needs a caption to be understood; the
 * counters and thresholds live on the circuit's own page, beside their captions.
 *
 * The whole tile is the link rather than a "view" affordance inside it, so the target
 * is the full card at every breakpoint and there is nothing to aim at on a phone.
 */
class Tile extends FuseComponent
{
    use DescribesSnapshot;

    /** @var list<string> */
    public array $classes;

    public string $dotClass;

    /**
     * "Down for 4m 12s", "Healthy for 6d 4h", or null when there is nothing to measure.
     */
    public ?string $duration;

    public string $lastGood;

    public string $nextProbe;

    public function __construct(
        public CircuitSnapshot $snapshot,
        public string $url = '#',
    ) {
        $modifier = $this->modifier($snapshot->stateKey());

        $this->classes = ['fuse-tile', 'fuse-tile--collapsible', "fuse-tile--{$modifier}"];
        $this->dotClass = "fuse-dot fuse-dot--{$modifier}";

        $this->duration = $this->describeDuration($snapshot);
        $this->lastGood = $this->resolveLastGood();
        $this->nextProbe = $this->describeNextProbe($snapshot);
    }

    /**
     * When the circuit last worked.
     *
     * A healthy circuit is working right now; a broken one last worked at the moment it
     * tripped. A circuit with neither has never been observed either way, and says so
     * rather than implying it has always been fine.
     */
    private function resolveLastGood(): string
    {
        if ($this->snapshot->isClosed()) {
            return $this->label('tile.last_good_now');
        }

        $openedAt = $this->snapshot->openedAt;

        // To the minute, like the probe's due time: the two facts sit side by side.
        return $openedAt instanceof Carbon
            ? $this->clock($openedAt)
            : $this->label('tile.last_good_never');
    }
}
