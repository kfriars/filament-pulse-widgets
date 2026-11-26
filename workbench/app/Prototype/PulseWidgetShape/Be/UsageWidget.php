<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape\Be;

use Laravel\Pulse\Livewire\Usage;

/**
 * PROTOTYPE (throwaway), shape "be": a ready-made Pulse widget is a subclass of
 * Pulse's own card class, which Pulse marks `@internal`.
 */
class UsageWidget extends Usage
{
    use InteractsAsPulseWidget;
}
