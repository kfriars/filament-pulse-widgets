<?php

declare(strict_types=1);

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Assets\AssetManager;
use Filament\Support\Facades\FilamentAsset;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Testing\TestResponse;
use Kfriars\FilamentPulseWidgets\FilamentPulseWidgetsPlugin;
use Kfriars\FilamentPulseWidgets\Tests\TestCase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\postJson;

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
 * The assets a panel puts on its pages, as Filament registers them when the panel
 * boots. Only this panel's: in the console, Filament has already registered every
 * panel's assets, so they're gathered afresh.
 */
function assetsRegisteredBy(Panel $panel): AssetManager
{
    app()->instance(AssetManager::class, $assets = new AssetManager);
    FilamentAsset::clearResolvedInstance(AssetManager::class);

    $panel->registerAssets();

    return $assets;
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
 * Mount a placement's Pulse widget exactly as Filament does, lazy loading included.
 *
 * @return Testable<Component>
 */
function mountPlacementLazily(WidgetConfiguration $placement): Testable
{
    return Livewire::test($placement->widget, [...$placement->widget::getDefaultProperties(), ...$placement->getProperties()]);
}

/**
 * The Livewire components in a rendered component, outermost first, as the browser receives them.
 *
 * @param  Testable<Component>  $rendered
 * @return list<array{id: string, name: string, key: string, isLoaded: bool, properties: array<string, mixed>, snapshot: string}>
 */
function renderedComponents(Testable $rendered): array
{
    $document = new DOMDocument;
    $document->loadHTML((string) $rendered->html(), LIBXML_NOERROR);

    $elements = (new DOMXPath($document))->query('//*[@*[name()="wire:snapshot"]]');

    $components = [];

    foreach ($elements === false ? [] : $elements as $element) {
        if (! $element instanceof DOMElement) {
            continue;
        }

        /** @var array{data: array<string, mixed>, memo: array{name: string, lazyLoaded?: bool}} $snapshot */
        $snapshot = json_decode($element->getAttribute('wire:snapshot'), associative: true, flags: JSON_THROW_ON_ERROR);

        $components[] = [
            'id' => $element->getAttribute('wire:id'),
            'name' => $snapshot['memo']['name'],
            'key' => $element->getAttribute('wire:key'),
            'isLoaded' => ($snapshot['memo']['lazyLoaded'] ?? true) !== false,
            'properties' => $snapshot['data'],
            'snapshot' => $element->getAttribute('wire:snapshot'),
        ];
    }

    return $components;
}

/**
 * The elements in a rendered component that a CSS selector matches, the way a
 * stylesheet's rule would select them.
 *
 * @param  Testable<Component>  $rendered
 * @return list<DOMElement>
 */
function elementsMatching(Testable $rendered, string $selector): array
{
    $document = new DOMDocument;
    $document->loadHTML((string) $rendered->html(), LIBXML_NOERROR);

    $elements = (new DOMXPath($document))->query((new CssSelectorConverter)->toXPath($selector));

    return array_values(array_filter(
        iterator_to_array($elements === false ? [] : $elements),
        fn (mixed $element): bool => $element instanceof DOMElement,
    ));
}

/**
 * The Pulse card nested in a rendered Pulse widget, as the browser receives it.
 *
 * @param  Testable<Component>  $pulseWidget
 * @return array{id: string, name: string, key: string, isLoaded: bool, properties: array<string, mixed>, snapshot: string}
 */
function nestedPulseCard(Testable $pulseWidget): array
{
    $nested = array_values(array_filter(
        renderedComponents($pulseWidget),
        fn (array $component): bool => $component['id'] !== $pulseWidget->id(),
    ));

    expect($nested)->toHaveCount(1, 'A Pulse widget nests exactly one Pulse card.');

    return $nested[0];
}

/**
 * Send a Pulse card's poll to Livewire's update endpoint, the way the browser
 * does every 5 seconds. Pass the response to the last poll to poll again.
 *
 * @param  string | TestResponse<Response>  $pulseCard  the Pulse card's snapshot, or the response to its last poll
 * @return TestResponse<Response>
 */
function pollPulseCard(string|TestResponse $pulseCard): TestResponse
{
    $snapshot = $pulseCard instanceof TestResponse ? $pulseCard->json('components.0.snapshot') : $pulseCard;

    assert(is_string($snapshot));

    return postJson(route('default-livewire.update'), [
        'components' => [
            ['snapshot' => $snapshot, 'updates' => [], 'calls' => [['method' => '$refresh', 'params' => [], 'metadata' => []]]],
        ],
    ], ['X-Livewire' => 'true']);
}
