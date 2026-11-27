<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Assets\AssetManager;
use Filament\Support\Facades\FilamentAsset;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Kfriars\FilamentPulseWidgets\FilamentPulseWidgetsPlugin;
use Kfriars\FilamentPulseWidgets\Tests\TestCase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Pest\TestSuite;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\postJson;

pest()->extend(TestCase::class)->in('Feature', 'Browser');

// Pulse cards poll every 5 seconds and Pulse caches their queries for 5 seconds,
// so new data can take 10 seconds to show.
pest()->browser()->timeout(15_000);

/**
 * Boot the app afresh, as its next request would, for packages to find files
 * the test has published where they look only while booting. The test's panel
 * and signed-in user go with the old app.
 */
function rebootApplication(): void
{
    $testCase = TestSuite::getInstance()->test;

    assert($testCase instanceof TestCase);

    $testCase->rebootApplication();
}

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
 * Render Pulse's views from the installed Pulse alone, as if nothing else had
 * added to or changed its `pulse` view namespace.
 */
function usePulseViewsOnly(): void
{
    View::replaceNamespace('pulse', InstalledVersions::getInstallPath('laravel/pulse').'/resources/views');
    View::getFinder()->flush();
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
 * A rendered component, or HTML a Livewire request returned, as a document.
 * It's read as UTF-8, as a browser reads it: without the declaration,
 * DOMDocument would read it as ISO-8859-1 and garble every accent.
 *
 * @param  Testable<Component> | string  $rendered
 */
function htmlDocument(Testable|string $rendered): DOMDocument
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.(is_string($rendered) ? $rendered : (string) $rendered->html()), LIBXML_NOERROR);

    return $document;
}

/**
 * The Livewire components in a rendered component, outermost first, as the browser receives them.
 *
 * @param  Testable<Component>  $rendered
 * @return list<array{id: string, name: string, key: string, isLoaded: bool, properties: array<string, mixed>, snapshot: string}>
 */
function renderedComponents(Testable $rendered): array
{
    $document = htmlDocument($rendered);

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
 * The elements in a rendered component, or in HTML a Livewire request returned,
 * that a CSS selector matches, the way a stylesheet's rule would select them.
 *
 * @param  Testable<Component> | string  $rendered
 * @return list<DOMElement>
 */
function elementsMatching(Testable|string $rendered, string $selector): array
{
    $document = htmlDocument($rendered);

    $elements = (new DOMXPath($document))->query((new CssSelectorConverter)->toXPath($selector));

    return array_values(array_filter(
        iterator_to_array($elements === false ? [] : $elements),
        fn (mixed $element): bool => $element instanceof DOMElement,
    ));
}

/**
 * An element's text as a reader sees it: whitespace collapsed and trimmed.
 */
function textOf(DOMElement $element): string
{
    return trim((string) preg_replace('/\s+/', ' ', $element->textContent));
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
