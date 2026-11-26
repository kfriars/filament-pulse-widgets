<?php

use Laravel\Pulse\Livewire\Cache;
use Laravel\Pulse\Livewire\Exceptions;
use Laravel\Pulse\Livewire\Queues;
use Laravel\Pulse\Livewire\Servers;
use Laravel\Pulse\Livewire\SlowJobs;
use Laravel\Pulse\Livewire\SlowOutgoingRequests;
use Laravel\Pulse\Livewire\SlowQueries;
use Laravel\Pulse\Livewire\SlowRequests;
use Laravel\Pulse\Livewire\Usage;
use Livewire\Livewire;
use Workbench\Database\Seeders\DatabaseSeeder;

// Seeding takes a couple of seconds, so one test seeds once and checks every card.
it('gives every first-party Pulse card something to show', function () {
    $this->seed(DatabaseSeeder::class);

    // Pulse cards are lazy; without this only their loading placeholder renders.
    // Livewire resets the flag on every test(), so it is set for each render.
    $render = fn (string $card, array $props = []) => Livewire::withoutLazyLoading()->test($card, $props);

    $showsNoResults = fn (string $card, array $props = []) => str_contains($render($card, $props)->html(), 'No results');

    $hasNothingToShow = [
        // The Servers card has no "No results" state: it renders no servers instead.
        'Servers' => fn () => $render(Servers::class)->viewData('servers')->isEmpty(),
        'Usage (requests)' => fn () => $showsNoResults(Usage::class, ['type' => 'requests']),
        'Usage (slow requests)' => fn () => $showsNoResults(Usage::class, ['type' => 'slow_requests']),
        'Usage (jobs)' => fn () => $showsNoResults(Usage::class, ['type' => 'jobs']),
        'Queues' => fn () => $showsNoResults(Queues::class),
        'Cache' => fn () => $showsNoResults(Cache::class),
        'Slow Queries' => fn () => $showsNoResults(SlowQueries::class),
        'Exceptions' => fn () => $showsNoResults(Exceptions::class),
        'Slow Requests' => fn () => $showsNoResults(SlowRequests::class),
        'Slow Jobs' => fn () => $showsNoResults(SlowJobs::class),
        'Slow Outgoing Requests' => fn () => $showsNoResults(SlowOutgoingRequests::class),
    ];

    $emptyCards = array_keys(array_filter($hasNothingToShow, fn (Closure $check) => $check()));

    expect($emptyCards)->toBe([]);
});
