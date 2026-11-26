<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Kfriars\FilamentPulseWidgets\Widgets\PulseCacheWidget;
use Livewire\Livewire;
use Workbench\App\Models\User;

use function Orchestra\Testbench\package_path;
use function Pest\Laravel\actingAs;

/*
 * The period selector gets into an unchanged Pulse card by swapping the view
 * behind Pulse's card header, and finds its Pulse card from inside that header.
 * Each test here fails as soon as Pulse or Livewire stops doing what the swap
 * relies on. Then the swap needs updating, not the test.
 */

it('still compiles its card header to a view it looks up through the pulse view namespace', function () {
    expect(Blade::compileString('<x-pulse::card-header name="Cache" />'))
        ->toContain("'view' => 'pulse::components.card-header'");
});

it('still renders the actions slot of its card header inside the header', function () {
    usePulseViewsOnly();

    $header = Blade::render('<x-pulse::card-header name="Cache"><x-slot:actions><span data-canary-action></span></x-slot:actions></x-pulse::card-header>');

    expect($header)->toMatch('/<header\b.*<span data-canary-action><\/span>.*<\/header>/s');
});

it('still renders its card header with the Pulse card it is in as the Livewire component', function () {
    usePanel();
    actingAs(User::factory()->createOne());

    // A card header that only says which Livewire component it sees.
    View::prependNamespace('pulse', package_path('tests/Fixtures/views/livewire-in-card-header'));
    View::getFinder()->flush();

    expect(Livewire::test(PulseCacheWidget::class)->html())->toContain('data-livewire="pulse.cache"');
});
