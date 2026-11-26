<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape\Contain;

/**
 * PROTOTYPE (throwaway), shape "contain": everything a ready-made Pulse widget needs.
 */
class ServersWidget extends PulseWidget
{
    protected string $card = 'pulse.servers';

    protected int|string|array $columnSpan = 'full';
}
