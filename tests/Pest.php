<?php

declare(strict_types=1);

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Widgets\WidgetConfiguration;
use Kfriars\FilamentPulseWidgets\FilamentPulseWidgetsPlugin;
use Kfriars\FilamentPulseWidgets\Tests\TestCase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

pest()->extend(TestCase::class)->in('Feature', 'Browser');

// Pulse cards poll every 5 seconds and Pulse caches their queries for 5 seconds,
// so new data can take 10 seconds to show.
pest()->browser()->timeout(15_000);

/**
 * Register a panel for the test and make it the current one, the way Filament's
 * own middleware does on a panel's requests.
 */
function usePanel(?Plugin $plugin = new FilamentPulseWidgetsPlugin): Panel
{
    $panel = Panel::make()->id('test');

    if ($plugin !== null) {
        $panel->plugin($plugin);
    }

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    return $panel;
}

/**
 * Mount a placement's Pulse widget with the placement's options, as Filament does,
 * but without lazy loading, so the Pulse widget renders its Pulse card straight away.
 *
 * @return Testable<Component>
 */
function mountPlacement(WidgetConfiguration $placement): Testable
{
    return Livewire::test($placement->widget, $placement->getProperties());
}

/**
 * The Pulse card nested in a rendered Pulse widget, as the browser receives it.
 *
 * @param  Testable<Component>  $pulseWidget
 * @return array{name: string, key: string, isLoaded: bool, properties: array<string, mixed>}
 */
function nestedPulseCard(Testable $pulseWidget): array
{
    $document = new DOMDocument;
    $document->loadHTML($pulseWidget->html(), LIBXML_NOERROR);

    $components = new DOMXPath($document)->query('//*[@*[name()="wire:snapshot"]]');

    $nested = [];

    foreach ($components === false ? [] : $components as $component) {
        if (! $component instanceof DOMElement || $component->getAttribute('wire:id') === $pulseWidget->id()) {
            continue;
        }

        /** @var array{data: array<string, mixed>, memo: array{name: string, lazyLoaded?: bool}} $snapshot */
        $snapshot = json_decode($component->getAttribute('wire:snapshot'), associative: true, flags: JSON_THROW_ON_ERROR);

        $nested[] = [
            'name' => $snapshot['memo']['name'],
            'key' => $component->getAttribute('wire:key'),
            'isLoaded' => ($snapshot['memo']['lazyLoaded'] ?? true) !== false,
            'properties' => $snapshot['data'],
        ];
    }

    expect($nested)->toHaveCount(1, 'A Pulse widget nests exactly one Pulse card.');

    return $nested[0];
}
