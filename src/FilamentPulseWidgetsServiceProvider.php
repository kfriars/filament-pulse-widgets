<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets;

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
}
