<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape\Be;

use Filament\Support\Concerns\CanBeLazyLoaded;
use Filament\Widgets\Concerns\CanAuthorizeAccess;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Reactive;

/**
 * PROTOTYPE (throwaway), shape "be": a Pulse card subclass that also answers
 * Filament's widget contract.
 *
 * A trait, not a base class, because every Pulse widget must extend a
 * different Pulse card (`Usage`, `Servers`, an app's own card...). Two
 * consequences show up below:
 *  - a trait property can't be redeclared with another default by the class
 *    using it, so per-widget settings (column span, sort) go through
 *    overridable methods or Pulse's own `cols` prop, not properties;
 *  - `render()` has to wrap the Pulse card's view, because Filament's grid
 *    placement must sit on the widget's root element.
 */
trait InteractsAsPulseWidget
{
    use CanAuthorizeAccess;
    use CanBeLazyLoaded;

    /**
     * @var array<string, mixed>|null
     */
    #[Reactive]
    public ?array $pageFilters = null;

    public static function canView(): bool
    {
        return Gate::allows('viewPulse');
    }

    public static function getSort(): int
    {
        return -1;
    }

    public static function isDiscovered(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDefaultProperties(): array
    {
        return static::isLazyLoaded() ? ['lazy' => true] : [];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function make(array $properties = []): WidgetConfiguration
    {
        return app(WidgetConfiguration::class, ['widget' => static::class, 'properties' => $properties]);
    }

    /**
     * Pulse's own `cols` prop doubles as the Filament column span.
     *
     * @return int|string|array<string, int|null>
     */
    public function getColumnSpan(): int|string|array
    {
        return $this->cols ?? 1;
    }

    /**
     * @return int|string|array<string, int|null>
     */
    public function getColumnStart(): int|string|array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlaceholderData(): array
    {
        return [
            'columnSpan' => $this->getColumnSpan(),
            'columnStart' => $this->getColumnStart(),
        ];
    }

    public function render(): View
    {
        // The card is its own component, so a page filter can just overwrite its period.
        if ($period = $this->pageFilters['period'] ?? null) {
            $this->period = $period;
        }

        /** @var Renderable&View $cardView */
        $cardView = parent::render();

        // Pass the name and data, not the View: Laravel renders a nested View to a string when gathering data.
        return view('prototype::be.pulse-widget', [
            'cardViewName' => $cardView->name(),
            'cardViewData' => $cardView->getData(),
        ]);
    }
}
