<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetPeriodSelector;

use Filament\Pages\Dashboard;

/**
 * PROTOTYPE (throwaway) for "How does a Pulse widget's period selector sit seamlessly in its Pulse card?".
 *
 * Four variants of the period selector on the existing workbench dashboard, switchable via `?variant=`.
 * The Pulse widgets and their unchanged Pulse cards are the same in every variant; only the markup the
 * swapped `<x-pulse::card-header>` adds changes:
 *
 * - inline:  the header's "past hour" detail becomes the control (a native select laid over the words).
 * - select:  a `<x-pulse::select>` labelled "Period" beside the card's own controls, like "Sort by".
 * - buttons: Pulse's own "1h 6h 24h 7d" buttons, sized down into the header.
 * - menu:    a Filament gray button opening a Filament dropdown.
 *
 * `?columns=` sets the dashboard's grid (narrow column spans) and `?locale=` the app locale.
 */
class PrototypeDashboard extends Dashboard
{
    public const VARIANTS = [
        'inline' => '(i) "past hour" becomes the control',
        'select' => '(ii) Pulse select beside the card\'s controls',
        'buttons' => '(iii) Pulse\'s 1h 6h 24h 7d buttons',
        'menu' => '(iv) Filament button + dropdown',
    ];

    public const COLUMNS = [2, 3, 4];

    public const LOCALES = ['en', 'fr'];

    /**
     * Prototype state lives in the page's query string. Livewire's own requests (lazy load, polls, period
     * changes) don't carry it, so fall back to the page they came from, the way Livewire's #[Url] does.
     */
    public static function param(string $key): ?string
    {
        $value = request()->query($key);

        if ($value === null && is_string($referer = request()->headers->get('referer'))) {
            parse_str((string) parse_url($referer, PHP_URL_QUERY), $query);
            $value = $query[$key] ?? null;
        }

        return is_string($value) ? $value : null;
    }

    public static function variant(): string
    {
        $variant = self::param('variant');

        return $variant !== null && array_key_exists($variant, self::VARIANTS) ? $variant : 'inline';
    }

    public static function columns(): int
    {
        $columns = (int) self::param('columns');

        return in_array($columns, self::COLUMNS, true) ? $columns : 2;
    }

    public static function locale(): string
    {
        $locale = self::param('locale');

        return in_array($locale, self::LOCALES, true) ? $locale : 'en';
    }

    public function getColumns(): int
    {
        return self::columns();
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            Widgets\UsageWidget::class,
            Widgets\SlowQueriesWidget::class,
            Widgets\CacheWidget::class,
            Widgets\ActivityWidget::class,
            Widgets\ServersWidget::class,
        ];
    }
}
