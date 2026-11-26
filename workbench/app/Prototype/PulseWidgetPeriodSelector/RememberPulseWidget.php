<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetPeriodSelector;

use Laravel\Pulse\Livewire\Card;
use Livewire\ComponentHook;

/**
 * PROTOTYPE (throwaway): lets a Pulse card's header know, on every request the card makes, that it sits
 * in a Pulse widget and whether that Pulse widget shows a period selector.
 *
 * The card's own requests (5s polls, sort and type changes) re-render its header without the Pulse
 * widget, so this can't be read from the parent. It's recorded once, when the Pulse widget mounts the
 * card, in the card's signed snapshot memo: the same place the access hook from "Should a Pulse widget
 * contain a Pulse card or be one?" keeps the Pulse widget's class.
 */
class RememberPulseWidget extends ComponentHook
{
    public function skip(): bool
    {
        return ! $this->component instanceof Card;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function mount(array $params, mixed $parent): void
    {
        if ($parent instanceof PulseWidget) {
            $this->storeSet('pulseWidget', [
                'class' => $parent::class,
                'periodSelector' => $parent->hasPeriodSelector(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $memo
     */
    public function hydrate(array $memo): void
    {
        if (isset($memo['pulseWidget'])) {
            $this->storeSet('pulseWidget', $memo['pulseWidget']);
        }
    }

    public function dehydrate(mixed $context): void
    {
        if ($this->storeHas('pulseWidget')) {
            $context->addMemo('pulseWidget', $this->storeGet('pulseWidget'));
        }
    }
}
