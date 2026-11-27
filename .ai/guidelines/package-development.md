# Package Development

This repository is a Laravel package (`kfriars/filament-pulse-widgets`), not a Laravel application. Where the generic Laravel guidance below assumes an application, this section wins.

- Package code lives in `src/` (namespace `Kfriars\FilamentPulseWidgets`). Tests live in `tests/`. `workbench/` is a development-only Laravel app (Orchestra Testbench) with a Filament v5 panel, Laravel Pulse and seeded Pulse data; it never ships to users.
- `php artisan` is a shim for `vendor/bin/testbench`, so Artisan commands run against the workbench app.
- Do not use `php artisan make:*`. Generators either write into `workbench/` or, for tests, fail. Create files in `src/` and `tests/` by hand, following sibling files.
- npm is here for Playwright, which the browser tests and the README's screenshots use, and for the Pulse widget stylesheet. There is no Vite.
  - `npm run build` compiles `resources/css/` into `resources/dist/filament-pulse-widgets.css` with the Tailwind CLI. Run it after changing any package view or anything in `resources/css/`, and commit the output with the change. Never edit `resources/dist/` by hand.
  - `npm run build` also compiles the workbench's custom Activity card stylesheet, which its `css()` returns, from `workbench/resources/css/pulse/` into `workbench/resources/dist/pulse/`. Run it after changing that card's view, and commit that output too.
  - CI fails when the committed stylesheet is out of date. When a newer Pulse is what changed it, the same PR raises the `laravel/pulse` floor in `composer.json`.
  - After rebuilding, `composer build` republishes the stylesheet for the workbench.
  - `composer serve` and `composer test` never need Node.
  - `npm run screenshots` regenerates the README's screenshots in `art/` (`art/screenshots.mjs`). Rerun it after any visible change to a Pulse widget, and commit what it writes. It serves the workbench on a database of its own, seeded by `ScreenshotSeeder`, so it needs `npx playwright install chromium` but not `composer build`, and leaves the workbench's own data alone.
- Run `composer test` (Pest), `composer analyse` (Larastan) and `composer format` (Pint) before finishing a change. Every PHP file declares `strict_types=1`.
- Also run `composer test:browser` before finishing any change to views, CSS or JS. It runs the Browser suite in `tests/Browser` (Pest's browser plugin, on Playwright), which `composer test` and `composer mutate` leave out. It needs `npm ci`, `npx playwright install chromium` and `composer build` first: without `composer build`, Filament's assets are missing and the panel throws JavaScript errors. In browser tests, use `assertNoJavaScriptErrors()`, not `assertNoConsoleLogs()`, which misses `console.error` and `console.warn`.
- Also run `composer mutate` before finishing any change to `src/`. It is Pest mutation testing over `src/` and must stay at a 100% score. It needs Xdebug or PCOV, and it is a local check only: CI does not run it. A surviving mutation means a missing or weak test, so fix the test. Only a mutation that cannot change behaviour (an equivalent mutant) may be ignored, with a trailing `// @pest-mutate-ignore <Mutator>` comment and the reason beside it.
- Keep the package clean for Plumb (plumbphp.dev), which scores packages for filamentphp.com/plugins:
  - Add every new development-only file or directory to `.gitattributes` with `export-ignore`, so it stays out of the release archive. `tests/Feature/ReleaseArchiveTest.php` fails otherwise.
  - Pin every GitHub Action to a full commit SHA, with the version as a trailing comment: `uses: owner/action@<40-char sha> # v1.2.3`. Dependabot keeps them current.
  - Give every Dependabot update entry a `cooldown` of at least 3 days.
- Use the domain terms in `GLOSSARY.md`: Pulse card, widget, Pulse widget, Placement, Period, Pulse dashboard.

## Releasing

- `CHANGELOG.md` follows Keep a Changelog 1.1.0. A PR that changes something a user could notice (anything in the release archive, or the versions `composer.json` allows) adds one line under `## [Unreleased]`, in the matching Keep a Changelog section, saying in `GLOSSARY.md`'s terms what changed for the developer.
  - A Dependabot PR that widens a constraint gets its line in a commit added before merging.
  - Actions, Playwright and dev-only bumps get no line.
  - A Tailwind bump or a Pulse-driven rebuild that changes the stylesheet gets a "Fixed" line.
- A fix, an upstream-compatibility change (a rebuilt stylesheet, a widened constraint) or a security fix is released within 7 days of merging. Features may wait and go out together. An empty `[Unreleased]` means no release.
- The maintainer cuts each release by hand, from `master`:
  1. A release PR renames `[Unreleased]` to `[1.x.y] - YYYY-MM-DD`, opens a fresh empty `## [Unreleased]` above it, and adds its compare link.
  2. Merge it.
  3. `git tag -a v1.x.y -m v1.x.y` on the merge commit, then `git push origin v1.x.y`.
  4. `gh release create v1.x.y --title v1.x.y --notes-file <that entry>`.
- A published tag is permanent, because Packagist and Plumb keep the archive it first built: fix a bad release with a new patch tag, and never move or delete a published tag.
- Semver covers the public surface:
  - `FilamentPulseWidgetsPlugin`'s `make()`, `get()`, `authorize()` and `ID`.
  - The Pulse widgets' `make()` and every fluent option on the configuration it returns, overriding `canView()`, and subclassing `PulseWidget` with `public string $card`.
  - `PulsePeriod` and its cases.
  - The translation keys and the `filament-pulse-widgets-translations` publish tag.
  - The `.fi-pulse-widget` class name, as a styling hook (not the rules under it).
- Anything `@internal`, every view and the stylesheet's contents may change in any release.
- A Laravel or PHP version is dropped no earlier than its security end of life, in a minor, under "Removed".
- A raised floor is versioned like the change it rides with: a patch with a fix, a minor with a feature or a dropped version. A major is only for a break in the public surface.
- Fixes go only into the next release, from `master`. There are no maintenance branches or backports.
