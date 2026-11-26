<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetStyling\Widgets;

use Workbench\App\Prototype\PulseWidgetStyling\PulseWidget;

/**
 * PROTOTYPE (throwaway).
 */
class ServersWidget extends PulseWidget
{
    protected string $card = 'pulse.servers';

    protected int|string|array $columnSpan = 'full';
}
