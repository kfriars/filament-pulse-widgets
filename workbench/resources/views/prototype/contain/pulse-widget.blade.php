{{-- PROTOTYPE (throwaway), shape "contain": Filament owns the grid cell, the Pulse card renders inside it. --}}
<x-filament-widgets::widget class="pulse-scope pulse-shape-contain">
    @livewire($card, $cardParams, key($cardKey))
</x-filament-widgets::widget>
