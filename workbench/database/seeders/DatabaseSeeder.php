<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Workbench Admin',
            'email' => 'admin@example.com',
        ]);

        User::factory()->count(4)->create();

        $this->call(PulseSeeder::class);
    }
}
