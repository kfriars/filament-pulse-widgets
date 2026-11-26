<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Kfriars\FilamentPulseWidgets\FilamentPulseWidgetsPlugin;
use Kfriars\FilamentPulseWidgets\Widgets\PulseExceptionsWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseServersWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseUsageWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidget;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidgetConfiguration;
use Livewire\Livewire;
use Workbench\App\Livewire\Pulse\Activity;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(fn () => usePanel());

it('shows only to users who can view Pulse, by default', function (string $email, bool $canView) {
    Gate::define('viewPulse', fn (User $user): bool => $user->email === 'ops@example.com');

    actingAs(User::factory()->createOne(['email' => $email]));

    expect(PulseUsageWidget::canView())->toBe($canView);
})->with([
    'can view Pulse' => ['ops@example.com', true],
    'cannot view Pulse' => ['customer@example.com', false],
]);

// Filament's Authenticate middleware doesn't run again on a Pulse card's own requests,
// so the app's default guard may not hold the panel's user there.
it('checks the user signed in to the panel, not the app\'s default guard', function () {
    config()->set('auth.guards.panel', ['driver' => 'session', 'provider' => 'users']);
    usePanel()->authGuard('panel');

    actingAs(User::factory()->createOne(), 'panel');
    auth()->shouldUse('web');

    expect(PulseUsageWidget::canView())->toBeTrue();
});

it('shows to whoever the plugin allows, in place of viewPulse', function (bool|Closure $condition, bool $canView) {
    Gate::define('viewPulse', fn (User $user): bool => ! $canView);

    usePanel(FilamentPulseWidgetsPlugin::make()->authorize($condition));

    actingAs(User::factory()->createOne(['email' => 'support@example.com']));

    expect(PulseUsageWidget::canView())->toBe($canView);
})->with([
    'allowed' => [true, true],
    'refused' => [false, false],
    'allowed by a closure given the user' => [fn () => fn (User $user): bool => $user->email === 'support@example.com', true],
    'refused by a closure given the user' => [fn () => fn (User $user): bool => $user->email === 'ops@example.com', false],
]);

// `PulseWidget::class` covers every custom Pulse card placed through the plain PulseWidget.
it('shows the Pulse widget classes the plugin names by their own rule, matched exactly', function () {
    Gate::define('viewPulse', fn (User $user): bool => false);

    usePanel(FilamentPulseWidgetsPlugin::make()->authorize(true, widgets: [PulseExceptionsWidget::class, PulseWidget::class]));

    actingAs(User::factory()->createOne());

    $exceptionsWidgetSubclass = (new class extends PulseExceptionsWidget {})::class;

    expect(PulseExceptionsWidget::canView())->toBeTrue()
        ->and(PulseWidget::canView())->toBeTrue()
        ->and(PulseServersWidget::canView())->toBeFalse()
        ->and($exceptionsWidgetSubclass::canView())->toBeFalse();
});

it('puts a Pulse widget class\'s own rule before the panel-wide one', function (FilamentPulseWidgetsPlugin $plugin) {
    usePanel($plugin);

    actingAs(User::factory()->createOne());

    expect(PulseExceptionsWidget::canView())->toBeTrue()
        ->and(PulseServersWidget::canView())->toBeFalse();
})->with([
    'set before it' => [fn () => FilamentPulseWidgetsPlugin::make()->authorize(true, widgets: [PulseExceptionsWidget::class])->authorize(false)],
    'set after it' => [fn () => FilamentPulseWidgetsPlugin::make()->authorize(false)->authorize(true, widgets: [PulseExceptionsWidget::class])],
]);

it('takes the last rule the plugin sets for a Pulse widget class', function () {
    usePanel(FilamentPulseWidgetsPlugin::make()
        ->authorize(true, widgets: [PulseExceptionsWidget::class])
        ->authorize(false, widgets: [PulseExceptionsWidget::class, PulseServersWidget::class]));

    actingAs(User::factory()->createOne());

    expect(PulseExceptionsWidget::canView())->toBeFalse()
        ->and(PulseServersWidget::canView())->toBeFalse()
        ->and(PulseUsageWidget::canView())->toBeTrue();
});

it('refuses its Pulse card\'s own requests once the user can no longer see it', function () {
    $canViewPulse = true;
    Gate::define('viewPulse', function (User $user) use (&$canViewPulse): bool {
        return $canViewPulse;
    });

    actingAs(User::factory()->createOne());

    $poll = pollPulseCard(nestedPulseCard(Livewire::test(PulseUsageWidget::class))['snapshot'])->assertOk();

    $canViewPulse = false;

    pollPulseCard($poll)->assertForbidden();
});

it('leaves Pulse cards that are not in a Pulse widget alone', function () {
    Gate::define('viewPulse', fn (User $user): bool => false);

    actingAs(User::factory()->createOne());

    pollPulseCard(renderedComponents(Livewire::test('pulse.usage', ['lazy' => false]))[0]['snapshot'])->assertOk();
});

it('checks a Pulse widget subclass\'s own rule on its Pulse card\'s requests', function () {
    $canViewExceptions = true;
    Gate::define('viewExceptions', function (User $user) use (&$canViewExceptions): bool {
        return $canViewExceptions;
    });

    usePanel(FilamentPulseWidgetsPlugin::make()->authorize(false));

    actingAs(User::factory()->createOne());

    $poll = pollPulseCard(nestedPulseCard(Livewire::test(PulseExceptionsWidgetForSupport::class))['snapshot'])->assertOk();

    $canViewExceptions = false;

    pollPulseCard($poll)->assertForbidden();
});

// Filament lazy-loads widgets, so a hidden placement must skip lazy loading to leave no placeholder behind.
it('leaves out a hidden placement, without loading it or its Pulse card', function (PulseWidgetConfiguration $placement) {
    actingAs(User::factory()->createOne());

    $pulseWidget = mountPlacementLazily($placement);

    expect(renderedComponents($pulseWidget))->toHaveCount(1, 'A hidden placement nests no Pulse card.')
        ->and((string) $pulseWidget->html())->not->toContain('__lazyLoad')->toContain('display: none');
})->with([
    'hidden' => [fn () => PulseUsageWidget::make()->hidden()],
    'hidden by a closure' => [fn () => PulseUsageWidget::make()->hidden(fn (): bool => true)],
    'not visible' => [fn () => PulseUsageWidget::make()->visible(false)],
    'not visible by a closure' => [fn () => PulseUsageWidget::make()->visible(fn (): bool => false)],
    'hidden, with a custom Pulse card' => [fn () => PulseWidget::make()->card(Activity::class)->hidden()],
]);

it('shows a visible placement', function (PulseWidgetConfiguration $placement) {
    actingAs(User::factory()->createOne());

    expect(nestedPulseCard(mountPlacement($placement))['name'])->toBe('pulse.usage');
})->with([
    'by default' => [fn () => PulseUsageWidget::make()],
    'visible' => [fn () => PulseUsageWidget::make()->visible()],
    'visible by a closure' => [fn () => PulseUsageWidget::make()->visible(fn (): bool => true)],
    'not hidden' => [fn () => PulseUsageWidget::make()->hidden(false)],
    'not hidden by a closure' => [fn () => PulseUsageWidget::make()->hidden(fn (): bool => false)],
]);

it('leaves out a placement whose abilities the user lacks', function (PulseWidgetConfiguration $placement) {
    Gate::define('viewUsage', fn (User $user): bool => true);
    Gate::define('viewBilling', fn (User $user): bool => false);

    actingAs(User::factory()->createOne());

    $pulseWidget = mountPlacementLazily($placement);

    expect(renderedComponents($pulseWidget))->toHaveCount(1, 'A placement left out nests no Pulse card.')
        ->and((string) $pulseWidget->html())->not->toContain('__lazyLoad')->toContain('display: none');
})->with([
    'one ability' => [fn () => PulseUsageWidget::make()->authorize('viewBilling')],
    'one of several abilities' => [fn () => PulseUsageWidget::make()->authorize(['viewUsage', 'viewBilling'])],
]);

it('shows a placement whose abilities the user has', function (PulseWidgetConfiguration $placement) {
    Gate::define('viewUsage', fn (User $user): bool => true);
    Gate::define('viewBilling', fn (User $user): bool => true);

    actingAs(User::factory()->createOne());

    expect(nestedPulseCard(mountPlacement($placement))['name'])->toBe('pulse.usage');
})->with([
    'one ability' => [fn () => PulseUsageWidget::make()->authorize('viewUsage')],
    'several abilities' => [fn () => PulseUsageWidget::make()->authorize(['viewUsage', 'viewBilling'])],
]);

it('refuses its Pulse card\'s own requests once the user lacks the placement\'s abilities', function () {
    $canViewUsage = true;
    Gate::define('viewUsage', function (User $user) use (&$canViewUsage): bool {
        return $canViewUsage;
    });

    actingAs(User::factory()->createOne());

    $poll = pollPulseCard(nestedPulseCard(mountPlacement(PulseUsageWidget::make()->authorize('viewUsage')))['snapshot'])->assertOk();

    $canViewUsage = false;

    pollPulseCard($poll)->assertForbidden();
});

// A new period remounts the Pulse card, so the Pulse widget's own requests can show fresh data too.
it('refuses its own requests once the user lacks the placement\'s abilities', function () {
    $canViewUsage = true;
    Gate::define('viewUsage', function (User $user) use (&$canViewUsage): bool {
        return $canViewUsage;
    });

    actingAs(User::factory()->createOne());

    $pulseWidget = mountPlacement(PulseUsageWidget::make()->authorize('viewUsage'));

    $canViewUsage = false;

    $pulseWidget->set('period', '7_days')->assertForbidden();
});

final class PulseExceptionsWidgetForSupport extends PulseExceptionsWidget
{
    public static function canView(): bool
    {
        return Gate::forUser(Filament::auth()->user())->allows('viewExceptions');
    }
}
