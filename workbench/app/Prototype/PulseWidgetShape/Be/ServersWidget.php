<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape\Be;

use Laravel\Pulse\Livewire\Servers;

/**
 * PROTOTYPE (throwaway), shape "be": a ready-made Pulse widget is a subclass of
 * Pulse's own card class, which Pulse marks `@internal`.
 */
class ServersWidget extends Servers
{
    use InteractsAsPulseWidget;

    /**
     * A different default span has to be a method, not a `$columnSpan`
     * property: a class can't redeclare a trait's property with a new default.
     *
     * @return int|string|array<string, int|null>
     */
    public function getColumnSpan(): int|string|array
    {
        return $this->cols ?? 'full';
    }
}
