<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetPeriodSelector;

use Filament\Widgets\Widget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Locked;

/**
 * PROTOTYPE (throwaway): the "contain" Pulse widget, plus the period it hands its Pulse card.
 *
 * The period lives here, not in the card. A pick in the card's header calls `$parent.setPeriod()`, this
 * widget re-renders, and the card's key changes with the period, so Livewire mounts a fresh card with
 * the new `period` prop in the same request. The card's own `#[Url] $period` is never set from the
 * browser, so nothing writes `?period=` into the address bar.
 */
abstract class PulseWidget extends Widget
{
    /**
     * The nested Pulse card: a Livewire component name or class.
     */
    protected string $card;

    /**
     * Whether the card's header gets a period selector. Pulse cards without Pulse's card header never do.
     */
    protected bool $periodSelector = true;

    protected string $view = 'prototype-period-selector::pulse-widget';

    /**
     * The developer-set period the selector starts at, on every page load.
     */
    protected string $defaultPeriod = '1_hour';

    /**
     * Only setPeriod() changes it.
     */
    #[Locked]
    public string $period;

    public function mount(): void
    {
        $this->period = $this->defaultPeriod;
    }

    public function setPeriod(string $period): void
    {
        if (in_array($period, PeriodSelector::PERIODS, true)) {
            $this->period = $period;
        }
    }

    public function hasPeriodSelector(): bool
    {
        return $this->periodSelector;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'card' => $this->card,
            'cardCss' => $this->getCardCss(),
        ];
    }

    /**
     * The nested card's css(), rendered the way Pulse::css() renders it: a path is
     * inlined in a <style> tag, an Htmlable is output as-is.
     */
    protected function getCardCss(): string
    {
        /** @var class-string<Card> $class */
        $class = app('livewire.factory')->resolveComponentClass($this->card);

        // css() is protected: Pulse calls it from Card::dehydrate() and nowhere else.
        $css = (fn () => $this->css())->call(new $class);

        return collect(Arr::wrap($css))
            ->map(fn (string|Htmlable $item): string => $item instanceof Htmlable
                ? $item->toHtml()
                : '<style>'.file_get_contents($item).'</style>')
            ->implode('');
    }
}
