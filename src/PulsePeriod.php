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
}
