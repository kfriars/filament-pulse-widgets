{{-- PROTOTYPE (throwaway): a custom Pulse card's view, using only Pulse's documented card components. --}}
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Activity (custom card)"
        x-bind:title="`Time: {{ number_format($time) }}ms; Run at: ${formatDate('{{ $runAt }}')};`"
        details="past {{ $this->periodForHumans() }} · rendered {{ now()->format('H:i:s') }}"
    />

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        <dl class="grid grid-cols-2 gap-3">
            @foreach (['user_request' => 'Requests', 'user_job' => 'Jobs', 'exception' => 'Exceptions', 'cache_hit' => 'Cache hits', 'cache_miss' => 'Cache misses'] as $type => $label)
                <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="text-xl font-bold tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($totals[$type] ?? 0) }}</dd>
                </div>
            @endforeach
        </dl>
    </x-pulse::scroll>
</x-pulse::card>
