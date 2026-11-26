{{--
    PROTOTYPE (throwaway): swapped in for Pulse's own <x-pulse::card-header> everywhere (see
    WorkbenchServiceProvider). It never copies Pulse's markup: it always renders Pulse's own header,
    and inside a Pulse widget it only adds the period selector, either in the details (variant
    "inline") or in the actions slot, beside the card's own controls (every other variant).

    On /pulse the card has no Pulse widget, so this passes everything through untouched.
--}}
@props(['name' => '', 'title' => '', 'details' => null])
@php
    use Workbench\App\Prototype\PulseWidgetPeriodSelector\PeriodSelector;
    use Workbench\App\Prototype\PulseWidgetPeriodSelector\PrototypeDashboard;

    $card = PeriodSelector::cardFor($__livewire ?? null);
    $variant = $card ? PrototypeDashboard::variant() : null;
    $inActions = $card !== null && $variant !== 'inline';

    if ($card !== null && $variant === 'inline') {
        $details = PeriodSelector::inlineDetails($card, $details);
    }
@endphp
<x-pulse-original::card-header :name="$name" :title="$title" :details="$details" {{ $attributes }}>
    @isset($icon)
        <x-slot:icon>{{ $icon }}</x-slot:icon>
    @endisset
    @if (isset($actions) || $inActions)
        <x-slot:actions>
            {{ $actions ?? '' }}
            @if ($inActions)
                @include("prototype-period-selector::selector.{$variant}", ['card' => $card])
            @endif
        </x-slot:actions>
    @endif
</x-pulse-original::card-header>
