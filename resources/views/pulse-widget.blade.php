{{-- A hidden placement keeps its root element, which Livewire needs, but takes no room in the grid. --}}
<x-filament-widgets::widget :style="$isPlacementHidden ? 'display: none' : null">
    @unless ($isPlacementHidden)
        @livewire($pulseCard, $pulseCardProperties, key($pulseCardKey))
    @endunless
</x-filament-widgets::widget>
