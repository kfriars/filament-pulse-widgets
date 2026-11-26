<?php

declare(strict_types=1);

use Filament\Widgets\AccountWidget;
use Kfriars\FilamentPulseWidgets\PulsePeriod;
use Kfriars\FilamentPulseWidgets\Widgets\PulseUsageWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidgetConfiguration;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Workbench\App\Livewire\Pulse\Activity;

beforeEach(fn () => usePanel());

it('nests its Pulse card, already loaded, for the past hour', function () {
    $pulseCard = nestedPulseCard(Livewire::test(PulseUsageWidget::class));

    expect($pulseCard['name'])->toBe('pulse.usage')
        ->and($pulseCard['isLoaded'])->toBeTrue()
        ->and($pulseCard['properties'])->toMatchArray(['period' => '1_hour']);
});

it('starts its Pulse card at the period it is placed with', function () {
    $pulseWidget = mountPlacement(PulseUsageWidget::make()->period(PulsePeriod::SixHours));

    expect(nestedPulseCard($pulseWidget)['properties'])->toMatchArray(['period' => '6_hours']);
});

// Pulse cards read their period only when they mount, so a new period needs a new Pulse card.
it('remounts its Pulse card at the new period when the period changes', function () {
    $pulseWidget = mountPlacement(PulseUsageWidget::make());
    $before = nestedPulseCard($pulseWidget);

    $pulseWidget->set('period', '7_days');

    $after = nestedPulseCard($pulseWidget);

    expect($after['key'])->not->toBe($before['key'])
        ->and($after['properties'])->toMatchArray(['period' => '7_days']);
});

it('nests a custom Pulse card it is given', function () {
    $pulseWidget = mountPlacement(PulseWidget::make()->card(Activity::class));

    expect(nestedPulseCard($pulseWidget)['name'])->toBe('workbench.app.livewire.pulse.activity');
});

it('fails to mount without a Pulse card', function () {
    mountPlacement(PulseWidget::make());
})->throws('Pulse widget [Kfriars\FilamentPulseWidgets\Widgets\PulseWidget] has no Pulse card. Give it one with ->card().');

// Only Pulse cards have their own requests authorized against the Pulse widget's access rules.
it('fails to mount with a Livewire component that is not a Pulse card', function () {
    mountPlacement(PulseWidget::make()->card(AccountWidget::class));
})->throws('Pulse widget [Kfriars\FilamentPulseWidgets\Widgets\PulseWidget] can only contain a Pulse card, and [Filament\Widgets\AccountWidget] does not extend [Laravel\Pulse\Livewire\Card].');

it('passes its card properties straight to its Pulse card', function () {
    $pulseWidget = mountPlacement(PulseWidget::make()->card(Activity::class)->cardProperties(['class' => 'ring-2', 'rows' => 2]));

    expect(nestedPulseCard($pulseWidget)['properties'])->toMatchArray(['class' => 'ring-2', 'rows' => 2]);
});

it('expands its Pulse card instead of scrolling it', function (PulseWidgetConfiguration $placement, bool $expands) {
    expect(nestedPulseCard(mountPlacement($placement))['properties'])->toMatchArray(['expand' => $expands]);
})->with([
    'expanded' => [fn () => PulseUsageWidget::make()->cardProperties(['expand' => false])->expand(), true],
    'scrolling' => [fn () => PulseUsageWidget::make()->cardProperties(['expand' => true])->expand(false), false],
]);

it('keeps the period and loading of its Pulse card to itself', function () {
    $pulseWidget = mountPlacement(PulseUsageWidget::make()->cardProperties(['period' => '7_days', 'lazy' => true]));

    $pulseCard = nestedPulseCard($pulseWidget);

    expect($pulseCard['isLoaded'])->toBeTrue()
        ->and($pulseCard['properties'])->toMatchArray(['period' => '1_hour']);
});

it('spans the columns it is placed with', function () {
    $pulseWidget = mountPlacement(PulseUsageWidget::make()->columnSpan(['md' => 2, 'xl' => 1]));

    expect($pulseWidget->html())
        ->toContain('--col-span-md: span 2 / span 2')
        ->toContain('--col-span-xl: span 1 / span 1');
});

// The period selector itself comes later; the setting is already part of a placement.
it('shows a period selector unless it is placed without one', function (PulseWidgetConfiguration $placement, ?bool $hasPeriodSelector) {
    mountPlacement($placement)->assertSet('hasPeriodSelector', $hasPeriodSelector);
})->with([
    'by default' => [fn () => PulseUsageWidget::make(), null],
    'without one' => [fn () => PulseUsageWidget::make()->withoutPeriodSelector(), false],
    'not without one' => [fn () => PulseUsageWidget::make()->withoutPeriodSelector(false), true],
]);

it('keeps every option but the period out of the browser\'s reach', function (string $option, mixed $value) {
    mountPlacement(PulseUsageWidget::make())->set($option, $value);
})->throws(CannotUpdateLockedPropertyException::class)->with([
    'card' => ['card', 'pulse.servers'],
    'card properties' => ['cardProperties', ['class' => 'hidden']],
    'column span' => ['placementColumnSpan', 'full'],
    'expand' => ['expand', true],
    'period selector' => ['hasPeriodSelector', false],
]);

// Filament lazy-loads widgets; a placeholder the Pulse widget's width keeps the layout from jumping when it loads.
it('holds its place with a placeholder as wide as the placement', function () {
    $placement = PulseUsageWidget::make()->columnSpan('full');

    $placeholder = Livewire::test($placement->widget, [...$placement->widget::getDefaultProperties(), ...$placement->getProperties()]);

    expect($placeholder->html())
        ->toContain('fi-loading-section')
        ->toContain('--col-span-lg: 1 / -1');
});
