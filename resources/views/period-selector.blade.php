{{--
    The period selector in a Pulse card's header, inside a Pulse widget. `$pulseCard`
    is the Pulse card and `$period` the period it shows.

    A pick sets the Pulse widget's period, which remounts the Pulse card at the new
    period, and starts the Pulse card's own loading state until it does. Picking the
    period already shown only closes the menu: nothing would remount to end it.

    The `wire:key` makes Filament mark the open menu `wire:ignore.self`, and lets
    Livewire match the dropdown by its key when the Pulse card's polls morph it.
--}}
@php
    use Kfriars\FilamentPulseWidgets\PulsePeriod;
@endphp

<x-filament::dropdown
    placement="bottom-end"
    :wire:key="$pulseCard->getId() . '.period-selector'"
    class="fi-pulse-widget-period-selector ml-auto shrink-0"
>
    <x-slot name="trigger">
        <x-filament::button color="gray" size="xs" icon="heroicon-m-clock" :tooltip="$period->getLongLabel()">
            {{ $period->getShortLabel() }}
        </x-filament::button>
    </x-slot>

    <x-filament::dropdown.list role="group" :aria-label="__('filament-pulse-widgets::period-selector.period')">
        @foreach (PulsePeriod::cases() as $option)
            <x-filament::dropdown.list.item
                role="menuitemradio"
                :aria-checked="$option === $period ? 'true' : 'false'"
                :color="$option === $period ? 'primary' : 'gray'"
                :icon="$option === $period ? 'heroicon-m-check' : 'heroicon-m-minus'"
                :x-on:click="$option === $period ? 'close()' : 'close(); loading = true; $wire.$parent.$set(\'period\', ' . \Illuminate\Support\Js::from($option->value) . ')'"
            >
                {{ $option->getLabel() }}
            </x-filament::dropdown.list.item>
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>
