<?php

namespace Workbench\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Laravel\Pulse\Facades\Pulse;
use Workbench\App\Models\User;

/**
 * Seeds Pulse with a week of believable monitoring data, so every first-party
 * Pulse card has something to show for every period Pulse offers.
 *
 * Writes the same types and keys as Pulse's own recorders.
 */
class PulseSeeder extends Seeder
{
    protected const SERVERS = [
        'web-1' => ['memory_total' => 8_192, 'storage_total' => 102_400],
        'worker-1' => ['memory_total' => 16_384, 'storage_total' => 51_200],
    ];

    protected const QUEUES = ['redis:default', 'redis:notifications', 'database:reports'];

    protected const CACHE_KEYS = ['users:*', 'settings', 'posts:*:comments', 'feature-flags'];

    protected const SLOW_QUERIES = [
        ['select * from `orders` where `user_id` = ? order by `created_at` desc', 'app/Http/Controllers/OrderController.php:42'],
        ['select count(*) as aggregate from `invoices` where `paid_at` is null', 'app/Filament/Widgets/UnpaidInvoices.php:28'],
        ['update `products` set `stock` = `stock` - ? where `id` = ?', 'app/Actions/ReserveStock.php:19'],
    ];

    protected const EXCEPTIONS = [
        ['App\Exceptions\PaymentDeclinedException', 'app/Services/PaymentGateway.php:88'],
        ['Illuminate\Database\QueryException', 'app/Actions/ReserveStock.php:19'],
        ['ErrorException', 'app/Http/Controllers/ReportController.php:61'],
    ];

    protected const SLOW_REQUESTS = [
        ['GET', '/orders/{order}', 'App\Http\Controllers\OrderController@show'],
        ['POST', '/checkout', 'App\Http\Controllers\CheckoutController'],
        ['GET', '/reports/monthly', 'App\Http\Controllers\ReportController@monthly'],
    ];

    protected const SLOW_JOBS = [
        'App\Jobs\GenerateMonthlyReport',
        'App\Jobs\SendInvoiceReminders',
        'App\Jobs\SyncInventory',
    ];

    protected const SLOW_OUTGOING_REQUESTS = [
        ['POST', 'https://payments.example.com/v1/charges'],
        ['GET', 'https://inventory.example.com/api/stock/*'],
    ];

    public function run(): void
    {
        mt_srand(2026);

        $userIds = User::query()->pluck('id');

        foreach ($this->ticks() as $tick) {
            $this->recordUsage($tick, $userIds);
            $this->recordQueues($tick);
            $this->recordCache($tick);
            $this->recordSlowEvents($tick);
        }

        foreach ($this->serverTicks() as $tick) {
            $this->recordServers($tick);
        }

        $this->reportServers(CarbonImmutable::now());

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
        $now = CarbonImmutable::now()->startOfMinute();

        return [
            ...array_map(fn (int $minutes) => $now->subMinutes($minutes), range(0, 58, 2)),
            ...array_map(fn (int $minutes) => $now->subMinutes($minutes), range(60, 24 * 60 - 1, 30)),
            ...array_map(fn (int $hours) => $now->subHours($hours), range(24, 7 * 24 - 1, 6)),
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
        $now = CarbonImmutable::now()->startOfMinute();

        return [
            ...array_map(fn (int $minutes) => $now->subMinutes($minutes), range(0, 59)),
            ...array_map(fn (int $minutes) => $now->subMinutes($minutes), range(60, 6 * 60 - 1, 6)),
            ...array_map(fn (int $minutes) => $now->subMinutes($minutes), range(6 * 60, 24 * 60 - 1, 24)),
            ...array_map(fn (int $minutes) => $now->subMinutes($minutes), range(24 * 60, 7 * 24 * 60 - 1, 168)),
        ];
    }

    protected function recordServers(CarbonImmutable $tick): void
    {
        foreach (self::SERVERS as $slug => $server) {
            Pulse::record('cpu', $slug, mt_rand(5, 85), $tick)->avg()->onlyBuckets();
            Pulse::record('memory', $slug, (int) ($server['memory_total'] * mt_rand(35, 80) / 100), $tick)->avg()->onlyBuckets();
        }
    }

    /**
     * Report each server the way Pulse's Servers recorder does on a heartbeat.
     *
     * The Servers card fades out any server that hasn't reported in the last
     * 30 seconds, so the workbench also calls this on every web request.
     */
    public function reportServers(CarbonImmutable $at): void
    {
        foreach (self::SERVERS as $slug => $server) {
            $cpu = mt_rand(5, 85);
            $memoryUsed = (int) ($server['memory_total'] * mt_rand(35, 80) / 100);

            Pulse::record('cpu', $slug, $cpu, $at)->avg()->onlyBuckets();
            Pulse::record('memory', $slug, $memoryUsed, $at)->avg()->onlyBuckets();

            Pulse::set('system', $slug, json_encode([
                'name' => $slug,
                'cpu' => $cpu,
                'memory_used' => $memoryUsed,
                'memory_total' => $server['memory_total'],
                'storage' => [
                    ['directory' => '/', 'total' => $server['storage_total'], 'used' => (int) ($server['storage_total'] * 0.62)],
                ],
            ], flags: JSON_THROW_ON_ERROR), $at);
        }
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    protected function recordUsage(CarbonImmutable $tick, Collection $userIds): void
    {
        foreach ($userIds as $index => $userId) {
            // Earlier users are busier, so the Usage card has a clear ranking.
            $requests = max(1, mt_rand(0, 6) - $index);

            for ($i = 0; $i < $requests; $i++) {
                Pulse::record('user_request', (string) $userId, timestamp: $tick)->count();
            }

            if (mt_rand(1, 4) === 1) {
                Pulse::record('slow_user_request', (string) $userId, timestamp: $tick)->count();
            }

            if (mt_rand(1, 3) === 1) {
                Pulse::record('user_job', (string) $userId, timestamp: $tick)->count();
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

    protected function recordSlowEvents(CarbonImmutable $tick): void
    {
        if (mt_rand(1, 3) === 1) {
            Pulse::record('slow_query', $this->pick(self::SLOW_QUERIES), mt_rand(1_000, 4_500), $tick)->max()->count();
        }

        if (mt_rand(1, 4) === 1) {
            Pulse::record('exception', $this->pick(self::EXCEPTIONS), $tick->getTimestamp(), $tick)->max()->count();
        }

        if (mt_rand(1, 3) === 1) {
            Pulse::record('slow_request', $this->pick(self::SLOW_REQUESTS), mt_rand(1_000, 6_000), $tick)->max()->count();
        }

        if (mt_rand(1, 4) === 1) {
            Pulse::record('slow_job', self::SLOW_JOBS[array_rand(self::SLOW_JOBS)], mt_rand(1_000, 30_000), $tick)->max()->count();
        }

        if (mt_rand(1, 4) === 1) {
            Pulse::record('slow_outgoing_request', $this->pick(self::SLOW_OUTGOING_REQUESTS), mt_rand(1_000, 8_000), $tick)->max()->count();
        }
    }

    /**
     * Pick one of the given keys, JSON-encoded the way Pulse's recorders store it.
     *
     * @param  list<list<string>>  $keys
     */
    protected function pick(array $keys): string
    {
        return json_encode($keys[array_rand($keys)], flags: JSON_THROW_ON_ERROR);
    }
}
