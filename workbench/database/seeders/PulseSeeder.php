<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Laravel\Pulse\Facades\Pulse;
use Workbench\App\Models\User;
use Workbench\App\Pulse\WorkbenchServers;

/**
 * Seeds Pulse with a week of believable monitoring data, so every first-party
 * Pulse card has something to show for every period Pulse offers.
 *
 * Writes the same types and keys as Pulse's own recorders.
 */
class PulseSeeder extends Seeder
{
    protected const QUEUES = ['redis:default', 'redis:notifications', 'database:reports'];

    protected const CACHE_KEYS = ['users:*', 'settings', 'posts:*:comments', 'feature-flags'];

    protected const EXCEPTIONS = [
        ['App\Exceptions\PaymentDeclinedException', 'app/Services/PaymentGateway.php:88'],
        ['Illuminate\Database\QueryException', 'app/Actions/ReserveStock.php:19'],
        ['ErrorException', 'app/Http/Controllers/ReportController.php:61'],
    ];

    /**
     * The slow events Pulse records with a duration: each happens on one tick
     * in `odds`, picks one of `keys`, and takes between the two `durations` (ms).
     */
    protected const SLOW_EVENTS = [
        'slow_query' => [
            'odds' => 3,
            'durations' => [1_000, 4_500],
            'keys' => [
                ['select * from `orders` where `user_id` = ? order by `created_at` desc', 'app/Http/Controllers/OrderController.php:42'],
                ['select count(*) as aggregate from `invoices` where `paid_at` is null', 'app/Filament/Widgets/UnpaidInvoices.php:28'],
                ['update `products` set `stock` = `stock` - ? where `id` = ?', 'app/Actions/ReserveStock.php:19'],
            ],
        ],
        'slow_request' => [
            'odds' => 3,
            'durations' => [1_000, 6_000],
            'keys' => [
                ['GET', '/orders/{order}', 'App\Http\Controllers\OrderController@show'],
                ['POST', '/checkout', 'App\Http\Controllers\CheckoutController'],
                ['GET', '/reports/monthly', 'App\Http\Controllers\ReportController@monthly'],
            ],
        ],
        'slow_job' => [
            'odds' => 4,
            'durations' => [1_000, 30_000],
            'keys' => [
                'App\Jobs\GenerateMonthlyReport',
                'App\Jobs\SendInvoiceReminders',
                'App\Jobs\SyncInventory',
            ],
        ],
        'slow_outgoing_request' => [
            'odds' => 4,
            'durations' => [1_000, 8_000],
            'keys' => [
                ['POST', 'https://payments.example.com/v1/charges'],
                ['GET', 'https://inventory.example.com/api/stock/*'],
            ],
        ],
    ];

    protected CarbonImmutable $now;

    public function run(WorkbenchServers $servers): void
    {
        mt_srand(2026);

        $this->now = CarbonImmutable::now()->startOfMinute();

        $users = User::query()->get();

        foreach ($this->ticks() as $tick) {
            $this->recordUsage($tick, $users);
            $this->recordQueues($tick);
            $this->recordCache($tick);
            $this->recordExceptions($tick);
            $this->recordSlowEvents($tick);
        }

        foreach ($this->serverTicks() as $tick) {
            $servers->recordLoad($tick);
        }

        $servers->report(CarbonImmutable::now());

        Pulse::ingest();
    }

    /**
     * Every two minutes for the last hour, every half hour for the rest of
     * the day, and every six hours for the rest of the week: enough points
     * for every period's graphs, few enough to seed in a second or two.
     *
     * @return list<CarbonImmutable>
     */
    protected function ticks(): array
    {
        return [
            ...$this->minutesAgo(range(0, 58, 2)),
            ...$this->minutesAgo(range(60, 24 * 60 - 1, 30)),
            ...$this->minutesAgo(range(24 * 60, 7 * 24 * 60 - 1, 6 * 60)),
        ];
    }

    /**
     * One point in every graph bucket of every period. Pulse splits each
     * period into 60 buckets (1, 6, 24 and 168 minutes wide), and the Servers
     * card draws its graphs without bridging empty buckets.
     *
     * @return list<CarbonImmutable>
     */
    protected function serverTicks(): array
    {
        return [
            ...$this->minutesAgo(range(0, 59)),
            ...$this->minutesAgo(range(60, 6 * 60 - 1, 6)),
            ...$this->minutesAgo(range(6 * 60, 24 * 60 - 1, 24)),
            ...$this->minutesAgo(range(24 * 60, 7 * 24 * 60 - 1, 168)),
        ];
    }

    /**
     * @param  list<int>  $minutes
     * @return list<CarbonImmutable>
     */
    protected function minutesAgo(array $minutes): array
    {
        return array_map(fn (int $minute): CarbonImmutable => $this->now->subMinutes($minute), $minutes);
    }

    /**
     * @param  Collection<int, User>  $users
     */
    protected function recordUsage(CarbonImmutable $tick, Collection $users): void
    {
        foreach ($users->values() as $rank => $user) {
            $userKey = (string) $user->id;

            // Earlier users are busier, so the Usage card has a clear ranking.
            $requests = max(1, mt_rand(0, 6) - $rank);

            for ($i = 0; $i < $requests; $i++) {
                Pulse::record('user_request', $userKey, timestamp: $tick)->count();
            }

            if (mt_rand(1, 4) === 1) {
                Pulse::record('slow_user_request', $userKey, timestamp: $tick)->count();
            }

            if (mt_rand(1, 3) === 1) {
                Pulse::record('user_job', $userKey, timestamp: $tick)->count();
            }
        }
    }

    protected function recordQueues(CarbonImmutable $tick): void
    {
        foreach (self::QUEUES as $queue) {
            $processed = mt_rand(1, 4);

            $counts = [
                'queued' => $processed + mt_rand(0, 1),
                'processing' => $processed,
                'processed' => $processed,
                'released' => mt_rand(0, 10) === 0 ? 1 : 0,
                'failed' => mt_rand(0, 15) === 0 ? 1 : 0,
            ];

            foreach ($counts as $type => $count) {
                for ($i = 0; $i < $count; $i++) {
                    Pulse::record($type, $queue, timestamp: $tick)->count()->onlyBuckets();
                }
            }
        }
    }

    protected function recordCache(CarbonImmutable $tick): void
    {
        foreach (self::CACHE_KEYS as $key) {
            for ($i = mt_rand(1, 3); $i > 0; $i--) {
                Pulse::record(mt_rand(1, 5) === 1 ? 'cache_miss' : 'cache_hit', $key, timestamp: $tick)->count();
            }
        }
    }

    protected function recordExceptions(CarbonImmutable $tick): void
    {
        if (mt_rand(1, 4) === 1) {
            // Pulse records when an exception happened as its value, not a duration.
            Pulse::record('exception', $this->randomKey(self::EXCEPTIONS), $tick->getTimestamp(), $tick)->max()->count();
        }
    }

    protected function recordSlowEvents(CarbonImmutable $tick): void
    {
        foreach (self::SLOW_EVENTS as $type => $event) {
            if (mt_rand(1, $event['odds']) !== 1) {
                continue;
            }

            [$fastest, $slowest] = $event['durations'];

            Pulse::record($type, $this->randomKey($event['keys']), mt_rand($fastest, $slowest), $tick)->max()->count();
        }
    }

    /**
     * Pick one of the keys at random. A key with several parts is JSON-encoded,
     * the way Pulse's recorders store it.
     *
     * @param  non-empty-list<string|list<string>>  $keys
     */
    protected function randomKey(array $keys): string
    {
        $key = $keys[array_rand($keys)];

        return is_array($key) ? json_encode($key, flags: JSON_THROW_ON_ERROR) : $key;
    }
}
