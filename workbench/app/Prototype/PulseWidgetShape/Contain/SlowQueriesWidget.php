<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape\Contain;

/**
 * PROTOTYPE (throwaway), shape "contain": everything a ready-made Pulse widget needs.
 */
class SlowQueriesWidget extends PulseWidget
{
    protected string $card = 'pulse.slow-queries';

    protected int|string|array $columnSpan = 1;
}
