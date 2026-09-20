@if ($visible)
    <div class="fuse-monitor">
        <a href="{{ $url }}" class="{{ implode(' ', $classes) }}" aria-label="{{ $aria }}">
            <span class="{{ $dotClass }}" aria-hidden="true"></span>
            {{ $text }}
        </a>
    </div>
@endif
