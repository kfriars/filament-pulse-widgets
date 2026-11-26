<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets;

use Laravel\Pulse\Livewire\Card;
use Livewire\Mechanisms\DataStore;

/**
 * What Pulse's card header needs to know to show a period selector.
 */
final class PeriodSelector
{
    /**
     * The period to show in the header of the Livewire component being rendered,
     * or null when its header shows no period selector. Only a Pulse card in a
     * Pulse widget whose placement shows one gets one.
     */
    public static function periodOf(mixed $component): ?PulsePeriod
    {
        if (! $component instanceof Card) {
            return null;
        }

        /** @var array{hasPeriodSelector: bool} | null $pulseWidget */
        $pulseWidget = app(DataStore::class)->get($component, AuthorizePulseCardRequests::MEMO_KEY);

        if (! ($pulseWidget['hasPeriodSelector'] ?? false)) {
            return null;
        }

        // Pulse shows the past hour for a period it doesn't know (a stray `?period=`), or none.
        // Any other string that isn't a period would fall back the same way as the empty one.
        return PulsePeriod::tryFrom($component->period ?? '') ?? PulsePeriod::OneHour; // @pest-mutate-ignore: EmptyStringToNotEmpty
    }
}
