<?php

declare(strict_types=1);

use Filament\Widgets\WidgetConfiguration;
use Kfriars\FilamentPulseWidgets\Widgets\PulseCacheWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseExceptionsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseQueuesWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseServersWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowJobsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowOutgoingRequestsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowQueriesWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowRequestsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseUsageWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidgetConfiguration;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Workbench\Database\Seeders\DatabaseSeeder;

use function Pest\Laravel\seed;

beforeEach(fn () => usePanel());

// Servers and Slow Queries need the full width of Filament's 2-column dashboard.
it('contains a first-party Pulse card, as wide as that Pulse card needs', function (string $pulseWidgetClass, string $pulseCard, string $columnSpan) {
    $pulseWidget = Livewire::test($pulseWidgetClass);

    expect(nestedPulseCard($pulseWidget)['name'])->toBe($pulseCard)
        ->and($pulseWidget->html())->toContain("--col-span-lg: {$columnSpan}");
})->with([
    'Servers' => [PulseServersWidget::class, 'pulse.servers', '1 / -1'],
    'Usage' => [PulseUsageWidget::class, 'pulse.usage', 'span 1 / span 1'],
    'Queues' => [PulseQueuesWidget::class, 'pulse.queues', 'span 1 / span 1'],
    'Cache' => [PulseCacheWidget::class, 'pulse.cache', 'span 1 / span 1'],
    'Slow Queries' => [PulseSlowQueriesWidget::class, 'pulse.slow-queries', '1 / -1'],
    'Exceptions' => [PulseExceptionsWidget::class, 'pulse.exceptions', 'span 1 / span 1'],
    'Slow Requests' => [PulseSlowRequestsWidget::class, 'pulse.slow-requests', 'span 1 / span 1'],
    'Slow Jobs' => [PulseSlowJobsWidget::class, 'pulse.slow-jobs', 'span 1 / span 1'],
    'Slow Outgoing Requests' => [PulseSlowOutgoingRequestsWidget::class, 'pulse.slow-outgoing-requests', 'span 1 / span 1'],
]);

it('shows one type of usage, or leaves the choice to the Usage card', function (PulseWidgetConfiguration $placement, ?string $type) {
    expect(nestedPulseCard(mountPlacement($placement))['properties'])->toMatchArray(['type' => $type]);
})->with([
    'one type' => [fn () => PulseUsageWidget::make()->cardProperties(['type' => 'requests'])->type('jobs'), 'jobs'],
    'the choice' => [fn () => PulseUsageWidget::make(), null],
]);

it('turns off the highlighting of slow queries', function (PulseWidgetConfiguration $placement, bool $withoutHighlighting) {
    expect(nestedPulseCard(mountPlacement($placement))['properties'])->toMatchArray(['withoutHighlighting' => $withoutHighlighting]);
})->with([
    'off' => [fn () => PulseSlowQueriesWidget::make()->cardProperties(['withoutHighlighting' => false])->withoutHighlighting(), true],
    'on' => [fn () => PulseSlowQueriesWidget::make()->cardProperties(['withoutHighlighting' => true])->withoutHighlighting(false), false],
]);

// The Servers card has no sorting of its own for people to use, so the developer sets it.
it('ignores quiet servers and sorts the rest', function () {
    $placement = PulseServersWidget::make()
        ->cardProperties(['ignoreAfter' => 60, 'sortBy' => 'name', 'sortDirection' => 'asc'])
        ->ignoreAfter('3 hours')
        ->sortBy('cpu_current')
        ->sortDirection('desc');

    expect(nestedPulseCard(mountPlacement($placement))['properties'])->toMatchArray([
        'ignoreAfter' => '3 hours',
        'sortBy' => 'cpu_current',
        'sortDirection' => 'desc',
    ]);
});

it('leaves the Servers card to its own defaults', function () {
    expect(nestedPulseCard(mountPlacement(PulseServersWidget::make()))['properties'])->toMatchArray([
        'ignoreAfter' => null,
        'sortBy' => 'name',
        'sortDirection' => 'asc',
    ]);
});

it('keeps its own options out of the browser\'s reach', function (string $pulseWidgetClass, string $option, mixed $value) {
    Livewire::test($pulseWidgetClass)->set($option, $value);
})->throws(CannotUpdateLockedPropertyException::class)->with([
    'Servers ignore after' => [PulseServersWidget::class, 'ignoreAfter', 1],
    'Servers sort by' => [PulseServersWidget::class, 'sortBy', 'cpu_current'],
    'Servers sort direction' => [PulseServersWidget::class, 'sortDirection', 'desc'],
    'Usage type' => [PulseUsageWidget::class, 'type', 'jobs'],
    'Slow Queries highlighting' => [PulseSlowQueriesWidget::class, 'withoutHighlighting', true],
]);

// Seeding takes a couple of seconds, so one test seeds once and renders every ready-made Pulse widget.
it('renders the seeded data of every ready-made Pulse widget\'s Pulse card', function () {
    seed(DatabaseSeeder::class);

    $seededData = [
        PulseServersWidget::class => 'worker-1',
        PulseUsageWidget::class => 'Workbench Admin',
        PulseQueuesWidget::class => 'notifications',
        PulseCacheWidget::class => 'feature-flags',
        PulseSlowQueriesWidget::class => 'app/Http/Controllers/OrderController.php:42',
        PulseExceptionsWidget::class => 'PaymentDeclinedException',
        PulseSlowRequestsWidget::class => '/reports/monthly',
        PulseSlowJobsWidget::class => 'SyncInventory',
        PulseSlowOutgoingRequestsWidget::class => 'payments.example.com',
    ];

    $missing = array_keys(array_filter(
        $seededData,
        fn (string $data, string $pulseWidgetClass): bool => ! str_contains(Livewire::test($pulseWidgetClass)->html(), $data),
        ARRAY_FILTER_USE_BOTH,
    ));

    expect($missing)->toBe([]);
});

it('expands every ready-made Pulse card that scrolls', function (PulseWidgetConfiguration $placement) {
    expect(nestedPulseCard(mountPlacement($placement))['properties'])->toMatchArray(['expand' => true]);
})->with([
    'Usage' => fn () => PulseUsageWidget::make()->expand(),
    'Queues' => fn () => PulseQueuesWidget::make()->expand(),
    'Cache' => fn () => PulseCacheWidget::make()->expand(),
    'Slow Queries' => fn () => PulseSlowQueriesWidget::make()->expand(),
    'Exceptions' => fn () => PulseExceptionsWidget::make()->expand(),
    'Slow Requests' => fn () => PulseSlowRequestsWidget::make()->expand(),
    'Slow Jobs' => fn () => PulseSlowJobsWidget::make()->expand(),
    'Slow Outgoing Requests' => fn () => PulseSlowOutgoingRequestsWidget::make()->expand(),
]);

// Filament's own way to configure a widget; the fluent methods write the same options.
it('takes its options as an array too', function (WidgetConfiguration $placement, string $option, mixed $value) {
    expect(nestedPulseCard(mountPlacement($placement))['properties'])->toMatchArray([$option => $value]);
})->with([
    'any Pulse widget' => [fn () => PulseWidget::make(['card' => 'pulse.cache', 'expand' => true]), 'expand', true],
    'Servers' => [fn () => PulseServersWidget::make(['sortBy' => 'cpu_current']), 'sortBy', 'cpu_current'],
    'Usage' => [fn () => PulseUsageWidget::make(['type' => 'jobs']), 'type', 'jobs'],
    'Slow Queries' => [fn () => PulseSlowQueriesWidget::make(['withoutHighlighting' => true]), 'withoutHighlighting', true],
]);
