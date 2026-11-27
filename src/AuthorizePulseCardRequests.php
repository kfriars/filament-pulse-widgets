<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets;

use Kfriars\FilamentPulseWidgets\Widgets\PulseWidget;
use Laravel\Pulse\Livewire\Card;
use Livewire\ComponentHook;
use Livewire\Mechanisms\HandleComponents\ComponentContext;

/**
 * Checks a placement's access rules (its Pulse widget's class rules and the
 * placement's abilities) on every request its Pulse card makes itself: its
 * polls, sorting and type changes, which never pass through the Pulse widget.
 * The Pulse card's snapshot memo records which placement it's in, and whether
 * the placement shows a period selector; Livewire's checksum keeps the browser
 * from changing it.
 *
 * @internal
 */
final class AuthorizePulseCardRequests extends ComponentHook
{
    public const MEMO_KEY = 'pulseWidget';

    public function skip(): bool
    {
        // Skipping saves a hook on every other component. Never skipping would change nothing else:
        // only a Pulse card can be in a Pulse widget, which PulseWidget::mount() makes sure of.
        return ! $this->component instanceof Card; // @pest-mutate-ignore: InstanceOfToTrue
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function mount(array $params, mixed $parent): void
    {
        if ($parent instanceof PulseWidget) {
            $this->storeSet(self::MEMO_KEY, [
                'class' => $parent::class,
                'placementAbilities' => $parent->placementAbilities,
                'hasPeriodSelector' => $parent->hasPeriodSelector !== false,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $memo
     */
    public function hydrate(array $memo): void
    {
        /** @var array{class: class-string<PulseWidget>, placementAbilities: list<string>, hasPeriodSelector: bool} | null $pulseWidget */
        $pulseWidget = $memo[self::MEMO_KEY] ?? null;

        if ($pulseWidget === null) {
            return;
        }

        abort_unless($pulseWidget['class']::canViewPlacement($pulseWidget['placementAbilities']), 403);

        $this->storeSet(self::MEMO_KEY, $pulseWidget);
    }

    public function dehydrate(ComponentContext $context): void
    {
        $pulseWidget = $this->storeGet(self::MEMO_KEY);

        if ($pulseWidget !== null) {
            $context->addMemo(self::MEMO_KEY, $pulseWidget);
        }
    }
}
