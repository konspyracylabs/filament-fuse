<a href="{{ $url }}" {{ $attributes->class($classes) }}>
    <div class="fuse-tile__head">
        <span class="fuse-tile__name">
            <span class="{{ $dotClass }}" aria-hidden="true"></span>
            <span>{{ $snapshot->service }}</span>
        </span>
    </div>

    @if ($duration !== null)
        <div class="fuse-tile__duration">{{ $duration }}</div>
    @endif

    <div class="fuse-tile__facts">
        <div>
            <div class="fuse-label">{{ __('filament-fuse::filament-fuse.tile.last_good') }}</div>
            <div class="fuse-tile__fact-value">{{ $lastGood }}</div>
        </div>

        <div>
            <div class="fuse-label">{{ __('filament-fuse::filament-fuse.tile.next_probe') }}</div>
            <div class="fuse-tile__fact-value">{{ $nextProbe }}</div>
        </div>
    </div>
</a>
