{{-- The Pulse widget stylesheet is scoped to `.fi-pulse-widget`, so it styles the Pulse card and nothing else. --}}
{{-- A hidden placement keeps its root element, which Livewire needs, but takes no room in the grid. --}}
<x-filament-widgets::widget class="fi-pulse-widget" :style="$isPlacementHidden ? 'display: none' : null">
    @unless ($isPlacementHidden)
        @livewire($pulseCard, $pulseCardProperties, key($pulseCardKey))
    @endunless
</x-filament-widgets::widget>
