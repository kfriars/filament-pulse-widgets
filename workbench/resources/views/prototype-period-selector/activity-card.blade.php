{{-- PROTOTYPE (throwaway): a custom Pulse card's view. Its extra classes (gradient, violet, tabular-nums) only exist in the card's own css(). --}}
<x-pulse::card id="workbench-activity" :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Activity (custom Pulse card)"
        x-bind:title="`Time: {{ number_format($time) }}ms; Run at: ${formatDate('{{ $runAt }}')};`"
        details="past {{ $this->periodForHumans() }}"
    />

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        <p class="activity-css-missing mb-3 rounded-md bg-red-50 px-3 py-2 text-xs font-bold text-red-700">Card::css() did not reach the page</p>

        <dl class="grid grid-cols-2 gap-3">
            @foreach (['user_request' => 'Requests', 'user_job' => 'Jobs', 'exception' => 'Exceptions', 'cache_hit' => 'Cache hits', 'cache_miss' => 'Cache misses'] as $type => $label)
                <div class="rounded-lg border-l-4 border-violet-400 bg-linear-to-br from-violet-50 to-white px-3 py-2 dark:border-violet-500 dark:from-violet-500/15 dark:to-transparent">
                    <dt class="text-xs tracking-wide text-violet-700 uppercase dark:text-violet-300">{{ $label }}</dt>
                    <dd class="text-2xl font-bold tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($totals[$type] ?? 0) }}</dd>
                </div>
            @endforeach
        </dl>
    </x-pulse::scroll>
</x-pulse::card>
