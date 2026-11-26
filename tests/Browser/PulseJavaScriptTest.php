<?php

declare(strict_types=1);

use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

// Pulse cards need Pulse's own JavaScript, which sets `window.Chart` and `window.formatDate`.
// It must run once per document, even when the workbench panel's SPA mode swaps pages
// without reloading. (Served from the console, as here, Filament includes it twice.)
const PULSE_JS_FETCHES = 'performance.getEntriesByType("resource").filter((entry) => entry.name.includes("/js/laravel/pulse/pulse.js")).length';

it("loads Pulse's JavaScript exactly once, and keeps it through SPA navigation", function () {
    actingAs(User::factory()->createOne());

    $page = visit('/admin')
        ->assertSee('Slow Queries')
        ->assertScript(PULSE_JS_FETCHES, 1)
        ->assertScript('typeof window.formatDate === "function"');

    $page->script('() => { window.chartBeforeNavigating = window.Chart }');

    // What a `wire:navigate` link does: away to a page without Pulse widgets, then back.
    $page->script('Livewire.navigate("/admin/profile")');
    $page->assertPathIs('/admin/profile')->assertSee('Email address');

    $page->script('Livewire.navigate("/admin")');
    $page->assertPathIs('/admin')
        ->assertSee('Slow Queries')
        ->assertScript(PULSE_JS_FETCHES, 1)
        ->assertScript('window.Chart === window.chartBeforeNavigating')
        ->assertNoJavaScriptErrors();
});

it("loads Pulse's JavaScript once when a page without Pulse widgets navigates to one", function () {
    actingAs(User::factory()->createOne());

    $page = visit('/admin/profile')->assertSee('Email address');

    $page->script('Livewire.navigate("/admin")');
    $page->assertPathIs('/admin')
        ->assertSee('Slow Queries')
        ->assertScript(PULSE_JS_FETCHES, 1)
        ->assertNoJavaScriptErrors();
});
