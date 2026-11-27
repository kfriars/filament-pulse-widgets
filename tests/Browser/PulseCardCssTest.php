<?php

declare(strict_types=1);

use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

// The Pulse widget stylesheet has only the classes Pulse's views and the package's
// use. The workbench's custom Activity card brings the rest in its own css(),
// which the Pulse widget delivers when Filament lazy-loads it.
beforeEach(function () {
    actingAs(User::factory()->createOne());
});

it('lays the Activity card out in the two columns its own css() gives it', function (string $url) {
    $page = visit($url)->assertSee('Cache misses');

    $columns = $page->script(<<<'JS'
        () => getComputedStyle(document.querySelector('[wire\\:name="workbench.app.livewire.pulse.activity"] dl'))
            .gridTemplateColumns.split(' ').length
        JS);

    expect($columns)->toBe(2);

    $page->assertNoJavaScriptErrors();
})->with([
    'at 2 columns' => ['/admin'],
    'at 4 columns' => ['/admin?columns=4'],
]);
