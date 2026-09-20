{{--
    The dashboard.

    Polling is set on this wrapper rather than the page so the whole grid refreshes as
    one frame — a tile moving between sections out of step with the headings would let
    the two disagree about whether anything is down.
--}}
<x-filament-panels::page>
    <div class="fuse-monitor" @if ($poll) wire:poll.{{ $poll }} @endif>
        @if (! $configured)
            <x-filament-fuse::empty-state
                class="fuse-panel"
                icon="heroicon-o-bolt-slash"
                :heading="__('filament-fuse::filament-fuse.empty.circuits.heading')"
                :body="__('filament-fuse::filament-fuse.empty.circuits.body')"
            >
                <pre class="fuse-empty__code">// config/fuse.php
'services' => [
    'stripe' => [],
    'sendgrid' => ['threshold' => 40],
],</pre>
            </x-filament-fuse::empty-state>
        @else
            <x-filament-fuse::live-strip :poll="$poll" />

            {{-- ------------------------------------------------------------- open --}}
            <div class="fuse-group fuse-group--down">
                <h2 class="fuse-group__head">
                    <span>{{ __('filament-fuse::filament-fuse.dashboard.open') }}</span>
                    <span class="fuse-group__count">{{ $down->count() }}</span>
                </h2>

                @if ($down->isEmpty())
                    <p class="fuse-group__empty">{{ __('filament-fuse::filament-fuse.dashboard.none_open') }}</p>
                @else
                    <div class="fuse-tiles">
                        @foreach ($down as $circuit)
                            <x-filament-fuse::tile
                                wire:key="circuit-{{ $circuit->service }}"
                                :snapshot="$circuit"
                                :url="$urls[$circuit->service]"
                            />
                        @endforeach
                    </div>
                @endif
            </div>

            <hr class="fuse-divider" />

            {{-- ----------------------------------------------------------- closed --}}
            <div class="fuse-group fuse-group--healthy">
                <h2 class="fuse-group__head">
                    <span>{{ __('filament-fuse::filament-fuse.dashboard.closed') }}</span>
                    <span class="fuse-group__count">{{ $healthy->count() }}</span>
                </h2>

                @if ($healthy->isEmpty())
                    <p class="fuse-group__empty">{{ __('filament-fuse::filament-fuse.dashboard.none_closed') }}</p>
                @else
                    <div class="fuse-tiles">
                        @foreach ($healthy as $circuit)
                            <x-filament-fuse::tile
                                wire:key="circuit-{{ $circuit->service }}"
                                :snapshot="$circuit"
                                :url="$urls[$circuit->service]"
                            />
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($hasUnreadable)
                <x-filament-fuse::error-state
                    class="fuse-section"
                    :heading="__('filament-fuse::filament-fuse.error.unavailable.heading')"
                    :body="__('filament-fuse::filament-fuse.error.unavailable.body')"
                />
            @endif
        @endif
    </div>
</x-filament-panels::page>
