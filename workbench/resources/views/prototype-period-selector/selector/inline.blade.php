{{--
    PROTOTYPE (throwaway), variant "inline": the header's "past hour" becomes the control.
    A native select is laid invisibly over the words, so its picker can't be clipped by the
    header's truncation and nothing about it is lost when a 5s poll re-renders the card.
--}}
@php
    use Workbench\App\Prototype\PulseWidgetPeriodSelector\PeriodSelector;
@endphp
<span
    data-period-selector="inline"
    class="relative inline-flex items-baseline gap-0.5 rounded-sm underline decoration-gray-300 decoration-dotted underline-offset-2 hover:text-gray-500 focus-within:text-gray-500 focus-within:ring-2 focus-within:ring-(--primary-500) dark:decoration-gray-600 dark:hover:text-gray-400 dark:focus-within:text-gray-400"
><span>{{ PeriodSelector::label('long', $card->period) }}</span><svg class="size-3 self-center" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg><select
        aria-label="{{ PeriodSelector::label('period') }}"
        class="absolute inset-0 cursor-pointer opacity-0"
        x-on:change="loading = true; $wire.$parent.setPeriod($event.target.value)"
    >
        @foreach (PeriodSelector::PERIODS as $period)
            <option value="{{ $period }}" @selected($period === $card->period)>{{ PeriodSelector::label('long', $period) }}</option>
        @endforeach
    </select></span>
