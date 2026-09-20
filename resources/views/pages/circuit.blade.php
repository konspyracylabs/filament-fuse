{{--
    One circuit in detail: its live state and its four metric cards.

    wire:poll on the hero below polls the whole Livewire component, not just that
    element — there is no such thing as a partial poll.
--}}
<x-filament-panels::page>
    <div class="fuse-monitor">
        <x-filament-fuse::circuit-hero :snapshot="$snapshot" :poll="$poll" />
    </div>
</x-filament-panels::page>
