<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetPeriodSelector;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Laravel\Pulse\Livewire\Card;

/**
 * PROTOTYPE (throwaway): what the swapped card header asks before it adds a period selector.
 */
final class PeriodSelector
{
    /**
     * Pulse's four periods: the only ones it stores aggregates for.
     */
    public const PERIODS = ['1_hour', '6_hours', '24_hours', '7_days'];

    /**
     * The Pulse card being rendered, if it sits in a Pulse widget that shows a period selector.
     *
     * Livewire shares the component it's rendering with every view as `$__livewire`, including the
     * anonymous card-header component inside the card's view. On /pulse that's a Pulse card with no
     * Pulse widget recorded, so Pulse's header renders untouched.
     */
    public static function cardFor(mixed $component): ?Card
    {
        if (! $component instanceof Card) {
            return null;
        }

        /** @var array{class: class-string, periodSelector: bool}|null $pulseWidget */
        $pulseWidget = \Livewire\store($component)->get('pulseWidget');

        return ($pulseWidget['periodSelector'] ?? false) ? $component : null;
    }

    /**
     * @param  'period'|'long'|'short'|'option'  $kind
     */
    public static function label(string $kind, ?string $period = null): string
    {
        $key = $period === null ? $kind : "{$kind}.{$period}";

        return __("prototype-period-selector::selector.{$key}");
    }

    /**
     * Variant "inline": the card's details with its "past hour" swapped for the control. Pulse writes the
     * period into the details string itself, so the words are found and replaced; a card that doesn't
     * mention its period gets the control appended instead.
     */
    public static function inlineDetails(Card $card, ?string $details): Htmlable
    {
        $control = view('prototype-period-selector::selector.inline', ['card' => $card])->render();

        // Pulse's header echoes the details, so they arrive as plain text and are escaped once more here.
        $details = e($details ?? '');
        $words = e('past '.$card->periodForHumans());

        return new HtmlString(match (true) {
            str_contains($details, $words) => Str::replaceLast($words, $control, $details),
            $details === '' => $control,
            default => "{$details}, {$control}",
        });
    }
}
