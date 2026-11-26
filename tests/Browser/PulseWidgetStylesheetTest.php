<?php

declare(strict_types=1);

use Laravel\Pulse\Facades\Pulse;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

// The workbench dashboard places every ready-made Pulse widget next to Filament's own
// Account widget, which is a Filament section. Computed styles, not screenshots:
// they say which value is wrong, and don't break when Pulse's data changes.
beforeEach(function () {
    actingAs(User::factory()->createOne());
});

it('makes a Pulse card look like the Filament section beside it, in the panel\'s greys', function (bool $isDark, string $textGrey) {
    $page = visit('/admin');

    if ($isDark) {
        $page = $page->inDarkMode();
    }

    $page = $page->assertSee('Slow Queries')
        ->assertScript('document.documentElement.classList.contains("dark")', $isDark);

    $frame = fn (string $selector): mixed => $page->script(<<<JS
        () => {
            const computed = getComputedStyle(document.querySelector('{$selector}'));

            return {
                background: computed.backgroundColor,
                font: computed.fontFamily,
                ring: computed.boxShadow,
                corners: computed.borderRadius,
            };
        }
        JS);

    // The panel's grey, as the browser computes it from Filament's `--gray-*` variable.
    $panelGrey = $page->script(<<<JS
        () => {
            const probe = document.createElement('div');
            probe.style.color = 'var({$textGrey})';
            document.body.append(probe);
            const grey = getComputedStyle(probe).color;
            probe.remove();

            return grey;
        }
        JS);

    expect($frame('.fi-pulse-widget section.ring-1'))->toBe($frame('.fi-account-widget .fi-section'))
        ->and($page->script("getComputedStyle(document.querySelector('.fi-pulse-widget section.ring-1')).color"))->toBe($panelGrey);
})->with([
    'light mode' => [false, '--gray-900'],
    'dark mode' => [true, '--gray-100'],
]);

it('leaves the Servers card without a frame, as on the Pulse dashboard', function (bool $isDark) {
    Pulse::set('system', 'browser-1', json_encode([
        'name' => 'browser-1',
        'cpu' => 12,
        'memory_used' => 1_024,
        'memory_total' => 4_096,
        'storage' => [['directory' => '/', 'total' => 10_240, 'used' => 2_048]],
    ], flags: JSON_THROW_ON_ERROR));
    Pulse::ingest();

    $page = visit('/admin');

    if ($isDark) {
        $page = $page->inDarkMode();
    }

    $frames = $page->assertSee('browser-1')->script(<<<'JS'
        () => {
            const serversCard = [...document.querySelectorAll('[wire\\:snapshot]')]
                .find((element) => JSON.parse(element.getAttribute('wire:snapshot')).memo.name === 'pulse.servers');

            return [serversCard, serversCard.closest('.fi-pulse-widget')].map((element) => {
                const computed = getComputedStyle(element);

                return { background: computed.backgroundColor, ring: computed.boxShadow, border: computed.borderTopWidth };
            });
        }
        JS);

    expect($frames)->toBe([
        ['background' => 'rgba(0, 0, 0, 0)', 'ring' => 'none', 'border' => '0px'],
        ['background' => 'rgba(0, 0, 0, 0)', 'ring' => 'none', 'border' => '0px'],
    ]);
})->with([
    'light mode' => false,
    'dark mode' => true,
]);

// Turning the stylesheet off must change nothing outside a Pulse widget: Filament's
// buttons, inputs and everything else. A page with Filament's inputs is included,
// because the stylesheet loads on every page of the panel.
it('changes nothing outside Pulse widgets', function (string $path, string $loaded) {
    $differences = visit($path)->assertSee($loaded)->script(<<<'JS'
        () => {
            // Served from the console, as here, Filament includes the stylesheet twice.
            const stylesheets = [...document.querySelectorAll('link[rel="stylesheet"]')]
                .filter((link) => link.href.includes('/css/kfriars/filament-pulse-widgets/filament-pulse-widgets.css'));

            if (stylesheets.length === 0) {
                return ['The Pulse widget stylesheet is not on the page.'];
            }

            const elements = [...document.querySelectorAll('*')].filter((element) => ! element.closest('.fi-pulse-widget'));

            // Unstyled Pulse cards are much taller, which resizes and moves the page around them.
            // These properties compute from that layout, not from any rule in the stylesheet.
            const fromLayout = new Set([
                'width', 'height', 'inline-size', 'block-size',
                'top', 'right', 'bottom', 'left',
                'inset-block-start', 'inset-block-end', 'inset-inline-start', 'inset-inline-end',
                'perspective-origin', 'transform-origin', 'grid-template-rows',
            ]);

            // Pseudo-elements only where they exist. Reading every one is slow enough
            // (over a second) that the browser plugin's round trip takes far longer.
            const exists = (element, pseudo) => pseudo === null || ! ['none', 'normal'].includes(getComputedStyle(element, pseudo).content);

            const targets = elements.flatMap((element) => [null, '::before', '::after']
                .filter((pseudo) => exists(element, pseudo))
                .map((pseudo) => ({ element, pseudo })));

            const computedStyles = () => targets.map(({ element, pseudo }) => {
                const computed = getComputedStyle(element, pseudo);
                const properties = {};

                for (const property of computed) {
                    if (! property.startsWith('--') && ! fromLayout.has(property)) {
                        properties[property] = computed.getPropertyValue(property);
                    }
                }

                return { element, pseudo, properties };
            });

            const withStylesheet = computedStyles();
            stylesheets.forEach((stylesheet) => stylesheet.disabled = true);
            const withoutStylesheet = computedStyles();
            stylesheets.forEach((stylesheet) => stylesheet.disabled = false);

            return withStylesheet.flatMap(({ element, pseudo, properties }, index) => Object.keys(properties)
                .filter((property) => properties[property] !== withoutStylesheet[index].properties[property])
                .map((property) => `${element.tagName.toLowerCase()}${[...element.classList].map((name) => '.' + name).join('')}${pseudo ?? ''} ${property}: ${properties[property]} (without: ${withoutStylesheet[index].properties[property]})`))
                .slice(0, 25);
        }
        JS);

    expect($differences)->toBe([]);
})->with([
    'dashboard' => ['/admin', 'Slow Queries'],
    'profile, with Filament\'s inputs' => ['/admin/profile', 'Email address'],
]);
