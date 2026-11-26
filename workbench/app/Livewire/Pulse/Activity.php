<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * A custom Pulse card, written the way Pulse's docs tell an app to write one:
 * how many requests, jobs, exceptions and cache lookups the app has seen.
 */
#[Lazy]
class Activity extends Card
{
    protected const TYPES = [
        'user_request' => 'Requests',
        'user_job' => 'Jobs',
        'exception' => 'Exceptions',
        'cache_hit' => 'Cache hits',
        'cache_miss' => 'Cache misses',
    ];

    public function render(): Renderable
    {
        [$totals, $time, $runAt] = $this->remember(
            fn () => $this->aggregateTotal(array_keys(self::TYPES), 'count'),
        );

        return View::make('workbench::livewire.pulse.activity', [
            'types' => self::TYPES,
            'totals' => $totals,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
