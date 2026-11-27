<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets;

/**
 * The stretch of recent time a Pulse card shows data for. These are the only
 * periods Pulse stores aggregates for, backed by Pulse's own names for them.
 */
enum PulsePeriod: string
{
    case OneHour = '1_hour';
    case SixHours = '6_hours';
    case TwentyFourHours = '24_hours';
    case SevenDays = '7_days';

    /**
     * The period as the period selector's menu offers it: "1 hour".
     *
     * @internal
     */
    public function getLabel(): string
    {
        return __("filament-pulse-widgets::period-selector.option.{$this->value}");
    }

    /**
     * The period in full, as the period selector's tooltip gives it: "past hour".
     *
     * @internal
     */
    public function getLongLabel(): string
    {
        return __("filament-pulse-widgets::period-selector.long.{$this->value}");
    }

    /**
     * The period at its shortest, as the period selector's button shows it: "1h".
     *
     * @internal
     */
    public function getShortLabel(): string
    {
        return __("filament-pulse-widgets::period-selector.short.{$this->value}");
    }
}
