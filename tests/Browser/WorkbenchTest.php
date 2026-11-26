<?php

declare(strict_types=1);

use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

it('shows a signed-in user the panel dashboard with no JavaScript errors', function () {
    actingAs(User::factory()->createOne(['name' => 'Ada Lovelace']));

    visit('/admin')
        ->assertSee('Ada Lovelace')
        ->assertNoJavaScriptErrors();
})->skip('The Pulse cards on the dashboard need pulse.js, which "Build and ship the Pulse widget stylesheet and Pulse\'s JS" (#19) puts on the page.');

// Pulse cards are lazy, and their loading placeholder has no title, so seeing one
// means Livewire's JavaScript ran and loaded the Pulse card.
it('shows a signed-in user the Pulse dashboard with no JavaScript errors', function () {
    actingAs(User::factory()->createOne());

    visit('/pulse')
        ->assertSee('Slow Queries')
        ->assertNoJavaScriptErrors();
});
