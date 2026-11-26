<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Widgets;

use Filament\Widgets\WidgetConfiguration;
use Kfriars\FilamentPulseWidgets\PulsePeriod;

/**
 * One placement of a Pulse widget, configured fluently. Each option sets a
 * public property of the Pulse widget, so a typo fails instead of becoming an
 * HTML attribute.
 */
class PulseWidgetConfiguration extends WidgetConfiguration
{
    /**
     * The Pulse card to contain: a Pulse card class, or the name it's registered under.
     */
    public function card(string $card): static
    {
        $this->properties['card'] = $card;

        return $this;
    }

    /**
     * Props passed straight to the Pulse card. Every other option, and the
     * period, takes precedence over them.
     *
     * @param  array<string, mixed>  $properties
     */
    public function cardProperties(array $properties): static
    {
        $this->properties['cardProperties'] = $properties;

        return $this;
    }

    /**
     * @param  int | string | array<string, int | null>  $columnSpan
     */
    public function columnSpan(int|string|array $columnSpan): static
    {
        $this->properties['placementColumnSpan'] = $columnSpan;

        return $this;
    }

    /**
     * Expand the Pulse card to fit its content, instead of scrolling it.
     */
    public function expand(bool $condition = true): static
    {
        $this->properties['expand'] = $condition;

        return $this;
    }

    /**
     * The period the Pulse widget starts at, on every page load.
     */
    public function period(PulsePeriod $period): static
    {
        $this->properties['period'] = $period;

        return $this;
    }

    /**
     * Hide the period selector, so the Pulse widget always shows the period it's placed with.
     * Pulse cards without Pulse's card header (Servers) have no period selector anyway.
     */
    public function withoutPeriodSelector(bool $condition = true): static
    {
        $this->properties['hasPeriodSelector'] = ! $condition;

        return $this;
    }
}
