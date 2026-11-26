{{--
    PROTOTYPE (throwaway), variant "select": Pulse's own <x-pulse::select>, labelled "Period", beside
    the card's own controls exactly like Slow Queries' "Sort by".
--}}
@php
    use Workbench\App\Prototype\PulseWidgetPeriodSelector\PeriodSelector;

    $options = collect(PeriodSelector::PERIODS)
        ->mapWithKeys(fn (string $period): array => [$period => PeriodSelector::label('option', $period)])
        ->all();
@endphp
<x-pulse::select
    data-period-selector="select"
    id="period-{{ $card->getId() }}"
    :label="PeriodSelector::label('period')"
    :options="$options"
    x-init="$el.value = '{{ $card->period }}'"
    x-on:change="loading = true; $wire.$parent.setPeriod($event.target.value)"
/>
