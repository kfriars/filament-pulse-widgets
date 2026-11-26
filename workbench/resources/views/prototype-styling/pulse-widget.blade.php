{{--
    PROTOTYPE (throwaway): Filament owns the grid cell, the unchanged Pulse card renders inside it.
    `.fi-pulse-widget` is what every variant's stylesheet is scoped to.
--}}
<x-filament-widgets::widget class="fi-pulse-widget">
    {!! $cardCss !!}

    @livewire($card, ['lazy' => false], key($this->getId().'-card'))
</x-filament-widgets::widget>
