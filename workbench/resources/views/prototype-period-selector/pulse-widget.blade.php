{{--
    PROTOTYPE (throwaway): Filament owns the grid cell, the unchanged Pulse card renders inside it.
    The period is in the card's key, so a new period mounts a fresh card with the new `period` prop.
--}}
<x-filament-widgets::widget class="fi-pulse-widget">
    {!! $cardCss !!}

    @livewire($card, ['period' => $period, 'lazy' => false], key($this->getId().'-card-'.$period))
</x-filament-widgets::widget>
