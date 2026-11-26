<?php

declare(strict_types=1);

use Laravel\Pulse\Facades\Pulse;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// The workbench dashboard places every ready-made Pulse widget and the custom Activity card.
// Filament lazy-loads them, so seeing a Pulse card's data means it loaded in the browser.
// Each string shows on one Pulse card only.
it('loads every Pulse card on the dashboard lazily, then keeps it polling', function () {
    seed(DatabaseSeeder::class);

    // Not a seeded user, whose name Filament's account widget would show as well as the Usage card.
    actingAs(User::factory()->createOne(['name' => 'Ada Lovelace']));

    $page = visit('/admin')
        ->assertSee('worker-1')
        ->assertSee('Workbench Admin')
        ->assertSee('notifications')
        ->assertSee('feature-flags')
        ->assertSee('app/Http/Controllers/OrderController.php:42')
        ->assertSee('PaymentDeclinedException')
        ->assertSee('/reports/monthly')
        ->assertSee('SyncInventory')
        ->assertSee('payments.example.com')
        ->assertSee('Cache misses');

    recordOneOfEverything();

    $page
        ->assertSee('browser-1')
        ->assertSee('Grace Hopper')
        ->assertSee('browser-queue')
        ->assertSee('browser-cache-key')
        ->assertSee('app/Models/BrowserTestQuery.php:12')
        ->assertSee('BrowserTestException')
        ->assertSee('/browser-request')
        ->assertSee('RunBrowserTests')
        ->assertSee('browser-outgoing.example.com');
});

/**
 * Record something new for every first-party Pulse card, the way Pulse's recorders do.
 */
function recordOneOfEverything(): void
{
    Pulse::set('system', 'browser-1', json_encode([
        'name' => 'browser-1',
        'cpu' => 12,
        'memory_used' => 1_024,
        'memory_total' => 4_096,
        'storage' => [['directory' => '/', 'total' => 10_240, 'used' => 2_048]],
    ], flags: JSON_THROW_ON_ERROR));

    Pulse::record('user_request', (string) User::factory()->createOne(['name' => 'Grace Hopper'])->id)->count();
    Pulse::record('queued', 'database:browser-queue')->count()->onlyBuckets();
    Pulse::record('cache_hit', 'browser-cache-key')->count();
    Pulse::record('slow_query', json_encode(['select * from `browser_tests`', 'app/Models/BrowserTestQuery.php:12'], flags: JSON_THROW_ON_ERROR), 1_500)->max()->count();
    Pulse::record('exception', json_encode(['App\Exceptions\BrowserTestException', 'app/Http/Controllers/BrowserTestController.php:34'], flags: JSON_THROW_ON_ERROR), time())->max()->count();
    Pulse::record('slow_request', json_encode(['GET', '/browser-request', 'Closure'], flags: JSON_THROW_ON_ERROR), 1_500)->max()->count();
    Pulse::record('slow_job', 'App\Jobs\RunBrowserTests', 1_500)->max()->count();
    Pulse::record('slow_outgoing_request', json_encode(['GET', 'https://browser-outgoing.example.com'], flags: JSON_THROW_ON_ERROR), 1_500)->max()->count();

    Pulse::ingest();
}
