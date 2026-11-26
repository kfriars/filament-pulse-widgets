<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetStyling;

use Filament\Pages\Dashboard;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

/**
 * PROTOTYPE (throwaway) for "How should Pulse widgets be styled inside a Filament v5 panel?".
 *
 * Three variants on the existing workbench dashboard, switchable via `?variant=`.
 * The widgets are identical in every variant; only the stylesheets change:
 *
 * - theme:  the panel's custom theme @source's Pulse's views and imports the package's compat CSS.
 * - native: the package's own compiled stylesheet, using the panel's greys and font (default theme).
 * - pulse:  the same package stylesheet, keeping Pulse's own greys and Figtree (default theme).
 *
 * `?gray=` swaps the panel's grey palette, to show which variants follow it.
 */
class PrototypeDashboard extends Dashboard
{
    public const VARIANTS = [
        'theme' => 'Custom theme @source + package compat CSS',
        'native' => 'Package stylesheet, panel greys + font',
        'pulse' => "Package stylesheet, Pulse's own greys + Figtree",
    ];

    public const GRAYS = [
        'zinc' => Color::Zinc,
        'slate' => Color::Slate,
        'stone' => Color::Stone,
    ];

    public static function variant(): string
    {
        $variant = request()->query('variant');

        return is_string($variant) && array_key_exists($variant, self::VARIANTS) ? $variant : 'theme';
    }

    public static function grayName(): string
    {
        $gray = request()->query('gray');

        return is_string($gray) && array_key_exists($gray, self::GRAYS) ? $gray : 'zinc';
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            FilamentInfoWidget::class,
            Widgets\ServersWidget::class,
            Widgets\UsageWidget::class,
            Widgets\ActivityWidget::class,
            Widgets\QueuesWidget::class,
            Widgets\CacheWidget::class,
            Widgets\SlowQueriesWidget::class,
            Widgets\ExceptionsWidget::class,
            Widgets\SlowRequestsWidget::class,
            Widgets\SlowJobsWidget::class,
            Widgets\SlowOutgoingRequestsWidget::class,
        ];
    }
}
