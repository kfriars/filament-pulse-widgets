<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Kfriars\FilamentPulseWidgets\FilamentPulseWidgetsPlugin;
use Kfriars\FilamentPulseWidgets\Widgets\PulseUsageWidget;

use function Orchestra\Testbench\package_path;

it('is required on every panel that shows Pulse widgets', function () {
    usePanel(plugin: null);

    mountPlacement(PulseUsageWidget::make());
})->throws('Plugin [filament-pulse-widgets] is not registered for panel [test].');

it('places no Pulse widgets itself', function () {
    expect(usePanel(FilamentPulseWidgetsPlugin::make())->getWidgets())->toBe([]);
});

it('fails when another plugin has taken its id on the panel', function () {
    usePanel(new class implements Plugin
    {
        public function getId(): string
        {
            return 'filament-pulse-widgets';
        }

        public function register(Panel $panel): void {}

        public function boot(Panel $panel): void {}
    });

    FilamentPulseWidgetsPlugin::get();
})->throws('Plugin [filament-pulse-widgets] on the current panel is not a [Kfriars\FilamentPulseWidgets\FilamentPulseWidgetsPlugin].');

it('puts the Pulse widget stylesheet on its panel', function () {
    $panel = usePanel(FilamentPulseWidgetsPlugin::make());

    $assets = assetsRegisteredBy($panel);

    expect($assets->getStyles(['kfriars/filament-pulse-widgets']))->toHaveCount(1)
        ->and(realpath($assets->getStyles()[0]->getPath() ?? ''))->toBe(package_path('resources/dist/filament-pulse-widgets.css'))
        ->and($assets->getStyleHref('filament-pulse-widgets', 'kfriars/filament-pulse-widgets'))
        ->toStartWith(asset('css/kfriars/filament-pulse-widgets/filament-pulse-widgets.css').'?v=');
});

// Under Pulse's own package name, so browsers fetch a new pulse.js whenever Pulse updates it.
it("puts the installed Pulse's own JavaScript on its panel, versioned by Pulse", function () {
    $panel = usePanel(FilamentPulseWidgetsPlugin::make());

    $assets = assetsRegisteredBy($panel);

    expect($assets->getScripts(['laravel/pulse']))->toHaveCount(1)
        ->and(realpath($assets->getScripts()[0]->getPath() ?? ''))->toBe(realpath(package_path('vendor/laravel/pulse/dist/pulse.js')))
        ->and($assets->getScriptSrc('pulse', 'laravel/pulse'))
        ->toBe(asset('js/laravel/pulse/pulse.js').'?v='.InstalledVersions::getVersion('laravel/pulse'));
});

// Pulse's JavaScript throws if it runs twice in one page, and Filament includes a panel's
// assets twice wherever requests are served from the console (Pest's browser tests among
// them). A browser runs a module once, however many times the page includes it.
it("includes Pulse's JavaScript as a module", function () {
    $panel = usePanel(FilamentPulseWidgetsPlugin::make());

    expect(assetsRegisteredBy($panel)->renderScripts(['laravel/pulse']))
        ->toMatch('/<script\s+src="'.preg_quote(asset('js/laravel/pulse/pulse.js'), '/').'\?v=[^"]+"\s+type="module"/');
});

it('puts neither the stylesheet nor Pulse\'s JavaScript on a panel without it', function () {
    $panel = usePanel(plugin: null);

    $assets = assetsRegisteredBy($panel);

    expect($assets->getStyles(['kfriars/filament-pulse-widgets']))->toBe([])
        ->and($assets->getScripts(['laravel/pulse']))->toBe([]);
});
