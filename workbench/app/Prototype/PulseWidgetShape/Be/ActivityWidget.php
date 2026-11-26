<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape\Be;

use Workbench\App\Prototype\PulseWidgetShape\ActivityCard;

/**
 * PROTOTYPE (throwaway), shape "be": all an app writes to turn its own custom
 * Pulse card into a Pulse widget: a second class that extends the card.
 */
class ActivityWidget extends ActivityCard
{
    use InteractsAsPulseWidget;
}
