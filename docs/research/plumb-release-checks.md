# Plumb's release-gated checks

Research for [#32](https://github.com/kfriars/filament-pulse-widgets/issues/32). Read on 2026-11-26 and 2026-11-27 against `master` at `5de19d8`.

## Direct answer

Six of Plumb's 15 checks are release-gated. Each one reads the newest stable release (its dist archive or its `composer.json`) and reports "not applicable" until one exists. The map's four are right, except that "current PHP/Laravel" is two checks, and a sixth, **Current Symfony version supported**, is gated too.

If v1.0.0 were tagged from `master` as it stands, it would score full marks on all six:

| Check | v1.0.0 from `master` |
| --- | --- |
| Open security advisories | **pass** |
| Dist archive is lean | **pass** |
| `composer.lock` not committed by library | **pass** |
| Current PHP version supported | **pass** |
| Current Laravel version supported | **pass** |
| Current Symfony version supported | **not applicable** (no score effect; inferred from comparable Filament plugins) |

None of these six needs a fix before the tag. One more check, **Commit and release recency**, reads the release date, but it passes on commits alone. No Plumb check reads tag format, tag signatures, GitHub Releases, a changelog, a README or a licence file.

## The release-gated checks

Plumb's own evidence confirms the gating. A public package with no stable release reports every one of these six as `not_applicable`, with reasons "Package has no resolved stable release version" and "No release artifact to inspect: no stable release" ([example report](https://plumbphp.dev/api/v1/packages/wizclumsy/age-check)). Its API names the release these checks run against as `reference_version`: "The stable release the dist-archive checks ran against; null when the package has no stable release" ([API reference](https://plumbphp.dev/api/docs)).

In the table, "composite effect" is my own arithmetic from Plumb's documented weights and rules, not a number Plumb publishes. For this package (GitHub-hosted, Laravel, not Symfony), the applicable weights are 37 in Security, 21 in Maintenance and 10 in Ecosystem. The composite is 55% Security + 30% Maintenance + 15% Ecosystem ([scoring](https://plumbphp.dev/scoring)).

| Check | What it looks at | Weight and scoring | `master`'s status | Before the tag | Source |
| --- | --- | --- | --- | --- | --- |
| **Open security advisories** (Security) | Advisories filed against **this package** (not its dependencies) in GitHub Security Advisories and the Packagist advisories database, merged and de-duplicated by CVE. It asks whether any advisory covers the latest released version. | Weight 10, plus a **veto**. Pass when no advisory covers the latest release. Fail when one does, either unfixed or with a fix no newer than the latest release. A fail also caps Security at 30/100, which costs about 38.5 composite points. | **Pass.** The repo has 0 security advisories (`gh api repos/…/security-advisories`) and private vulnerability reporting is on. Nothing has been released, so no advisory can cover a version. | Nothing. Afterwards, ship a fixed tag for any advisory and record the patched version in it. | [advisories-open](https://plumbphp.dev/checks/security/advisories-open) |
| **Dist archive is lean** (Maintenance) | Every file in the **released archive**, matched against four groups: tests (`tests/`, `phpunit.xml`, `phpstan.neon`), CI (`.github/`, `.gitlab-ci.yml`), AI assistant settings (`.claude/`, `CLAUDE.md`, `.cursorrules`) and dev tooling (`.php-cs-fixer.php`, `.editorconfig`, `Makefile`, **`CHANGELOG.md`**). How the files were excluded doesn't matter. | Weight 4. Pass when no group matches. Warn for half credit when exactly one group matches (about −2.9 composite). Fail when two or more match (about −5.7). | **Pass.** `git archive --worktree-attributes HEAD \| tar -t` lists only `composer.json`, `resources/dist/`, `resources/lang/`, `resources/views/` and `src/`, and none of those match a group. `ReleaseArchiveTest` enforces this. | Nothing. Keep `CHANGELOG.md` export-ignored when it's added (it already is), or the tooling group matches and the check warns. | [lean-dist](https://plumbphp.dev/checks/maintenance/lean-dist) |
| **`composer.lock` not committed by library** (Maintenance) | Whether `composer.lock` is in the **released archive**, despite the check's title. "It does not matter how the file was kept out of the archive: `export-ignore` in `.gitattributes`, `archive.exclude` in `composer.json`, or never committing the file all work." It is not applicable to `type: project`. | Weight 2. Pass when the lock file is absent from the archive, fail when present (about −2.9 composite). | **Pass.** `/composer.lock` is gitignored, so it can't be in the archive. `composer.json` has no `type`, so it is treated as a library, the same as `"package_type": "library"` in [an example report](https://plumbphp.dev/api/v1/packages/spatie/laravel-permission). | Nothing. | [composer-lock-policy](https://plumbphp.dev/checks/maintenance/composer-lock-policy) |
| **Current PHP version supported** (Ecosystem) | `require.php` in the **released** `composer.json`, compared with the PHP release calendar. It reads only the constraint, not CI. | Weight 6. Pass when the constraint allows the current stable PHP. Warn for 70% credit when the newest allowed PHP is in active support (about −2.7 composite), or 40% when it gets security fixes only (about −5.4). Fail when it is end of life (about −9). | **Pass.** `"php": "^8.3"` allows PHP 8.6, the current release (8.6.0 on 19 Nov 2026, per [php.net](https://www.php.net/supported-versions.php)). Plumb's own evidence on 2026-11-26 reads "Constraint `^8.3` supports current PHP `8.6`" for another package ([example](https://plumbphp.dev/api/v1/packages/spatie/laravel-permission)). | Nothing required. Optionally add `'8.6'` to the CI matrix. Plumb's guide says "What good looks like" includes testing the current PHP in CI, but it doesn't score that. | [current-php-supported](https://plumbphp.dev/checks/ecosystem/current-php-supported) |
| **Current Laravel version supported** (Ecosystem) | A **real Composer resolution** of the released `require`, at the newest PHP every dependency allows. It respects `replace` and `conflict` rules and checks whether the current Laravel line installs. A package counts as Laravel when `require` names `laravel/framework` or `illuminate/*`. | Weight 4. Pass when the current line resolves. Warn for 70% credit when the newest working line is in active support (about −1.8 composite), or 40% when it gets security fixes only (about −3.6). Fail when it is end of life or nothing resolves (about −6). Not applicable when the package's own dependencies don't resolve at all. | **Pass.** `illuminate/contracts: ^12.0\|^13.0`, and Plumb's current line is `13.0` ([example evidence](https://plumbphp.dev/api/v1/packages/spatie/laravel-permission)). On `master`'s head, CI resolves and passes with Laravel 13 at highest and lowest dependencies, on PHP 8.3 to 8.5 ([run 45939006387](https://github.com/kfriars/filament-pulse-widgets/actions/runs/45939006387)). A Filament plugin that allows v5 (`filament/tables: ^4.0\|^5.0`) resolves with 13.0 in Plumb ([pxlrbt/filament-excel](https://plumbphp.dev/api/v1/packages/pxlrbt/filament-excel)). | Nothing. When Laravel 14 ships, widen `illuminate/contracts` and release once Filament, Livewire and Pulse allow it. | [current-laravel-supported](https://plumbphp.dev/checks/ecosystem/current-laravel-supported) |
| **Current Symfony version supported** (Ecosystem) | The same resolution, for Symfony. A package counts as Symfony when `require` names `symfony/framework-bundle` or a framework-versioned component, or its graph reaches one through Symfony packages. "A package is not treated as Symfony-related just because a framework it builds on uses Symfony components internally." | Weight 4. The thresholds match the Laravel check. Not applicable for packages unrelated to Symfony. | **Not applicable (inferred).** `require` names no Symfony package (`symfony/css-selector` is `require-dev`). Two Filament plugins and a Laravel package all report "No Symfony dependency detected in the package require or its resolved dependency graph" ([filament-excel](https://plumbphp.dev/api/v1/packages/pxlrbt/filament-excel), [filament-shield](https://plumbphp.dev/api/v1/packages/bezhansalleh/filament-shield), [laravel-permission](https://plumbphp.dev/api/v1/packages/spatie/laravel-permission)). Not applicable means no penalty and no credit. | Nothing. Don't add a direct `symfony/*` runtime requirement without checking this again. | [current-symfony-supported](https://plumbphp.dev/checks/ecosystem/current-symfony-supported) |

### What "composer.lock" means here

It means **shipping** one. Committing a lock file is fine as long as it's export-ignored, and gitignoring it, as this package does, is one of the two fixes Plumb recommends ([composer-lock-policy](https://plumbphp.dev/checks/maintenance/composer-lock-policy)). The lock file shows up in one other place. **Dependabot or Renovate configured** counts Composer as an ecosystem "in use" only "when a `composer.lock` is committed" ([dependabot-or-renovate-configured](https://plumbphp.dev/checks/security/dependabot-or-renovate-configured)). Without a lock file, Composer coverage isn't required there, and `.github/dependabot.yml` covers it anyway.

### Before the tag, nothing loses points

A not-applicable check "is left out of the score entirely: no penalty, no credit" ([scoring](https://plumbphp.dev/scoring)). Until v1.0.0, these six checks don't count, and Ecosystem has no applicable checks at all. Plumb doesn't document how the composite treats a category with no applicable checks (see "Couldn't verify").

## Other checks that read tags or releases

| Topic | What Plumb does | Source |
| --- | --- | --- |
| Release cadence | No dedicated check. **Commit and release recency** (Maintenance, weight 5) passes when the latest default-branch commit is ≤ 90 days old **or** the latest stable release on Packagist is ≤ 180 days old. It warns for half credit in between and fails when both are more than a year old. **`master` passes on commits** (its latest commit, `5de19d8`, is from 2026-11-27 UTC), so it isn't release-gated. | [activity-recency](https://plumbphp.dev/checks/maintenance/activity-recency) |
| Pre-release tags | `-alpha`, `-beta` and `-RC` tags are not stable, so the six gated checks stay not applicable. Plumb's guide calls a stable release "what most users can actually adopt". | [activity-recency](https://plumbphp.dev/checks/maintenance/activity-recency), [Composer: stabilities](https://getcomposer.org/doc/articles/versions.md#stabilities) |
| Tag format and the `v` prefix | No check. Composer "automatically removes the 'v' prefix", and a version with no suffix is `stable`. Packagist accepts "'X.Y.Z', or 'vX.Y.Z'". Plumb's `reference_version` shows both forms passing (`v4.1.2` for filament-excel, `4.3.1` for filament-shield, `v5.12.0` for Filament). | [Composer: tags](https://getcomposer.org/doc/articles/versions.md#tags), [Packagist: about](https://packagist.org/about), example reports above |
| Signed tags | No check mentions signatures, GPG or verification. | [checks](https://plumbphp.dev/checks) |
| GitHub Releases | No check. Plumb reads releases from Packagist, which builds versions from git tags ("automatically fetched from tags you create"). | [activity-recency](https://plumbphp.dev/checks/maintenance/activity-recency), [Packagist: about](https://packagist.org/about) |
| Changelog | No check rewards having one. Shipping `CHANGELOG.md` in the dist puts a file in lean dist's dev-tooling group, which is half credit on its own. Plumb calls shipping it deliberately "a fair choice", but it still costs points, so keep it export-ignored. | [lean-dist](https://plumbphp.dev/checks/maintenance/lean-dist) |
| README and LICENSE | No check reads either. Lean dist's "What good looks like" expects the archive to contain "the source code, `composer.json`, the license, and the README", so shipping them is fine. `master` has neither yet. `ReleaseArchiveTest` already lets `README.md` and `LICENSE.md` ship. | [lean-dist](https://plumbphp.dev/checks/maintenance/lean-dist), [checks](https://plumbphp.dev/checks) |

## What the release should look like for full marks

1. **A stable tag on a commit of `master`**, such as `v1.0.0`, with no `-beta` or `-RC` suffix. The `v` is optional. Filament's own tags use it.
2. **The package on Packagist, with its GitHub hook on.** Plumb reads the stable release, the archive and the abandoned flag from Packagist. Its scans are "change-driven: a Packagist release or metadata change re-scores the package within minutes" ([API usage](https://plumbphp.dev/api-usage)). With the hook on, Packagist updates "instantly when you push to GitHub". Without it, Packagist crawls only "once a week" ([Packagist: about](https://packagist.org/about)).
3. **The archive as `ReleaseArchiveTest` allows it:** `composer.json`, `src/`, `resources/{dist,views,lang}/`, plus `README.md` and `LICENSE.md` if present. Packagist and GitHub build the archive with `git archive` from the tagged tree, honouring the committed `.gitattributes` ([lean-dist](https://plumbphp.dev/checks/maintenance/lean-dist)). "Archives for existing tags are already built and will not change", so fixing a bad archive takes a new tag.
4. **The released `composer.json` must allow** the current PHP (8.6 today) and resolve with the current Laravel line (13 today). Both hold on `master`.
5. **No advisory against the release.**
6. **Not needed for points:** a GitHub Release, release notes, a signed tag or a changelog.

The non-gated checks already pass on `master`, and the release doesn't change them:

- Actions pinned: every `uses:` in `.github/workflows/ci.yml` is a 40-character SHA.
- Dependabot covers github-actions, composer and npm.
- Cooldown: 7 days.
- `.github/SECURITY.md` is present.
- Dependabot PRs: none are open.
- The repo is public and not archived.

After the release, keep earning the points:

- Merge Dependabot PRs within 30 days, or 14 days for security fixes ([dependabot-general-responsiveness](https://plumbphp.dev/checks/security/dependabot-general-responsiveness)).
- Commit at least every 90 days, or release at least every 180 days.
- Widen the constraints when Laravel 14 or PHP 9 ships.

## How a scan is requested

- **Automatically.** Plumb tracks "a curated, steadily expanding subset" of Packagist, 380,229 of about 483,608 packages, with full coverage as the goal ([coverage](https://plumbphp.dev/full-packagist-coverage)). A tracked package is re-scored within minutes of a Packagist release, and on a periodic sweep (`scan_cadence_hours`) for time-based signals ([API usage](https://plumbphp.dev/api-usage)).
- **Homepage form.** "Missing a package? Request it now — we'll scan it right away." The form's Livewire state holds a package `name` and an `email` ([plumbphp.dev](https://plumbphp.dev/)).
- **API.** `POST https://plumbphp.dev/api/v1/packages/{vendor}/{name}` returns 202 `queued`, 200 `cached` ("A fresh scan already exists."), 404 "Package not found." or 429. The limit is 3 per 15 minutes and 30 per day, per IP, with no key ([API reference](https://plumbphp.dev/api/docs), [API usage](https://plumbphp.dev/api-usage)).
- **MCP.** The `request_scan` tool at `https://plumbphp.dev/mcp/v1` shares the API's limit. `get_package_score` returns `not_scanned` until a scan exists ([MCP](https://plumbphp.dev/mcp)).
- **Private packages** need a portal account and the Plumb GitHub App ([scoring](https://plumbphp.dev/scoring)). That doesn't apply here: the repo is public.

Request the scan after Packagist shows v1.0.0. Before that, the six gated checks would only report not applicable.

## What depends on a filamentphp.com listing

**No Plumb check does.** None of the 15 checks reads filamentphp.com. They read the repository, Packagist, the advisory feeds and the PHP, Laravel and Symfony calendars ([checks](https://plumbphp.dev/checks)). The listing only affects where the score appears. Filament's plugin pages show a "Package health" section with the three category scores and the 15 checks. Public directory plugins are scanned automatically, and private, paid plugins need their GitHub account linked to Plumb ([Laravel News, 2026-10-05](https://laravel-news.com/securing-filament-plugins-with-plumb)).

One thing I observed, which Plumb doesn't document: `scan_cadence_hours` is 24 for two directory plugins (filament-shield, filament-excel) and 168 for `filament/filament` and `spatie/laravel-permission`. The API calls it "this package's tier" but doesn't say what sets the tier.

## What I read

- **Plumb's docs:**
  - the [homepage](https://plumbphp.dev/), [about](https://plumbphp.dev/about), [scoring](https://plumbphp.dev/scoring), [checks](https://plumbphp.dev/checks) and all 15 check pages under `https://plumbphp.dev/checks/{category}/{id}`;
  - [API usage](https://plumbphp.dev/api-usage), the [API reference](https://plumbphp.dev/api/docs) (its embedded OpenAPI 3.1 document), [MCP](https://plumbphp.dev/mcp) and [coverage](https://plumbphp.dev/full-packagist-coverage);
  - the MCP server's `initialize` instructions and its `plumb://scoring` resource ("Rendered from the scoring code itself"), which confirms the 80% coverage gate and both vetoes.
- **Public Plumb reports for unrelated packages**, used as examples of how checks read: [spatie/laravel-permission](https://plumbphp.dev/api/v1/packages/spatie/laravel-permission) (scanned 2026-11-26), [pxlrbt/filament-excel](https://plumbphp.dev/api/v1/packages/pxlrbt/filament-excel) (2026-11-09), [bezhansalleh/filament-shield](https://plumbphp.dev/api/v1/packages/bezhansalleh/filament-shield) (2026-07-27), [filament/filament](https://plumbphp.dev/api/v1/packages/filament/filament) (2026-11-21) and [wizclumsy/age-check](https://plumbphp.dev/api/v1/packages/wizclumsy/age-check) (2026-11-27, no stable release).
- **Other primary sources:** [php.net supported versions](https://www.php.net/supported-versions.php), [Composer: versions](https://getcomposer.org/doc/articles/versions.md), [Packagist: about](https://packagist.org/about) and [Laravel News](https://laravel-news.com/securing-filament-plugins-with-plumb), which is Filament's announcement, written by its Head of Developer Relations.
- **This repo, read locally:**
  - `composer.json`, `.gitattributes`, `.gitignore`, `.github/` and `tests/Feature/ReleaseArchiveTest.php`;
  - `git archive --worktree-attributes --format=tar origin/master | tar -t`;
  - CI results, repo security advisories and repo flags, through `gh`.

By design, I didn't look this package up on Plumb, Packagist or filamentphp.com, and I didn't request a scan.

## Couldn't verify

- **Plumb's source code.** I found no public repository. GitHub search finds only a third-party CLI, `jeffersongoncalves/plump-cli`. Everything above comes from Plumb's docs, API schema and published evidence.
- **What "stable release" means to Plumb.** Its pages say "stable release" and "resolved stable release version" without defining them. I've assumed Composer's rule: a tag with no suffix is stable. Under that rule, `v1.0.0` is stable.
- **The Symfony check for this package.** "Not applicable" is inferred from comparable packages, not from a scan of this one.
- **Whether Plumb already tracks this package** or will pick it up without a request. I didn't look it up. The scan trigger's 404 "Package not found." is undocumented. It most likely means the name isn't on Packagist.
- **How the composite weights a category with no applicable checks**, which is Ecosystem before the tag. The docs don't say.
- **What sets `scan_cadence_hours`**, and whether a Filament listing changes it.
- **The Packagist advisories feed for this package.** I didn't query it, to keep the package name private. GitHub shows 0 repository advisories.
- **Whether the homepage form requires the email.** I didn't open the form.
