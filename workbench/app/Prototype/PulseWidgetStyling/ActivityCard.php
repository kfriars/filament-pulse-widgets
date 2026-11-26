<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetStyling;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

use function Orchestra\Testbench\package_path;

/**
 * PROTOTYPE (throwaway): a custom Pulse card written the way Pulse's docs say a
 * packaged card should be, including its own stylesheet from css().
 */
#[Lazy]
class ActivityCard extends Card
{
    public function render(): Renderable
    {
        [$totals, $time, $runAt] = $this->remember(
            fn () => $this->aggregateTotal(['user_request', 'user_job', 'exception', 'cache_hit', 'cache_miss'], 'count'),
        );

        return View::make('prototype-styling::activity-card', [
            'totals' => $totals,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }

    protected function css(): string
    {
        return package_path('workbench/resources/css/prototype/dist/activity-card.css');
    }
}
