<?php

namespace KonspyracyLabs\FilamentFuse\View\Components;

use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;
use KonspyracyLabs\FilamentFuse\View\Components\Concerns\DescribesSnapshot;

/**
 * The live half of a circuit's detail page: orb and the four metric cards.
 *
 * The cards are the same four Fuse's own dashboard shows, in the same order, so the two
 * tools can be read interchangeably.
 */
class CircuitHero extends FuseComponent
{
    use DescribesSnapshot;

    public bool $isUnknown;

    public ?string $duration;

    public string $nextProbe;

    /**
     * @var list<array{label: string, value: string, caption: string, accent: string}>
     */
    public array $metrics;

    public string $errorHeading;

    public string $errorBody;

    /**
     * @param  string|null  $poll  Livewire interval. Taken as a prop rather than as a
     *                             `wire:poll` attribute on the tag, because Blade's
     *                             component-tag parser cannot read an `@if` directive
     *                             inside an attribute list — it silently renders the
     *                             whole component as nothing.
     */
    public function __construct(
        public CircuitSnapshot $snapshot,
        public ?string $poll = null,
    ) {
        $this->isUnknown = $snapshot->isUnknown();

        $this->errorHeading = $this->label('error.unavailable.heading');
        $this->errorBody = $this->label('error.unavailable.body');

        $this->duration = $this->isUnknown ? null : $this->describeDuration($snapshot);
        $this->nextProbe = $this->describeNextProbe($snapshot);

        $this->metrics = $this->resolveMetrics();
    }

    /**
     * @return list<array{label: string, value: string, caption: string, accent: string}>
     */
    private function resolveMetrics(): array
    {
        return [
            [
                'label' => $this->label('metric.attempts'),
                'value' => number_format($this->snapshot->attempts),
                'caption' => $this->label('metric.attempts_caption'),
                'accent' => 'cyan',
            ],
            [
                'label' => $this->label('metric.failures'),
                'value' => number_format($this->snapshot->failures),
                'caption' => $this->label('metric.failures_caption'),
                'accent' => 'open',
            ],
            [
                'label' => $this->label('metric.failure_rate'),
                'value' => "{$this->snapshot->failureRate}%",
                'caption' => $this->label('metric.failure_rate_caption', ['value' => $this->snapshot->threshold]),
                'accent' => $this->snapshot->isRateHot() ? 'open' : 'closed',
            ],
            [
                'label' => $this->label('metric.min_requests'),
                'value' => number_format($this->snapshot->minRequests),
                'caption' => $this->label('metric.min_requests_caption'),
                'accent' => 'half-open',
            ],
        ];
    }
}
