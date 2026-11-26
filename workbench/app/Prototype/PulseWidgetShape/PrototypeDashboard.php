<?php

declare(strict_types=1);

namespace Workbench\App\Prototype\PulseWidgetShape;

use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Widgets\WidgetConfiguration;
use Livewire\Attributes\Url;

/**
 * PROTOTYPE (throwaway) for "Should a Pulse widget contain a Pulse card or be one?".
 *
 * Two shapes, switchable via `?variant=contain|be` on the existing workbench
 * dashboard: three first-party Pulse cards plus one custom Pulse card, next to
 * Filament's own widgets. The period page filter is only here to show how a
 * period reaches each shape; choosing the period UI is a separate ticket.
 */
class PrototypeDashboard extends Dashboard
{
    use HasFiltersForm;

    public const VARIANTS = [
        'contain' => 'Contain: a Filament widget nests the Pulse card',
        'be' => 'Be: the Pulse card subclass is the widget',
    ];

    #[Url]
    public string $variant = 'contain';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->placeholder('Not set (falls back to ?period= or 1 hour)')
                ->options([
                    '1_hour' => '1 hour',
                    '6_hours' => '6 hours',
                    '24_hours' => '24 hours',
                    '7_days' => '7 days',
                ]),
        ]);
    }

    /**
     * @return array<class-string|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            FilamentInfoWidget::class,
            ...match ($this->variant) {
                'be' => [
                    Be\ServersWidget::class,
                    Be\UsageWidget::class,
                    Be\SlowQueriesWidget::class,
                    Be\ActivityWidget::class,
                ],
                default => [
                    Contain\ServersWidget::class,
                    Contain\UsageWidget::class,
                    Contain\SlowQueriesWidget::class,
                    Contain\ActivityWidget::class,
                ],
            },
        ];
    }
}
