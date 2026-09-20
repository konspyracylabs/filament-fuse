<div {{ $attributes->class('fuse-orb-ctn') }}>
    <div class="fuse-orb__ring" aria-hidden="true"></div>

    <div class="{{ implode(' ', $classes) }}">
        <div class="fuse-orb__state">{{ $text }}</div>
        <div class="fuse-label">{{ $snapshot->service }}</div>
    </div>
</div>
