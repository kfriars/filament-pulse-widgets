<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape\Contain;

use Laravel\Pulse\Livewire\Card;
use Livewire\ComponentHook;

/**
 * PROTOTYPE (throwaway), shape "contain": the extra machinery this shape needs.
 *
 * A nested Pulse card is a stock Pulse component, so its own requests (lazy
 * load, 5s polls, sort/type changes) never pass through the Pulse widget.
 * This global Livewire hook remembers which Pulse widget mounted the card, in
 * the card's signed snapshot memo, and re-checks that widget's canView() on
 * every later request the card makes.
 */
class AuthorizeNestedPulseCards extends ComponentHook
{
    /**
     * @var class-string<PulseWidget>|null
     */
    protected ?string $pulseWidget = null;

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
            $this->pulseWidget = $parent::class;
        }
    }

    /**
     * @param  array<string, mixed>  $memo
     */
    public function hydrate(array $memo): void
    {
        $this->pulseWidget = $memo['pulseWidget'] ?? null;

        if ($this->pulseWidget !== null) {
            abort_unless($this->pulseWidget::canView(), 403);
        }
    }

    public function dehydrate(mixed $context): void
    {
        if ($this->pulseWidget !== null) {
            $context->addMemo('pulseWidget', $this->pulseWidget);
        }
    }
}
