<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Activity"
        x-bind:title="`Time: {{ number_format($time) }}ms; Run at: ${formatDate('{{ $runAt }}')};`"
        details="past {{ $this->periodForHumans() }}"
    />

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        <dl class="grid grid-cols-2 gap-3">
            @foreach ($types as $type => $label)
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="text-xl font-bold tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($totals[$type] ?? 0) }}</dd>
                </div>
            @endforeach
        </dl>
    </x-pulse::scroll>
</x-pulse::card>
