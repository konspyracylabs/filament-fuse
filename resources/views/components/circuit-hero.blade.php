<div {{ $attributes->class(['fuse-panel', 'fuse-hero']) }} @if ($poll) wire:poll.{{ $poll }} @endif>
    @if ($isUnknown)
        <x-filament-fuse::error-state
            class="fuse-error--bare"
            :heading="$errorHeading"
            :body="$errorBody"
        />
    @else
        <div class="fuse-hero__body">
            <x-filament-fuse::orb :snapshot="$snapshot" />

            <div class="fuse-hero__side">
                @if ($duration !== null)
                    <div class="fuse-tile__duration fuse-hero__duration">{{ $duration }}</div>
                @endif

                <div class="fuse-hero__facts">
                    <div>
                        <div class="fuse-label">{{ __('filament-fuse::filament-fuse.tile.next_probe') }}</div>
                        <div class="fuse-mono fuse-hero__fact-value">{{ $nextProbe }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="fuse-metrics">
            @foreach ($metrics as $metric)
                <x-filament-fuse::metric
                    :label="$metric['label']"
                    :value="$metric['value']"
                    :caption="$metric['caption']"
                    :accent="$metric['accent']"
                />
            @endforeach
        </div>
    @endif
</div>
