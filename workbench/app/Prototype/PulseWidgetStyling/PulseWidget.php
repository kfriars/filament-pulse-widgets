<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetStyling;

use Filament\Widgets\Widget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Laravel\Pulse\Livewire\Card;

/**
 * PROTOTYPE (throwaway): the "contain" Pulse widget decided in "Should a Pulse
 * widget contain a Pulse card or be one?", reduced to what styling needs.
 *
 * Its wrapper carries `.fi-pulse-widget`, the one class every variant's CSS is
 * scoped to. It also delivers a custom Pulse card's own `css()`: Pulse only
 * collects that on full-page requests and only emits it from its own layout, so
 * inside Filament it would otherwise be silently dropped.
 */
abstract class PulseWidget extends Widget
{
    /**
     * The nested Pulse card: a Livewire component name or class.
     */
    protected string $card;

    protected string $view = 'prototype-styling::pulse-widget';

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
