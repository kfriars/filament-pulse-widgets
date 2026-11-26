<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Pulse\WorkbenchServers;

use function Orchestra\Testbench\package_path;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Pulse only publishes its migration, so load it straight from the package.
        $this->loadMigrationsFrom(package_path('vendor/laravel/pulse/database/migrations'));

        $this->loadViewsFrom(package_path('workbench/resources/views'), 'workbench');

        Gate::define('viewPulse', fn (User $user): bool => true);

        // Keep the workbench's servers live on the Servers card; Pulse ingests at the end of the request.
        if (! $this->app->runningInConsole()) {
            $this->app->booted(fn () => $this->app->make(WorkbenchServers::class)->report(CarbonImmutable::now()));
        }
    }
}
