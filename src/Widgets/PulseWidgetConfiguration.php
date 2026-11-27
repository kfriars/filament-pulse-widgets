<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Widgets;

use Closure;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Arr;
use Kfriars\FilamentPulseWidgets\PulsePeriod;

/**
 * One placement of a Pulse widget, configured fluently. Each option sets a
 * public property of the Pulse widget, so a typo fails instead of becoming an
 * HTML attribute.
 */
class PulseWidgetConfiguration extends WidgetConfiguration
{
    /**
     * Unset, the placement is visible.
     *
     * @internal
     */
    protected bool|Closure|null $isVisible = null;

    /**
     * Unset, the placement isn't hidden.
     *
     * @internal
     */
    protected bool|Closure|null $isHidden = null;

    /**
     * Gate abilities the user needs, every one of them, to see this placement. They
     * narrow what the Pulse widget's class allows, and are checked while the page
     * renders and again on every request the Pulse card makes.
     *
     * @param  string | list<string>  $abilities
     */
    public function authorize(string|array $abilities): static
    {
        $this->properties['placementAbilities'] = Arr::wrap($abilities);

        return $this;
    }

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
     * Hide the placement. It's decided once, while the page renders, so it's
     * for layout, not security: use `->authorize()` for that.
     */
    public function hidden(bool|Closure $condition = true): static
    {
        $this->isHidden = $condition;

        return $this;
    }

    /**
     * Show the placement only when the condition holds. It's decided once, while
     * the page renders, so it's for layout, not security: use `->authorize()` for that.
     */
    public function visible(bool|Closure $condition = true): static
    {
        $this->isVisible = $condition;

        return $this;
    }

    /**
     * Filament reads a placement's properties while the page renders, which is
     * when its visibility and abilities are first checked. A placement that's
     * hidden, or whose abilities the user lacks, isn't lazy-loaded, so it leaves
     * no placeholder behind and never loads its Pulse card.
     *
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        $properties = parent::getProperties();

        /** @var class-string<PulseWidget> $pulseWidget */
        $pulseWidget = $this->widget;

        /** @var list<string> $placementAbilities */
        $placementAbilities = $properties['placementAbilities'] ?? [];

        if (value($this->isHidden) === true || value($this->isVisible) === false || ! $pulseWidget::canViewPlacement($placementAbilities)) {
            return [...$properties, 'isPlacementHidden' => true, 'lazy' => false];
        }

        return $properties;
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
