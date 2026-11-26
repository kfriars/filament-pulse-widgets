<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets;

use Livewire\ComponentHookRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentPulseWidgetsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-pulse-widgets')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        // Livewire boots its component hooks along with itself, so this can't wait for `boot()`.
        ComponentHookRegistry::register(AuthorizePulseCardRequests::class);
    }
}
