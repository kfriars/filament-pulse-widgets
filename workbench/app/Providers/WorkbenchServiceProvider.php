<?php

namespace Workbench\App\Providers;

use Carbon\CarbonImmutable;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\PulseSeeder;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Pulse only publishes its migration, so load it straight from the package.
        $this->loadMigrationsFrom(InstalledVersions::getInstallPath('laravel/pulse').'/database/migrations');

        Gate::define('viewPulse', fn (User $user) => true);

        // Keep the seeded servers live on the Servers card; Pulse ingests at the end of the request.
        if (! $this->app->runningInConsole()) {
            $this->app->booted(fn () => (new PulseSeeder)->reportServers(CarbonImmutable::now()));
        }
    }
}
