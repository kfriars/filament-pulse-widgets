# Package Development

This repository is a Laravel package (`kfriars/filament-pulse-widgets`), not a Laravel application. Where the generic Laravel guidance below assumes an application, this section wins.

- Package code lives in `src/` (namespace `Kfriars\FilamentPulseWidgets`). Tests live in `tests/`. `workbench/` is a development-only Laravel app (Orchestra Testbench) with a Filament v5 panel, Laravel Pulse and seeded Pulse data; it never ships to users.
- `php artisan` is a shim for `vendor/bin/testbench`, so Artisan commands run against the workbench app.
- Do not use `php artisan make:*`. Generators either write into `workbench/` or, for tests, fail. Create files in `src/` and `tests/` by hand, following sibling files.
- npm is here for Playwright, which the browser tests drive, and for the Pulse widget stylesheet. There is no Vite.
  - `npm run build` compiles `resources/css/` into `resources/dist/filament-pulse-widgets.css` with the Tailwind CLI. Run it after changing any package view or anything in `resources/css/`, and commit the output with the change. Never edit `resources/dist/` by hand.
  - CI fails when the committed stylesheet is out of date. When a newer Pulse is what changed it, the same PR raises the `laravel/pulse` floor in `composer.json`.
  - After rebuilding, `composer build` republishes the stylesheet for the workbench.
  - `composer serve` and `composer test` never need Node.
- Run `composer test` (Pest), `composer analyse` (Larastan) and `composer format` (Pint) before finishing a change. Every PHP file declares `strict_types=1`.
- Also run `composer test:browser` before finishing any change to views, CSS or JS. It runs the Browser suite in `tests/Browser` (Pest's browser plugin, on Playwright), which `composer test` and `composer mutate` leave out. It needs `npm ci`, `npx playwright install chromium` and `composer build` first: without `composer build`, Filament's assets are missing and the panel throws JavaScript errors. In browser tests, use `assertNoJavaScriptErrors()`, not `assertNoConsoleLogs()`, which misses `console.error` and `console.warn`.
- Also run `composer mutate` before finishing any change to `src/`. It is Pest mutation testing over `src/` and must stay at a 100% score. It needs Xdebug or PCOV, and it is a local check only: CI does not run it. A surviving mutation means a missing or weak test, so fix the test. Only a mutation that cannot change behaviour (an equivalent mutant) may be ignored, with a trailing `// @pest-mutate-ignore <Mutator>` comment and the reason beside it.
- Keep the package clean for Plumb (plumbphp.dev), which scores packages for filamentphp.com/plugins:
  - Add every new development-only file or directory to `.gitattributes` with `export-ignore`, so it stays out of the release archive. `tests/Feature/ReleaseArchiveTest.php` fails otherwise.
  - Pin every GitHub Action to a full commit SHA, with the version as a trailing comment: `uses: owner/action@<40-char sha> # v1.2.3`. Dependabot keeps them current.
  - Give every Dependabot update entry a `cooldown` of at least 3 days.
- Use the domain terms in `GLOSSARY.md`: Pulse card, widget, Pulse widget, Placement, Period, Pulse dashboard.
