<?php

declare(strict_types=1);

namespace Workbench\App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Theme;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Workbench\App\Prototype\PulseWidgetStyling\PrototypeDashboard;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // PROTOTYPE: variant "theme" swaps Filament's default theme for a custom one. Resolved at
            // render time, because panels are configured before the real request is bound.
            ->theme(Theme::make('app')->html(fn (): Htmlable => PrototypeDashboard::variant() === 'theme'
                ? new HtmlString('<link rel="stylesheet" href="/prototype-styling/css/theme.css" data-navigate-track />')
                : FilamentAsset::getTheme('app')->getHtml()))
            // PROTOTYPE: ?gray= swaps the panel's grey palette.
            ->colors(fn (): array => [
                'primary' => Color::Amber,
                'gray' => PrototypeDashboard::GRAYS[PrototypeDashboard::grayName()],
            ])
            ->pages([
                // PROTOTYPE: ?variant=theme|native|pulse on the dashboard.
                PrototypeDashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
