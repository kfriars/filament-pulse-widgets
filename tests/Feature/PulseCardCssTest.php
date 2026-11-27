<?php

declare(strict_types=1);

use Illuminate\Support\HtmlString;
use Kfriars\FilamentPulseWidgets\PulsePeriod;
use Kfriars\FilamentPulseWidgets\Tests\Fixtures\PulseCards\CustomPulseCard;
use Kfriars\FilamentPulseWidgets\Widgets\PulseUsageWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidgetConfiguration;
use Livewire\Livewire;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

// Pulse collects a Pulse card's css() only on full-page requests, and emits it only
// from its own dashboard's layout, so a Pulse widget delivers it itself.
beforeEach(function () {
    usePanel();

    actingAs(User::factory()->createOne());

    CustomPulseCard::$css = null;
});

it('inlines the file its Pulse card\'s css() names in a style tag', function () {
    CustomPulseCard::$css = __DIR__.'/../Fixtures/css/custom-pulse-card.css';

    $styles = elementsMatching(mountPlacement(PulseWidget::make()->card(CustomPulseCard::class)), '.fi-pulse-widget style');

    expect(array_map(textOf(...), $styles))->toBe(['.custom-pulse-card-from-a-file { display: grid; }']);
});

it('outputs an Htmlable its Pulse card\'s css() returns as it is', function () {
    CustomPulseCard::$css = new HtmlString('<link rel="stylesheet" href="https://cdn.example.com/custom-pulse-card.css">');

    $pulseWidget = mountPlacement(PulseWidget::make()->card(CustomPulseCard::class));

    expect(elementsMatching($pulseWidget, '.fi-pulse-widget link[rel="stylesheet"][href="https://cdn.example.com/custom-pulse-card.css"]'))->toHaveCount(1)
        ->and(elementsMatching($pulseWidget, '.fi-pulse-widget style'))->toBeEmpty();
});

it('outputs every file and Htmlable in a list its Pulse card\'s css() returns, in order', function () {
    CustomPulseCard::$css = [
        new HtmlString('<style>.custom-pulse-card-from-html { color: red; }</style>'),
        __DIR__.'/../Fixtures/css/custom-pulse-card.css',
    ];

    $styles = elementsMatching(mountPlacement(PulseWidget::make()->card(CustomPulseCard::class)), '.fi-pulse-widget style');

    expect(array_map(textOf(...), $styles))->toBe([
        '.custom-pulse-card-from-html { color: red; }',
        '.custom-pulse-card-from-a-file { display: grid; }',
    ]);
});

it('adds nothing to its Pulse widget but its Pulse card\'s css()', function () {
    CustomPulseCard::$css = [
        new HtmlString('<link rel="stylesheet" href="https://cdn.example.com/custom-pulse-card.css">'),
        new HtmlString('<link rel="stylesheet" href="https://cdn.example.com/custom-pulse-card-dark.css">'),
    ];

    $pulseWidget = mountPlacement(PulseWidget::make()->card(CustomPulseCard::class));

    expect(array_map(textOf(...), elementsMatching($pulseWidget, '.fi-pulse-widget')))->toBe(['A custom Pulse card']);
});

it('delivers the css() of a Pulse card it is given by the name the Pulse card is registered under', function () {
    CustomPulseCard::$css = __DIR__.'/../Fixtures/css/custom-pulse-card.css';
    Livewire::component('custom-pulse-card', CustomPulseCard::class);

    $styles = elementsMatching(mountPlacement(PulseWidget::make()->card('custom-pulse-card')), '.fi-pulse-widget style');

    expect(array_map(textOf(...), $styles))->toBe(['.custom-pulse-card-from-a-file { display: grid; }']);
});

// A new period remounts the Pulse card, and its CSS has to survive that.
it('still has its Pulse card\'s css() after the period changes', function () {
    CustomPulseCard::$css = __DIR__.'/../Fixtures/css/custom-pulse-card.css';

    $pulseWidget = mountPlacement(PulseWidget::make()->card(CustomPulseCard::class))
        ->set('period', PulsePeriod::SevenDays->value);

    expect(nestedPulseCard($pulseWidget)['properties'])->toMatchArray(['period' => '7_days'])
        ->and(array_map(textOf(...), elementsMatching($pulseWidget, '.fi-pulse-widget style')))->toBe(['.custom-pulse-card-from-a-file { display: grid; }']);
});

it('adds no CSS for a hidden placement, which has no Pulse card', function () {
    CustomPulseCard::$css = __DIR__.'/../Fixtures/css/custom-pulse-card.css';

    expect(elementsMatching(mountPlacementLazily(PulseWidget::make()->card(CustomPulseCard::class)->hidden()), 'style'))->toBeEmpty();
});

// Pulse's own dist/pulse.css would arrive as a style tag too, and it breaks Filament's styles.
it('adds no CSS for a Pulse card without its own', function (PulseWidgetConfiguration $placement) {
    expect(elementsMatching(mountPlacement($placement), 'style, link'))->toBeEmpty();
})->with([
    'a first-party Pulse card' => [fn () => PulseUsageWidget::make()],
    'a custom Pulse card whose css() returns null' => [fn () => PulseWidget::make()->card(CustomPulseCard::class)],
]);

it('fails clearly when its Pulse card\'s css() names a file that can\'t be read', function () {
    CustomPulseCard::$css = '/nowhere/custom-pulse-card.css';

    mountPlacement(PulseWidget::make()->card(CustomPulseCard::class));
})->throws('Pulse card [Kfriars\FilamentPulseWidgets\Tests\Fixtures\PulseCards\CustomPulseCard] names the CSS file [/nowhere/custom-pulse-card.css] in its css(), and it can\'t be read.');
