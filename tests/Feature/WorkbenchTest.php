<?php

use Workbench\App\Models\User;

it('serves the panel dashboard to a signed-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertOk();
});

it('serves the Pulse dashboard to a signed-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/pulse')
        ->assertOk();
});
