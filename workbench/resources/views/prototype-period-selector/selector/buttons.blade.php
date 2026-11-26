{{--
    PROTOTYPE (throwaway), variant "buttons": Pulse's own "1h 6h 24h 7d" from the top of /pulse
    (pulse::livewire.period-selector), same colours, sized down to sit at the right of the header.
--}}
@php
    use Workbench\App\Prototype\PulseWidgetPeriodSelector\PeriodSelector;
@endphp
<div data-period-selector="buttons" role="group" aria-label="{{ PeriodSelector::label('period') }}" class="ml-auto flex shrink-0">
    @foreach (PeriodSelector::PERIODS as $period)
        <button
            type="button"
            aria-pressed="{{ $period === $card->period ? 'true' : 'false' }}"
            x-on:click="loading = true; $wire.$parent.setPeriod('{{ $period }}')"
            @class([
                'px-1 text-xs font-semibold sm:text-sm',
                'text-gray-700 dark:text-gray-300' => $period === $card->period,
                'text-gray-300 hover:text-gray-400 dark:text-gray-600 dark:hover:text-gray-500' => $period !== $card->period,
            ])
        >{{ PeriodSelector::label('short', $period) }}</button>
    @endforeach
</div>
