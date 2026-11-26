<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

use function Orchestra\Testbench\package_path;

/**
 * Lets Laravel Boost work on this package rather than on Testbench's skeleton.
 *
 * Boost reads and writes everything relative to base_path(), which under
 * Testbench is the Laravel skeleton in vendor/, and upstream doesn't support
 * packages (laravel/boost#746, laravel/boost#855). While a boost: or mcp:
 * command runs, the base path moves to the package root, the app path to
 * src/, and every other path stays on the skeleton.
 */
class BoostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->runningBoostCommand()) {
            return;
        }

        /** @var Application $app */
        $app = $this->app;

        $skeletonPaths = [
            'useStoragePath' => $app->storagePath(),
            'useConfigPath' => $app->configPath(),
            'useDatabasePath' => $app->databasePath(),
            'useBootstrapPath' => $app->bootstrapPath(),
            'useLangPath' => $app->langPath(),
            'usePublicPath' => $app->publicPath(),
        ];

        $app->setBasePath(package_path());

        foreach ($skeletonPaths as $method => $path) {
            $app->{$method}($path);
        }

        $app->useAppPath(package_path('src'));

        // This is a package: Laravel Cloud deployment guidance doesn't apply.
        config(['boost.guidelines.exclude' => ['deployments']]);
    }

    protected function runningBoostCommand(): bool
    {
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            return false;
        }

        /** @var list<string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        $command = collect(array_slice($argv, 1))->first(fn (string $token) => ! str_starts_with($token, '-')) ?? '';

        return str_starts_with($command, 'boost:') || str_starts_with($command, 'mcp:');
    }
}
