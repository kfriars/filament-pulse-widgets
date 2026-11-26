<?php

declare(strict_types=1);

use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

// Livewire names each Pulse card's root element after it. Slow Queries has its
// own control (a sort), and the period selector comes after it.
const SLOW_QUERIES_CARD = '[wire\:name="pulse.slow-queries"]';
const CACHE_CARD = '[wire\:name="pulse.cache"]';

beforeEach(function () {
    actingAs(User::factory()->createOne());
});

it('remounts its Pulse card at the period picked, keeping the Pulse card\'s sort and writing no ?period=', function () {
    $page = visit('/admin')->assertSee('Slow Queries');

    // Pulse keeps the sort in the URL.
    $page->select('#select-slow-queries-order-by', 'count')
        ->assertQueryStringHas('slow-queries', 'count');

    $pulseCardBefore = $page->attribute(SLOW_QUERIES_CARD, 'wire:id');

    $page->click(SLOW_QUERIES_CARD.' .fi-pulse-widget-period-selector .fi-dropdown-trigger button')
        ->click(SLOW_QUERIES_CARD.' .fi-dropdown-panel [role="menuitemradio"]:has-text("7 days")');

    $page->assertSeeIn(SLOW_QUERIES_CARD.' header', 'past 7 days')
        ->assertSeeIn(SLOW_QUERIES_CARD.' .fi-pulse-widget-period-selector .fi-dropdown-trigger button', '7d')
        ->assertSelected('#select-slow-queries-order-by', 'count')
        ->assertQueryStringHas('slow-queries', 'count')
        ->assertQueryStringMissing('period')
        ->assertNoJavaScriptErrors();

    expect($page->attribute(SLOW_QUERIES_CARD, 'wire:id'))->not->toBe($pulseCardBefore)
        ->and($page->script(<<<'JS'
            () => JSON.parse(document.querySelector('[wire\\:name="pulse.slow-queries"]').getAttribute('wire:snapshot')).data.period
            JS))->toBe('7_days');
});

// Pulse cards poll every 5 seconds, and each poll morphs the Pulse card's markup into what the server rendered.
it('keeps its menu open through its Pulse card\'s polls', function () {
    $page = visit('/admin')->assertSee('Cache');

    $page->script(<<<'JS'
        () => {
            window.cachePollsMorphed = 0;

            Livewire.hook('morphed', ({ component }) => {
                if (component.name === 'pulse.cache') {
                    window.cachePollsMorphed++;
                }
            });
        }
        JS);

    $page->click(CACHE_CARD.' .fi-pulse-widget-period-selector .fi-dropdown-trigger button')
        ->assertVisible(CACHE_CARD.' .fi-dropdown-panel')
        ->assertScript('window.cachePollsMorphed > 0')
        ->assertVisible(CACHE_CARD.' .fi-dropdown-panel')
        ->assertNoJavaScriptErrors();
});

// Every Pulse card but Servers has Pulse's card header. At 4 columns most are a quarter of the panel wide.
it('keeps every period selector inside its Pulse card at 4 columns', function () {
    $page = visit('/admin?columns=4')
        ->assertSee('Slow Queries')
        ->assertCount('.fi-pulse-widget-period-selector', 9);

    $outside = $page->script(<<<'JS'
        () => [...document.querySelectorAll('.fi-pulse-widget-period-selector')]
            .filter((selector) => {
                const button = selector.querySelector('.fi-dropdown-trigger button').getBoundingClientRect();
                const pulseCard = selector.closest('section').getBoundingClientRect();

                return button.left < pulseCard.left || button.right > pulseCard.right
                    || button.top < pulseCard.top || button.bottom > pulseCard.bottom;
            })
            .map((selector) => selector.closest('[wire\\:name]').getAttribute('wire:name'))
        JS);

    expect($outside)->toBe([]);

    $page->assertNoJavaScriptErrors();
});
