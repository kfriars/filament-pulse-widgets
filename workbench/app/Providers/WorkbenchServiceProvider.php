<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Workbench\App\Models\User;
use Workbench\App\Prototype\PulseWidgetStyling\ActivityCard;
use Workbench\App\Prototype\PulseWidgetStyling\PrototypeDashboard;
use Workbench\App\Pulse\WorkbenchServers;

use function Orchestra\Testbench\package_path;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Pulse only publishes its migration, so load it straight from the package.
        $this->loadMigrationsFrom(package_path('vendor/laravel/pulse/database/migrations'));

        Gate::define('viewPulse', fn (User $user): bool => true);

        $this->bootPulseWidgetStylingPrototype();

        // Keep the workbench's servers live on the Servers card; Pulse ingests at the end of the request.
        if (! $this->app->runningInConsole()) {
            $this->app->booted(fn () => $this->app->make(WorkbenchServers::class)->report(CarbonImmutable::now()));
        }
    }

    /**
     * PROTOTYPE (throwaway): "How should Pulse widgets be styled inside a Filament v5 panel?".
     */
    protected function bootPulseWidgetStylingPrototype(): void
    {
        $this->loadViewsFrom(package_path('workbench/resources/views/prototype-styling'), 'prototype-styling');

        Livewire::component('workbench.activity', ActivityCard::class);

        // Serves the compiled stylesheets, standing in for a published Filament asset / an app's built theme.
        Route::get('/prototype-styling/css/{name}.css', fn (string $name) => response()->file(
            package_path("workbench/resources/css/prototype/dist/{$name}.css"),
            ['Content-Type' => 'text/css; charset=UTF-8', 'Cache-Control' => 'no-store'],
        ))->whereIn('name', ['theme', 'pulse-widgets-native', 'pulse-widgets-pulse']);

        // Variants "native" and "pulse": the package's stylesheet, loaded after Filament's default theme.
        FilamentView::registerRenderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => match (PrototypeDashboard::variant()) {
            'native' => '<link rel="stylesheet" href="/prototype-styling/css/pulse-widgets-native.css">',
            'pulse' => '<link rel="preconnect" href="https://fonts.bunny.net"><link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:300,400,500,600">'
                .'<link rel="stylesheet" href="/prototype-styling/css/pulse-widgets-pulse.css">',
            default => '',
        });

        // Every variant: Chart.js and formatDate, once per document, without Pulse::js()'s second livewire.js.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<script>'.file_get_contents(package_path('vendor/laravel/pulse/dist/pulse.js')).'</script>',
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('prototype-styling::switcher'),
            scopes: PrototypeDashboard::class,
        );
    }
}
