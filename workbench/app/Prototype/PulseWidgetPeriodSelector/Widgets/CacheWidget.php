<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetPeriodSelector\Widgets;

use Workbench\App\Prototype\PulseWidgetPeriodSelector\PulseWidget;

/**
 * PROTOTYPE (throwaway).
 */
class CacheWidget extends PulseWidget
{
    protected string $card = 'pulse.cache';

    /**
     * Shows that a Pulse widget can start at its own developer-set period.
     */
    protected string $defaultPeriod = '24_hours';
}
