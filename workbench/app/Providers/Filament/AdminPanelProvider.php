<?php

declare(strict_types=1);

namespace Workbench\App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Kfriars\FilamentPulseWidgets\FilamentPulseWidgetsPlugin;
use Kfriars\FilamentPulseWidgets\Widgets\PulseCacheWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseExceptionsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseQueuesWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseServersWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowJobsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowOutgoingRequestsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowQueriesWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowRequestsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseUsageWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidget;
use Workbench\App\Filament\Pages\Dashboard;
use Workbench\App\Livewire\Pulse\Activity;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // A second page, with Filament's own inputs, for the browser tests to navigate to and from.
            ->profile()
            // The browser tests check that Pulse's JavaScript survives SPA navigation.
            ->spa()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->plugin(FilamentPulseWidgetsPlugin::make())
            // Every ready-made Pulse widget, and a custom Pulse card, next to Filament's own widgets.
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                PulseServersWidget::class,
                PulseUsageWidget::class,
                PulseQueuesWidget::class,
                PulseCacheWidget::class,
                PulseExceptionsWidget::class,
                PulseSlowQueriesWidget::class,
                PulseSlowRequestsWidget::class,
                PulseSlowJobsWidget::class,
                PulseSlowOutgoingRequestsWidget::class,
                PulseWidget::make()->card(Activity::class),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                // PreventRequestForgery replaced VerifyCsrfToken in Laravel 13.
                class_exists(PreventRequestForgery::class) ? PreventRequestForgery::class : VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
