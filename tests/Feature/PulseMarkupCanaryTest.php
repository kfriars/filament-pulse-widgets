<?php

declare(strict_types=1);

use Kfriars\FilamentPulseWidgets\Widgets\PulseCacheWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseExceptionsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseQueuesWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseServersWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowJobsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowOutgoingRequestsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowQueriesWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseSlowRequestsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseUsageWidget;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Livewire;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

/*
 * The Pulse widget stylesheet fixes three things in Pulse's markup for Tailwind 4
 * (resources/css/compat.css and native.css). Each test here selects that markup
 * with the stylesheet's own selector, so it fails as soon as a Pulse release stops
 * rendering what the fix targets. Then the fix needs updating, not the test.
 */

// The workbench lets every signed-in user view Pulse.
beforeEach(function () {
    usePanel();

    actingAs(User::factory()->createOne());
});

it('still renders the Servers card grid that the stylesheet gives its columns', function () {
    Pulse::set('system', 'canary-1', json_encode([
        'name' => 'canary-1',
        'cpu' => 12,
        'memory_used' => 1_024,
        'memory_total' => 4_096,
        'storage' => [['directory' => '/', 'total' => 10_240, 'used' => 2_048]],
    ], flags: JSON_THROW_ON_ERROR));
    Pulse::ingest();

    $selector = ".fi-pulse-widget [class~='grid-cols-[max-content,minmax(max-content,1fr),max-content,minmax(min-content,2fr),max-content,minmax(min-content,2fr),minmax(max-content,1fr)]']";

    expect(elementsMatching(Livewire::test(PulseServersWidget::class), $selector))->toHaveCount(1);
});

it('still renders a select that the stylesheet styles in place of @tailwindcss/forms', function (string $pulseWidgetClass) {
    expect(elementsMatching(Livewire::test($pulseWidgetClass), '.fi-pulse-widget select'))->not->toBeEmpty();
})->with([
    'Usage' => PulseUsageWidget::class,
    'Slow Queries' => PulseSlowQueriesWidget::class,
    'Exceptions' => PulseExceptionsWidget::class,
    'Slow Requests' => PulseSlowRequestsWidget::class,
    'Slow Jobs' => PulseSlowJobsWidget::class,
    'Slow Outgoing Requests' => PulseSlowOutgoingRequestsWidget::class,
]);

it('still renders the Pulse card frame that the stylesheet gives a Filament section ring', function (string $pulseWidgetClass) {
    expect(elementsMatching(Livewire::test($pulseWidgetClass), '.fi-pulse-widget section.ring-1'))->toHaveCount(1);
})->with([
    'Usage' => PulseUsageWidget::class,
    'Queues' => PulseQueuesWidget::class,
    'Cache' => PulseCacheWidget::class,
    'Slow Queries' => PulseSlowQueriesWidget::class,
    'Exceptions' => PulseExceptionsWidget::class,
    'Slow Requests' => PulseSlowRequestsWidget::class,
    'Slow Jobs' => PulseSlowJobsWidget::class,
    'Slow Outgoing Requests' => PulseSlowOutgoingRequestsWidget::class,
]);
