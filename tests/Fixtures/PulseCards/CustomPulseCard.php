<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Tests\Fixtures\PulseCards;

use Illuminate\Contracts\Support\Htmlable;
use Laravel\Pulse\Livewire\Card;

/**
 * A custom Pulse card whose `css()` returns whatever a test sets.
 */
class CustomPulseCard extends Card
{
    /**
     * What `css()` returns.
     *
     * @var string | Htmlable | list<string | Htmlable> | null
     */
    public static string|Htmlable|array|null $css = null;

    public function render(): string
    {
        return '<div>A custom Pulse card</div>';
    }

    /**
     * @return string | Htmlable | list<string | Htmlable> | null
     */
    protected function css(): string|Htmlable|array|null
    {
        return static::$css;
    }
}
