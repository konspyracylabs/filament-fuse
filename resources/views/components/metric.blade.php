<div {{ $attributes->class($classes) }}>
    <div class="fuse-label">{{ $label }}</div>
    <div class="fuse-metric__value">{{ $value }}</div>

    @if ($caption !== null)
        <div class="fuse-metric__caption">{{ $caption }}</div>
    @endif
</div>
