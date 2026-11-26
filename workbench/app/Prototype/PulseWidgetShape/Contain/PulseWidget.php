<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape\Contain;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/**
 * PROTOTYPE (throwaway), shape "contain": a real Filament widget whose view
 * nests the stock Pulse card's Livewire component by its public name.
 *
 * Everything Filament knows about (canView, sort, column span, lazy loading,
 * page filters) lives here; the Pulse card inside is untouched.
 */
abstract class PulseWidget extends Widget
{
    use InteractsWithPageFilters;

    /**
     * The nested Pulse card: a Livewire component name or class.
     */
    protected string $card;

    /**
     * Props passed straight to the Pulse card (`type`, `expand`, `ignore-after`, ...).
     *
     * @var array<string, mixed>
     */
    public array $cardProps = [];

    protected string $view = 'prototype::contain.pulse-widget';

    public static function canView(): bool
    {
        return Gate::allows('viewPulse');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->pageFilters['period'] ?? null;

        return [
            'card' => $this->card,
            'cardParams' => [
                ...$this->cardProps,
                ...($period ? ['period' => $period] : []),
                // The widget is already lazy (Filament's placeholder); don't lazy-load twice.
                'lazy' => false,
            ],
            // A new key remounts the Pulse card, which is the only way its period changes.
            'cardKey' => $this->getId().'-card-'.($period ?? 'url'),
        ];
    }
}
