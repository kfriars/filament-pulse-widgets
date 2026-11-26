<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetPeriodSelector\Widgets;

use Workbench\App\Prototype\PulseWidgetPeriodSelector\PulseWidget;

/**
 * PROTOTYPE (throwaway).
 */
class ServersWidget extends PulseWidget
{
    protected string $card = 'pulse.servers';

    protected int|string|array $columnSpan = 'full';
}
