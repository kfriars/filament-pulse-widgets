<?php

declare(strict_types=1);

use Laravel\Pulse\Livewire\Cache;
use Laravel\Pulse\Livewire\Exceptions;
use Laravel\Pulse\Livewire\Queues;
use Laravel\Pulse\Livewire\Servers;
use Laravel\Pulse\Livewire\SlowJobs;
use Laravel\Pulse\Livewire\SlowOutgoingRequests;
use Laravel\Pulse\Livewire\SlowQueries;
use Laravel\Pulse\Livewire\SlowRequests;
use Laravel\Pulse\Livewire\Usage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\Database\Seeders\DatabaseSeeder;
use Workbench\Database\Seeders\ScreenshotSeeder;

use function Pest\Laravel\seed;

// Seeding takes a couple of seconds, so one test seeds once and checks every Pulse card.
// Only the default period (the last hour) is checked: Pulse's longer periods all include it.
it('gives every first-party Pulse card something to show', function () {
    seed(DatabaseSeeder::class);

    // Pulse cards are lazy; without this only their loading placeholder renders.
    // Livewire resets the flag on every test(), so it is set for each render.
    $render = fn (string $pulseCard, array $props = []): Testable => Livewire::withoutLazyLoading()->test($pulseCard, $props);

    $showsNoResults = fn (string $pulseCard, array $props = []): bool => str_contains($render($pulseCard, $props)->html(), 'No results');

    $isEmpty = [
        // The Servers card has no "No results" state: it renders no servers instead.
        'Servers' => blank($render(Servers::class)->viewData('servers')),
        'Usage (requests)' => $showsNoResults(Usage::class, ['type' => 'requests']),
        'Usage (slow requests)' => $showsNoResults(Usage::class, ['type' => 'slow_requests']),
        'Usage (jobs)' => $showsNoResults(Usage::class, ['type' => 'jobs']),
        'Queues' => $showsNoResults(Queues::class),
        'Cache' => $showsNoResults(Cache::class),
        'Slow Queries' => $showsNoResults(SlowQueries::class),
        'Exceptions' => $showsNoResults(Exceptions::class),
        'Slow Requests' => $showsNoResults(SlowRequests::class),
        'Slow Jobs' => $showsNoResults(SlowJobs::class),
        'Slow Outgoing Requests' => $showsNoResults(SlowOutgoingRequests::class),
    ];

    $emptyPulseCards = array_keys(array_filter($isEmpty));

    expect($emptyPulseCards)->toBe([]);
});

// The README's screenshots show the Usage card, so `npm run screenshots` seeds users
// whose names a reader takes at face value, instead of Faker's and "Workbench Admin".
it('seeds the README screenshots with plain user names', function () {
    seed(ScreenshotSeeder::class);

    Livewire::withoutLazyLoading()->test(Usage::class, ['type' => 'requests'])
        ->assertSee(['Sofia Martinez', 'James Chen', 'Amara Okafor', 'Liam Walsh', 'Hannah Weber'])
        ->assertDontSee('Workbench Admin');
});
