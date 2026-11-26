<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\ComponentHookRegistry;
use Livewire\Livewire;
use Workbench\App\Models\User;
use Workbench\App\Prototype\PulseWidgetPeriodSelector\ActivityCard;
use Workbench\App\Prototype\PulseWidgetPeriodSelector\PrototypeDashboard;
use Workbench\App\Prototype\PulseWidgetPeriodSelector\RememberPulseWidget;
use Workbench\App\Pulse\WorkbenchServers;

use function Orchestra\Testbench\package_path;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PROTOTYPE: Livewire wires component hooks up in its own boot(), so register before that.
        ComponentHookRegistry::register(RememberPulseWidget::class);
    }

    public function boot(): void
    {
        // Pulse only publishes its migration, so load it straight from the package.
        $this->loadMigrationsFrom(package_path('vendor/laravel/pulse/database/migrations'));

        Gate::define('viewPulse', fn (User $user): bool => true);

        $this->bootPulseWidgetPeriodSelectorPrototype();

        // Keep the workbench's servers live on the Servers card; Pulse ingests at the end of the request.
        if (! $this->app->runningInConsole()) {
            $this->app->booted(fn () => $this->app->make(WorkbenchServers::class)->report(CarbonImmutable::now()));
        }
    }

    /**
     * PROTOTYPE (throwaway): "How does a Pulse widget's period selector sit seamlessly in its Pulse card?".
     */
    protected function bootPulseWidgetPeriodSelectorPrototype(): void
    {
        $this->loadViewsFrom(package_path('workbench/resources/views/prototype-period-selector'), 'prototype-period-selector');
        $this->loadTranslationsFrom(package_path('workbench/lang/prototype-period-selector'), 'prototype-period-selector');

        Livewire::component('workbench.activity', ActivityCard::class);

        $this->swapPulseCardHeader();

        // ?locale= on the page, and on every Livewire request it makes (read back from the Referer).
        Event::listen(RouteMatched::class, fn () => app()->setLocale(PrototypeDashboard::locale()));

        // Serves the compiled stylesheet, standing in for the package's registered Filament asset.
        Route::get('/prototype-period-selector/css/{name}.css', fn (string $name) => response()->file(
            package_path("workbench/resources/css/prototype/dist/{$name}.css"),
            ['Content-Type' => 'text/css; charset=UTF-8', 'Cache-Control' => 'no-store'],
        ))->whereIn('name', ['pulse-widgets-native']);

        // The decided styling ("How should Pulse widgets be styled inside a Filament v5 panel?"), on the default theme.
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): string => '<link rel="stylesheet" href="/prototype-period-selector/css/pulse-widgets-native.css">',
        );

        // Chart.js and formatDate, once per document, without Pulse::js()'s second livewire.js.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<script>'.file_get_contents(package_path('vendor/laravel/pulse/dist/pulse.js')).'</script>',
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('prototype-period-selector::switcher'),
            scopes: PrototypeDashboard::class,
        );
    }

    /**
     * Getting inside an unchanged Pulse card's header: swap the view behind `<x-pulse::card-header>`.
     *
     * Blade compiles `<x-pulse::card-header>` to the view `pulse::components.card-header`, found through
     * Pulse's ordinary `pulse` view namespace when it renders (it never reaches Pulse's anonymous
     * component path). So this is Laravel's own vendor-view override, the one an app gets from
     * `resources/views/vendor/pulse`: put our directory first in the `pulse` namespace. It swaps the
     * header for every Pulse card on every request, without touching a Pulse view.
     *
     * Everything that was in the namespace before (Pulse's views, and an app's published overrides)
     * stays reachable as `<x-pulse-original::card-header>`, which the swap renders.
     */
    protected function swapPulseCardHeader(): void
    {
        View::addNamespace('pulse-original', View::getFinder()->getHints()['pulse']);

        View::prependNamespace('pulse', package_path('workbench/resources/views/prototype-period-selector/pulse'));
    }
}
