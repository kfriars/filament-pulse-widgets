{{--
    PROTOTYPE (throwaway), variant "menu": Filament's own gray button and dropdown, as on a Filament
    page header, dropped into a Pulse card. Its `wire:key` makes Filament mark the open panel
    `wire:ignore.self`, so a 5s poll doesn't snap it shut.
--}}
@php
    use Workbench\App\Prototype\PulseWidgetPeriodSelector\PeriodSelector;
@endphp
<x-filament::dropdown
    data-period-selector="menu"
    placement="bottom-end"
    wire:key="period-menu-{{ $card->getId() }}"
    class="ml-auto shrink-0"
>
    <x-slot name="trigger">
        <x-filament::button color="gray" size="xs" icon="heroicon-m-clock" :tooltip="PeriodSelector::label('long', $card->period)">
            {{ PeriodSelector::label('short', $card->period) }}
        </x-filament::button>
    </x-slot>

    <x-filament::dropdown.list>
        @foreach (PeriodSelector::PERIODS as $period)
            <x-filament::dropdown.list.item
                :icon="$period === $card->period ? 'heroicon-m-check' : 'heroicon-m-minus'"
                :color="$period === $card->period ? 'primary' : 'gray'"
                x-on:click="close(); loading = true; $wire.$parent.setPeriod('{{ $period }}')"
            >
                {{ PeriodSelector::label('option', $period) }}
            </x-filament::dropdown.list.item>
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>
