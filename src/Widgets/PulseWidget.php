<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Widgets;

use Filament\Facades\Filament;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Kfriars\FilamentPulseWidgets\FilamentPulseWidgetsPlugin;
use Kfriars\FilamentPulseWidgets\PulsePeriod;
use Laravel\Pulse\Livewire\Card;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use LogicException;

/**
 * A widget that contains one Pulse card, unchanged.
 *
 * Every public property is an option a placement sets, and only the period can
 * change after that.
 */
class PulseWidget extends Widget
{
    /**
     * The Pulse card: a Pulse card class, or the name it's registered under.
     */
    public string $card;

    /**
     * Props passed straight to the Pulse card.
     *
     * @var array<string, mixed>
     */
    public array $cardProperties = [];

    /**
     * The placement's column span, which wins over the class's `$columnSpan`.
     *
     * @var int | string | array<string, int | null> | null
     */
    public int|string|array|null $placementColumnSpan = null;

    /**
     * Whether the Pulse card expands to fit its content instead of scrolling. Unset leaves it to the Pulse card.
     */
    public ?bool $expand = null;

    /**
     * The period the Pulse card shows. It isn't locked, because the period selector changes it.
     */
    public PulsePeriod $period = PulsePeriod::OneHour;

    /**
     * Whether the Pulse widget shows a period selector. Unset shows one.
     */
    public ?bool $hasPeriodSelector = null;

    /**
     * Gate abilities the user needs, every one of them, to see this placement.
     *
     * @var list<string>
     */
    public array $placementAbilities = [];

    /**
     * Whether the placement was hidden while the page rendered. A hidden placement
     * renders nothing. Unset, it wasn't.
     */
    public ?bool $isPlacementHidden = null;

    protected string $view = 'filament-pulse-widgets::pulse-widget';

    /**
     * @param  array<string, mixed>  $properties
     */
    #[Unexposed]
    public static function make(array $properties = []): PulseWidgetConfiguration
    {
        return app(PulseWidgetConfiguration::class, ['widget' => static::class, 'properties' => $properties]);
    }

    /**
     * Who can see this Pulse widget: by default, whoever can view Pulse.
     * The panel's plugin can set other rules.
     */
    #[Unexposed]
    public static function canView(): bool
    {
        // Every panel that shows Pulse widgets needs the plugin.
        return FilamentPulseWidgetsPlugin::get()->canView(static::class);
    }

    /**
     * Whether the panel's signed-in user can see a placement of this Pulse widget
     * that needs the given abilities.
     *
     * @param  list<string>  $placementAbilities
     */
    #[Unexposed]
    public static function canViewPlacement(array $placementAbilities): bool
    {
        return static::canView() && Gate::forUser(Filament::auth()->user())->check($placementAbilities);
    }

    public function mount(): void
    {
        $pulseWidget = static::class;

        if (! isset($this->card)) {
            throw new LogicException("Pulse widget [{$pulseWidget}] has no Pulse card. Give it one with ->card().");
        }

        // Only Pulse cards have their own requests checked against the Pulse widget's access rules.
        if (! Livewire::new($this->card) instanceof Card) {
            throw new LogicException("Pulse widget [{$pulseWidget}] can only contain a Pulse card, and [{$this->card}] does not extend [".Card::class.'].');
        }
    }

    /**
     * @return int | string | array<string, int | null>
     */
    #[Unexposed]
    public function getColumnSpan(): int|string|array
    {
        return $this->placementColumnSpan ?? parent::getColumnSpan();
    }

    /**
     * A render can mount the Pulse card (on the first render, the lazy load, or a
     * new period), so each one checks the placement's rules first. A hidden
     * placement renders no Pulse card, so it has nothing to check.
     */
    #[Unexposed]
    public function render(): View
    {
        if ($this->isPlacementHidden !== true) {
            abort_unless(static::canViewPlacement($this->placementAbilities), 403);
        }

        return parent::render();
    }

    /**
     * Keep every option but the period out of the browser's reach. `#[Locked]`
     * can't do this: PHP drops it when a subclass redeclares a property to give
     * it a default.
     */
    public function updating(string $property): void
    {
        if ($property !== 'period') {
            throw new CannotUpdateLockedPropertyException($property);
        }
    }

    /**
     * The Pulse card's own options, under the Pulse card's names for them. Unset options are null.
     *
     * @return array<string, mixed>
     */
    protected function getCardOptions(): array
    {
        return [
            'expand' => $this->expand,
        ];
    }

    /**
     * View data can't share a name with a public property, which would replace it.
     *
     * @return array{pulseCard: string, pulseCardProperties: array<string, mixed>, pulseCardKey: string}
     */
    protected function getViewData(): array
    {
        return [
            'pulseCard' => $this->card,
            'pulseCardProperties' => [
                ...$this->cardProperties,
                ...array_filter($this->getCardOptions(), fn (mixed $option): bool => $option !== null),
                'period' => $this->period->value,
                // The Pulse widget is already lazy-loaded; this stops the Pulse card loading lazily a second time.
                'lazy' => false,
            ],
            // A Pulse card reads its period only when it mounts, so a new period needs a new key.
            'pulseCardKey' => "pulse-card-{$this->period->value}",
        ];
    }
}
