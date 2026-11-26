<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape\Contain;

use Workbench\App\Prototype\PulseWidgetShape\ActivityCard;

/**
 * PROTOTYPE (throwaway), shape "contain": all an app writes to turn its own
 * custom Pulse card into a Pulse widget. The card itself is unchanged.
 */
class ActivityWidget extends PulseWidget
{
    protected string $card = ActivityCard::class;
}
