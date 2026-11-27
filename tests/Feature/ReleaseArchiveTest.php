<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

use function Orchestra\Testbench\package_path;

// Packagist installs what `git archive` produces, and Plumb (which filamentphp.com/plugins
// uses) marks a package down when tests, CI, AI or tooling files ship in it.
it('ships only the package itself in the release archive', function () {
    $archive = Process::path(package_path())
        ->run('git archive --worktree-attributes --format=tar HEAD | tar -t')
        ->throw()
        ->output();

    $unexpected = collect(explode("\n", trim($archive)))
        ->reject(fn (string $path): bool => str_ends_with($path, '/'))
        ->reject(fn (string $path): bool => str_starts_with($path, 'src/'))
        ->reject(fn (string $path): bool => str_starts_with($path, 'resources/views/'))
        // The period selector's labels, which an app can publish and translate.
        ->reject(fn (string $path): bool => str_starts_with($path, 'resources/lang/'))
        // The compiled Pulse widget stylesheet ships; its source in resources/css/ doesn't.
        ->reject(fn (string $path): bool => str_starts_with($path, 'resources/dist/'))
        ->reject(fn (string $path): bool => in_array($path, ['composer.json', 'LICENSE.md', 'README.md'], true))
        ->values()
        ->all();

    expect($unexpected)->toBe([]);
});

// composer.json declares MIT, and the licence has to travel with every install.
it('ships the licence in the release archive', function () {
    $archive = Process::path(package_path())
        ->run('git archive --worktree-attributes --format=tar HEAD | tar -t')
        ->throw()
        ->output();

    expect(explode("\n", trim($archive)))->toContain('LICENSE.md');
});
