<?php

declare(strict_types=1);

namespace Workbench\App\Pulse;

use Carbon\CarbonImmutable;
use Laravel\Pulse\Facades\Pulse;

/**
 * The made-up servers the workbench shows on Pulse's Servers card.
 *
 * Records what Pulse's Servers recorder records on each heartbeat: CPU and
 * memory for the graphs, plus a "system" value for the card's current reading.
 */
class WorkbenchServers
{
    protected const SERVERS = [
        ['name' => 'web-1', 'memory_total' => 8_192, 'storage_total' => 102_400],
        ['name' => 'worker-1', 'memory_total' => 16_384, 'storage_total' => 51_200],
    ];

    /**
     * Record every server's CPU and memory at the given time, for the graphs.
     */
    public function recordLoad(CarbonImmutable $at): void
    {
        foreach (self::SERVERS as $server) {
            $this->recordServerLoad($server, $at);
        }
    }

    /**
     * Report every server as live at the given time.
     *
     * The Servers card fades out any server that hasn't reported in the last
     * 30 seconds, so the workbench reports on every web request.
     */
    public function report(CarbonImmutable $at): void
    {
        foreach (self::SERVERS as $server) {
            ['cpu' => $cpu, 'memory_used' => $memoryUsed] = $this->recordServerLoad($server, $at);

            Pulse::set('system', $server['name'], json_encode([
                'name' => $server['name'],
                'cpu' => $cpu,
                'memory_used' => $memoryUsed,
                'memory_total' => $server['memory_total'],
                'storage' => [
                    ['directory' => '/', 'total' => $server['storage_total'], 'used' => intdiv($server['storage_total'] * 62, 100)],
                ],
            ], flags: JSON_THROW_ON_ERROR), $at);
        }
    }

    /**
     * @param  array{name: string, memory_total: int, storage_total: int}  $server
     * @return array{cpu: int, memory_used: int} CPU as a percentage, memory in MB
     */
    protected function recordServerLoad(array $server, CarbonImmutable $at): array
    {
        $cpu = mt_rand(5, 85);
        $memoryUsed = intdiv($server['memory_total'] * mt_rand(35, 80), 100);

        Pulse::record('cpu', $server['name'], $cpu, $at)->avg()->onlyBuckets();
        Pulse::record('memory', $server['name'], $memoryUsed, $at)->avg()->onlyBuckets();

        return ['cpu' => $cpu, 'memory_used' => $memoryUsed];
    }
}
