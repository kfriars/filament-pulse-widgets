<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\ComponentHookRegistry;
use Livewire\Livewire;
use Workbench\App\Models\User;
use Workbench\App\Prototype\PulseWidgetShape\ActivityCard;
use Workbench\App\Prototype\PulseWidgetShape\Contain\AuthorizeNestedPulseCards;
use Workbench\App\Prototype\PulseWidgetShape\PrototypeDashboard;
use Workbench\App\Pulse\WorkbenchServers;

use function Orchestra\Testbench\package_path;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PROTOTYPE: Livewire wires component hooks up in its own boot(), so register before that.
        ComponentHookRegistry::register(AuthorizeNestedPulseCards::class);
    }

    public function boot(): void
    {
        // Pulse only publishes its migration, so load it straight from the package.
        $this->loadMigrationsFrom(package_path('vendor/laravel/pulse/database/migrations'));

        // PROTOTYPE: the switcher's gate button flips this for the session.
        Gate::define('viewPulse', fn (User $user): bool => ! session('prototype.deny-pulse'));

        $this->bootPulseWidgetShapePrototype();

        // Keep the workbench's servers live on the Servers card; Pulse ingests at the end of the request.
        if (! $this->app->runningInConsole()) {
            $this->app->booted(fn () => $this->app->make(WorkbenchServers::class)->report(CarbonImmutable::now()));
        }
    }

    /**
     * PROTOTYPE (throwaway): "Should a Pulse widget contain a Pulse card or be one?".
     */
    protected function bootPulseWidgetShapePrototype(): void
    {
        $this->loadViewsFrom(package_path('workbench/resources/views/prototype'), 'prototype');

        Livewire::component('workbench.activity', ActivityCard::class);

        Route::middleware(['web', 'auth'])->get('/prototype/toggle-pulse-gate', function () {
            session(['prototype.deny-pulse' => ! session('prototype.deny-pulse')]);

            return ['denied' => session('prototype.deny-pulse')];
        });

        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, function () {
            $css = (string) file_get_contents(package_path('vendor/laravel/pulse/dist/pulse.css'));

            // @keyframes can't sit inside @scope, so keep them global.
            preg_match_all('/@keyframes\s+[\w-]+\s*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', $css, $keyframes);
            $css = (string) preg_replace('/@keyframes\s+[\w-]+\s*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $css);

            return view('prototype::assets', [
                'css' => implode('', $keyframes[0]).'@scope (.pulse-scope) {'.$css.'}',
                'js' => file_get_contents(package_path('vendor/laravel/pulse/dist/pulse.js')),
            ]);
        });

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('prototype::switcher'),
            scopes: PrototypeDashboard::class,
        );
    }
}
