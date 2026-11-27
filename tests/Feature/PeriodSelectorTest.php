<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Kfriars\FilamentPulseWidgets\PulsePeriod;
use Kfriars\FilamentPulseWidgets\Widgets\PulseCacheWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseServersWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowQueriesWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseUsageWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidgetConfiguration;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Livewire\Pulse\Activity;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

// The workbench lets every signed-in user view Pulse.
beforeEach(function () {
    usePanel();

    actingAs(User::factory()->createOne());
});

it('puts a period selector in its Pulse card\'s header, showing the period', function (PulsePeriod $period, string $shown) {
    $pulseWidget = mountPlacement(PulseCacheWidget::make()->period($period));

    $triggers = elementsMatching($pulseWidget, '.fi-pulse-widget header .fi-pulse-widget-period-selector .fi-dropdown-trigger');

    expect($triggers)->toHaveCount(1)
        ->and(textOf($triggers[0]))->toBe($shown);
})->with([
    'past hour' => [PulsePeriod::OneHour, '1h'],
    'past 6 hours' => [PulsePeriod::SixHours, '6h'],
    'past 24 hours' => [PulsePeriod::TwentyFourHours, '24h'],
    'past 7 days' => [PulsePeriod::SevenDays, '7d'],
]);

// A `?period=` in the address bar beats the Pulse widget's period (a stray one is left alone in v1).
// Pulse shows the past hour for one it doesn't know.
it('shows the past hour for a period Pulse does not know, as Pulse does', function () {
    $pulseWidget = Livewire::withQueryParams(['period' => 'fortnight'])->test(PulseCacheWidget::class);

    [$trigger] = elementsMatching($pulseWidget, '.fi-pulse-widget-period-selector .fi-dropdown-trigger');

    expect(textOf($trigger))->toBe('1h');
});

it('names the period in full in its button\'s tooltip', function () {
    $pulseWidget = mountPlacement(PulseCacheWidget::make()->period(PulsePeriod::SixHours));

    [$button] = elementsMatching($pulseWidget, '.fi-pulse-widget-period-selector .fi-dropdown-trigger button');

    expect($button->getAttribute('x-tooltip'))->toContain("'past 6 hours'");
});

it('offers every period in its menu, with a check on the one shown', function () {
    $pulseWidget = mountPlacement(PulseCacheWidget::make()->period(PulsePeriod::TwentyFourHours));

    $options = elementsMatching($pulseWidget, '.fi-pulse-widget-period-selector .fi-dropdown-panel [role="group"][aria-label="Period"] [role="menuitemradio"]');

    expect(array_map(textOf(...), $options))->toBe(['1 hour', '6 hours', '24 hours', '7 days'])
        ->and(array_map(fn (DOMElement $option): string => $option->getAttribute('aria-checked'), $options))->toBe(['false', 'false', 'true', 'false'])
        ->and(array_map(fn (DOMElement $option): bool => iconOf($option) === iconOf(svg('heroicon-m-check')->toHtml()), $options))->toBe([false, false, true, false]);
});

it('shows no period selector when it is placed without one', function () {
    $pulseWidget = mountPlacement(PulseCacheWidget::make()->withoutPeriodSelector());

    expect(elementsMatching($pulseWidget, 'header'))->toHaveCount(1)
        ->and(elementsMatching($pulseWidget, '.fi-pulse-widget-period-selector'))->toBeEmpty();
});

// The Servers card, and any custom Pulse card that doesn't use `<x-pulse::card-header>`, have no header to put one in.
it('shows no period selector on a Pulse card without Pulse\'s card header', function () {
    expect(elementsMatching(mountPlacement(PulseServersWidget::make()), '.fi-pulse-widget-period-selector'))->toBeEmpty();
});

// Polls, sorting and type changes re-render the header without the Pulse widget.
it('keeps its period selector, or its lack of one, through its Pulse card\'s own requests', function (PulseWidgetConfiguration $placement, int $selectors) {
    $poll = pollPulseCard(pollPulseCard(nestedPulseCard(mountPlacement($placement))['snapshot']));

    $html = $poll->assertOk()->json('components.0.effects.html');

    assert(is_string($html));

    expect(elementsMatching($html, 'header'))->toHaveCount(1)
        ->and(elementsMatching($html, 'header .fi-pulse-widget-period-selector'))->toHaveCount($selectors);
})->with([
    'with one' => [fn () => PulseCacheWidget::make(), 1],
    'without one' => [fn () => PulseCacheWidget::make()->withoutPeriodSelector(), 0],
]);

// Pulse's own controls (Usage's type, Slow Queries' sort) stay first.
it('adds the period selector after its Pulse card\'s own controls, and nothing else to Pulse\'s header', function (PulseWidgetConfiguration $placement) {
    $withSelector = mountPlacement($placement);

    [$selector] = elementsMatching($withSelector, '.fi-pulse-widget header .fi-pulse-widget-period-selector');

    expect($selector->previousElementSibling)->not->toBeNull()
        ->and($selector->nextElementSibling)->toBeNull();

    $document = $selector->ownerDocument;

    assert($document instanceof DOMDocument);

    $selector->remove();
    $headerWithoutSelector = cardHeaders($document);

    usePulseViewsOnly();

    expect($headerWithoutSelector)->toBe(cardHeaders(mountPlacement($placement)));
})->with([
    'Usage' => fn () => PulseUsageWidget::make(),
    'Slow Queries' => fn () => PulseSlowQueriesWidget::make(),
]);

// The Pulse dashboard places Pulse cards on their own.
it('leaves the header of a Pulse card outside a Pulse widget as Pulse renders it', function (string $pulseCard) {
    $swapped = cardHeaders(Livewire::withoutLazyLoading()->test($pulseCard));

    usePulseViewsOnly();

    expect($swapped)->not->toBeEmpty()
        ->toBe(cardHeaders(Livewire::withoutLazyLoading()->test($pulseCard)));
})->with([
    'Usage' => 'pulse.usage',
    'Queues' => 'pulse.queues',
    'Cache' => 'pulse.cache',
    'Slow Queries' => 'pulse.slow-queries',
    'Exceptions' => 'pulse.exceptions',
    'Slow Requests' => 'pulse.slow-requests',
    'Slow Jobs' => 'pulse.slow-jobs',
    'Slow Outgoing Requests' => 'pulse.slow-outgoing-requests',
    'a custom Pulse card' => Activity::class,
]);

// An app can use Pulse's card header in its own views, which aren't Pulse cards.
it('leaves Pulse\'s card header outside any Pulse card as Pulse renders it', function () {
    $header = '<x-pulse::card-header name="Deploys" details="past hour"><x-slot:actions><span>v1.2.3</span></x-slot:actions></x-pulse::card-header>';

    $swapped = cardHeaders(htmlDocument(Blade::render($header)));

    usePulseViewsOnly();

    expect($swapped)->toHaveCount(1)
        ->toBe(cardHeaders(htmlDocument(Blade::render($header))));
});

it('renders an app\'s published override of Pulse\'s card header, with the period selector in a Pulse widget', function () {
    $published = resource_path('views/vendor/pulse');

    File::ensureDirectoryExists("{$published}/components");
    File::put("{$published}/components/card-header.blade.php", '<header data-app-override>{{ $name }}{{ $actions ?? \'\' }}</header>');

    try {
        // Pulse finds an app's overrides of its views when it boots.
        rebootApplication();

        usePanel();
        actingAs(User::factory()->createOne());

        expect(elementsMatching(Livewire::withoutLazyLoading()->test('pulse.cache'), 'header[data-app-override]'))->toHaveCount(1)
            ->and(elementsMatching(mountPlacement(PulseCacheWidget::make()), 'header[data-app-override] .fi-pulse-widget-period-selector'))->toHaveCount(1);
    } finally {
        removeFromApp($published);
    }
});

// An app translates a package's lines, or changes them, in lang/vendor/<package>.
it('labels the period selector in the app\'s language', function () {
    $translations = lang_path('vendor/filament-pulse-widgets');

    File::ensureDirectoryExists("{$translations}/fr");
    File::put("{$translations}/fr/period-selector.php", '<?php return '.var_export([
        'period' => 'Période',
        'long' => ['6_hours' => 'depuis 6 heures'],
        'short' => ['6_hours' => '6 h'],
        'option' => ['1_hour' => '1 heure', '6_hours' => '6 heures', '24_hours' => '24 heures', '7_days' => '7 jours'],
    ], true).';');

    try {
        app()->setLocale('fr');

        $pulseWidget = mountPlacement(PulseCacheWidget::make()->period(PulsePeriod::SixHours));

        [$trigger] = elementsMatching($pulseWidget, '.fi-pulse-widget-period-selector .fi-dropdown-trigger');

        expect(textOf($trigger))->toBe('6 h')
            ->and(elementsMatching($pulseWidget, '.fi-pulse-widget-period-selector button[x-tooltip*="depuis 6 heures"]'))->toHaveCount(1)
            ->and(array_map(textOf(...), elementsMatching($pulseWidget, '.fi-pulse-widget-period-selector [role="group"][aria-label="Période"] [role="menuitemradio"]')))
            ->toBe(['1 heure', '6 heures', '24 heures', '7 jours']);
    } finally {
        removeFromApp($translations);
    }
});

it('publishes the period selector\'s labels for an app to translate', function () {
    $published = lang_path('vendor/filament-pulse-widgets/en/period-selector.php');

    try {
        expect(Artisan::call('vendor:publish', ['--tag' => 'filament-pulse-widgets-translations']))->toBe(0);

        expect(require $published)->toMatchArray([
            'period' => 'Period',
            'long' => ['1_hour' => 'past hour', '6_hours' => 'past 6 hours', '24_hours' => 'past 24 hours', '7_days' => 'past 7 days'],
            'short' => ['1_hour' => '1h', '6_hours' => '6h', '24_hours' => '24h', '7_days' => '7d'],
            'option' => ['1_hour' => '1 hour', '6_hours' => '6 hours', '24_hours' => '24 hours', '7_days' => '7 days'],
        ]);
    } finally {
        removeFromApp(lang_path('vendor/filament-pulse-widgets'));
    }
});

/**
 * The Pulse card headers in a rendered component, as HTML with the whitespace between tags removed.
 *
 * @param  Testable<Component> | DOMDocument  $rendered
 * @return list<string>
 */
function cardHeaders(Testable|DOMDocument $rendered): array
{
    if ($rendered instanceof Testable) {
        $rendered = htmlDocument($rendered);
    }

    $headers = $rendered->getElementsByTagName('header');

    return array_values(array_map(
        fn (DOMElement $header): string => (string) preg_replace('/>\s+</', '><', (string) $rendered->saveHTML($header)),
        iterator_to_array($headers),
    ));
}

/**
 * The shape an element's icon draws, or an icon's own, whatever else its markup says.
 */
function iconOf(DOMElement|string $icon): ?string
{
    if (is_string($icon)) {
        $icon = htmlDocument($icon)->documentElement;
    }

    $path = $icon?->getElementsByTagName('path')->item(0);

    return $path instanceof DOMElement ? $path->getAttribute('d') : null;
}

/**
 * Remove a directory a test published into Testbench's app, and the `vendor`
 * directory it's in when nothing else is left there.
 */
function removeFromApp(string $directory): void
{
    File::deleteDirectory($directory);

    if (File::isEmptyDirectory(dirname($directory))) {
        File::deleteDirectory(dirname($directory));
    }
}
