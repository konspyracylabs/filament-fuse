<div {{ $attributes->class('fuse-error') }} role="alert">
    @svg('heroicon-o-exclamation-triangle', 'fuse-state__icon fuse-error__icon')

    <div>
        <div class="fuse-error__heading">{{ $heading }}</div>
        <p class="fuse-empty__body fuse-error__body">{{ $body }}</p>

        {{ $slot }}
    </div>
</div>
