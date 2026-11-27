<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\User;

/**
 * The data the README's screenshots show: the same Pulse data as the workbench's,
 * for users with plain names. The first user is the one signed in, and the busiest.
 *
 * `npm run screenshots` seeds it into a database of its own, right before it
 * shoots, so the workbench's own data stays as it is.
 */
class ScreenshotSeeder extends Seeder
{
    protected const USERS = [
        ['name' => 'Sofia Martinez', 'email' => 'sofia@example.com'],
        ['name' => 'James Chen', 'email' => 'james@example.com'],
        ['name' => 'Amara Okafor', 'email' => 'amara@example.com'],
        ['name' => 'Liam Walsh', 'email' => 'liam@example.com'],
        ['name' => 'Hannah Weber', 'email' => 'hannah@example.com'],
    ];

    public function run(): void
    {
        foreach (self::USERS as $user) {
            User::factory()->create($user);
        }

        $this->call(PulseSeeder::class);
    }
}
