<div {{ $attributes->class('fuse-live') }}>
    <span class="fuse-dot fuse-dot--live" aria-hidden="true"></span>
    <span class="fuse-label fuse-live__label">{{ $live }}</span>

    <span class="fuse-label">
        {{ $updated }}

        @if ($cadence !== null)
            &middot; {{ $cadence }}
        @endif
    </span>
</div>
