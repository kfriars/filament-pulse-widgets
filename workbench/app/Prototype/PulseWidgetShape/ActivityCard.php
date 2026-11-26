<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * PROTOTYPE (throwaway): a custom Pulse card, written the way the Pulse docs
 * tell an app to write one. Both prototype shapes have to host it.
 */
#[Lazy]
class ActivityCard extends Card
{
    public function render(): Renderable
    {
        [$totals, $time, $runAt] = $this->remember(
            fn () => $this->aggregateTotal(['user_request', 'user_job', 'exception', 'cache_hit', 'cache_miss'], 'count'),
        );

        return View::make('prototype::activity-card', [
            'totals' => $totals,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
