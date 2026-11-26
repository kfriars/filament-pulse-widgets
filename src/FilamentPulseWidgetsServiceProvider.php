<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets;

use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
use Livewire\ComponentHookRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentPulseWidgetsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-pulse-widgets')
            ->hasViews()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        // Livewire boots its component hooks along with itself, so this can't wait for `boot()`.
        ComponentHookRegistry::register(AuthorizePulseCardRequests::class);
    }

    public function packageBooted(): void
    {
        // Pulse adds its views to the `pulse` namespace when it boots, which can be after this package.
        $this->app->booted(fn () => $this->swapPulseCardHeader());
    }

    /**
     * Blade compiles `<x-pulse::card-header>` to the view `pulse::components.card-header`,
     * and finds it through Pulse's `pulse` view namespace when it renders. Putting the
     * package's directory first in that namespace swaps in the package's card header,
     * the way an app's `resources/views/vendor/pulse` overrides Pulse's views.
     *
     * The namespace as it was, an app's overrides included, stays reachable as
     * `filament-pulse-widgets-pulse`, which the package's card header renders.
     */
    protected function swapPulseCardHeader(): void
    {
        /** @var FileViewFinder $finder */
        $finder = View::getFinder();

        View::addNamespace('filament-pulse-widgets-pulse', $finder->getHints()['pulse']);
        View::prependNamespace('pulse', __DIR__.'/../resources/views/pulse');
    }
}
