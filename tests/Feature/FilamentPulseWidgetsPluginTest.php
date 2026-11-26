<?php

declare(strict_types=1);

use Filament\Contracts\Plugin;
use Filament\Panel;
use Kfriars\FilamentPulseWidgets\FilamentPulseWidgetsPlugin;
use Kfriars\FilamentPulseWidgets\Widgets\PulseUsageWidget;

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
