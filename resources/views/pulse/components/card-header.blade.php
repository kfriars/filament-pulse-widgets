{{--
    The package puts this directory first in Pulse's `pulse` view namespace, so every
    `<x-pulse::card-header>` renders this view. It renders the header the namespace
    held before (Pulse's own, or an app's published override), and inside a Pulse
    widget it adds the period selector after the Pulse card's own controls.

    The period selector is echoed, not wrapped in `@if`: Livewire marks every `@if`
    in the output, which would change the header outside Pulse widgets too.
--}}
@props(['name' => '', 'title' => '', 'details' => null])
@php
    $period = \Kfriars\FilamentPulseWidgets\PeriodSelector::periodOf($__livewire ?? null);
    $periodSelector = $period ? view('filament-pulse-widgets::period-selector', ['pulseCard' => $__livewire, 'period' => $period]) : null;
@endphp
<x-filament-pulse-widgets-pulse::card-header :name="$name" :title="$title" :details="$details" {{ $attributes }}>
    {{ $slot }}
    @isset($icon)
        <x-slot:icon>{{ $icon }}</x-slot:icon>
    @endisset
    @if (isset($actions) || $periodSelector)
        <x-slot:actions>{{ $actions ?? '' }}{{ $periodSelector }}</x-slot:actions>
    @endif
</x-filament-pulse-widgets-pulse::card-header>
