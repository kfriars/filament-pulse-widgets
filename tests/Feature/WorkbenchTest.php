<?php

declare(strict_types=1);

use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

it('serves the panel dashboard to a signed-in user', function () {
    actingAs(User::factory()->createOne())
        ->get('/admin')
        ->assertOk();
});

it('serves the Pulse dashboard to a signed-in user', function () {
    actingAs(User::factory()->createOne())
        ->get('/pulse')
        ->assertOk();
});
