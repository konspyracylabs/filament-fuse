<div {{ $attributes->class($classes) }}>
    @if ($icon !== null)
        @svg($icon, 'fuse-state__icon fuse-empty__icon')
    @endif

    <h2 class="fuse-empty__heading">{{ $heading }}</h2>
    <p class="fuse-empty__body">{{ $body }}</p>

    {{ $slot }}
</div>
