# Package Development

This repository is a Laravel package (`kfriars/filament-pulse-widgets`), not a Laravel application. Where the generic Laravel guidance below assumes an application, this section wins.

- Package code lives in `src/` (namespace `Kfriars\FilamentPulseWidgets`). Tests live in `tests/`. `workbench/` is a development-only Laravel app (Orchestra Testbench) with a Filament v5 panel, Laravel Pulse and seeded Pulse data; it never ships to users.
- `php artisan` is a shim for `vendor/bin/testbench`, so Artisan commands run against the workbench app.
- Do not use `php artisan make:*`. Generators either write into `workbench/` or, for tests, fail. Create files in `src/` and `tests/` by hand, following sibling files.
- There is no frontend build: no Vite, no `npm run build`.
- Run `composer test` (Pest), `composer analyse` (Larastan) and `composer format` (Pint) before finishing a change. Every PHP file declares `strict_types=1`.
- Also run `composer mutate` before finishing any change to `src/`. It is Pest mutation testing over `src/` and must stay at a 100% score. It needs Xdebug or PCOV, and it is a local check only: CI does not run it. A surviving mutation means a missing or weak test, so fix the test. Only a mutation that cannot change behaviour (an equivalent mutant) may be ignored, with a trailing `// @pest-mutate-ignore <Mutator>` comment and the reason beside it.
- Keep the package clean for Plumb (plumbphp.dev), which scores packages for filamentphp.com/plugins:
  - Add every new development-only file or directory to `.gitattributes` with `export-ignore`, so it stays out of the release archive. `tests/Feature/ReleaseArchiveTest.php` fails otherwise.
  - Pin every GitHub Action to a full commit SHA, with the version as a trailing comment: `uses: owner/action@<40-char sha> # v1.2.3`. Dependabot keeps them current.
  - Give every Dependabot update entry a `cooldown` of at least 3 days.
- Use the domain terms in `GLOSSARY.md`: Pulse card, widget, Pulse widget, Pulse page, Pulse dashboard.
