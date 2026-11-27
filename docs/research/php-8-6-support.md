# Can PHP 8.6 join CI yet?

Research for [#31](https://github.com/kfriars/filament-pulse-widgets/issues/31), checked 2026-11-26. The criterion comes from [#11](https://github.com/kfriars/filament-pulse-widgets/issues/11): PHP 8.6 joins the matrix as a required job once Laravel 13 and Pest support it.

## Answer

**Yes, on Laravel 13. No, on Laravel 12.**

PHP 8.6.0 went GA on 2026-11-19, and the setup-php version CI already pins (2.40.0) installs 8.6.0 on `ubuntu-latest`. Both halves of the criterion hold for Laravel 13:

- Laravel 13 runs PHP 8.6 as a required CI job since [laravel/framework#61771](https://github.com/laravel/framework/pull/61771), first tagged in v13.44.0 (2026-11-24).
- Pest 5 has tested PHP 8.6 in CI since [v5.3.4](https://github.com/pestphp/pest/releases/tag/v5.3.4) (2026-10-27).

With PHP 8.6 and Laravel 13, `composer update` resolves the main packages to the same versions CI installs on 8.5 today. Filament v5 passes its full suite on PHP 8.6 with Laravel 13 and Livewire 4.

Laravel 12 can't take PHP 8.6, for two reasons:

- Laravel 12 doesn't support PHP 8.6. Its docs list 8.2–8.5, its CI stops at 8.5, and it has had security fixes only since 2026-08-13.
- This package's dev dependencies don't resolve on PHP 8.6 with Laravel 12:
  - Pest 5 needs PHPUnit 13.4 and `symfony/process` 8. Testbench 10 rules out PHPUnit 13.2+, and Laravel 12 rules out `symfony/process` 8.
  - Pest 4 needs a paratest release that allows both PHP 8.6 and PHPUnit 12. None exists, because paratest's PHP 8.6 releases (7.25.0 and later) need PHPUnit 13.3.4 or later.

**What to change:** add `'8.6'` to the highest-dependency `tests` matrix and exclude it from Laravel 12, so PHP 8.6 on Laravel 13 is one new required job. The lowest-dependency jobs, Checks and Browser stay as they are.

```yaml
php: ['8.3', '8.4', '8.5', '8.6']
laravel: ['12', '13']
dependencies: [highest]
exclude:
  - php: '8.6'
    laravel: '12'
```

**Not verified:** this package's own test suite on PHP 8.6. There was no PHP 8.6 on this machine (see [What I couldn't verify](#what-i-couldnt-verify)), so the first CI run on the PR that adds the job is the real test.

**Gaps, none of them blockers:**

- Pest's Laravel, browser and mutate plugins have open, green PRs that add PHP 8.6 to their CI, but none is merged.
- Livewire, Pulse, Larastan and Boost don't test PHP 8.6 yet.
- Laravel's docs still list 8.3–8.5 for Laravel 13.

## Dependencies at a glance

The versions are what CI's highest-dependency job on PHP 8.6 and Laravel 13 resolves to (see [Resolution check](#resolution-check)).

| Dependency | Version | PHP 8.6 status | Source |
| --- | --- | --- | --- |
| PHP | 8.6.0 | GA on 2026-11-19 | [php.net release page](https://www.php.net/releases/8.6/en.php) |
| `shivammathur/setup-php` | 2.40.0 (pinned `eb7c497`) | Installs 8.6.0 on Ubuntu. The commit marking 8.6 stable is on `main` but unreleased | [Pest's 8.6 job log](https://github.com/pestphp/pest/actions/runs/45845068362), [`2001cd3`](https://github.com/shivammathur/setup-php/commit/2001cd3148dcbaf467b7fa06cd7155ebb6d4cacf) |
| `laravel/framework` 13 | 13.44.1 | Supported: a required Linux job since #61771, first tagged in v13.44.0 | [#61771](https://github.com/laravel/framework/pull/61771), [tests.yml @ `b9bb152`](https://github.com/laravel/framework/blob/b9bb152c76827df0bf2f32285b6e49fa8520886c/.github/workflows/tests.yml) |
| `laravel/framework` 12 | none | Not supported: docs say 8.2–8.5, CI stops at 8.5 | [12.x tests.yml @ `a6887ee`](https://github.com/laravel/framework/blob/a6887ee7b1ceafdadea1d8cfaa7c7c7422671f13/.github/workflows/tests.yml), [support policy](https://laravel.com/docs/13.x/releases#support-policy) |
| `pestphp/pest` 5 | 5.3.7 | Supported: CI on 8.4–8.6 since v5.3.4 | [#1989](https://github.com/pestphp/pest/pull/1989), [v5.3.4](https://github.com/pestphp/pest/releases/tag/v5.3.4) |
| `pestphp/pest` 4 (the Laravel 12 jobs) | none | Can't install on 8.6 (paratest, see below) | [Packagist metadata](https://repo.packagist.org/p2/pestphp/pest.json) |
| `pestphp/pest-plugin-laravel` 5 | 5.0.1 | `^8.4`, uncapped. CI stops at 8.5. 8.6 PR open and green | [#82](https://github.com/pestphp/pest-plugin-laravel/pull/82) |
| `pestphp/pest-plugin-browser` 5 | 5.2.0 | `^8.4`, uncapped. CI stops at 8.5. 8.6 PR open and green | [#330](https://github.com/pestphp/pest-plugin-browser/pull/330) |
| `pestphp/pest-plugin-mutate` 5 | 5.0.2 | `^8.4`, uncapped. CI stops at 8.5. 8.6 PR open and green | [#54](https://github.com/pestphp/pest-plugin-mutate/pull/54) |
| `phpunit/phpunit` 13 | 13.4.7 | `>=8.4.1`. The 13.4 branch's CI includes 8.6 | [ci.yaml @ `d22fe3b`](https://github.com/sebastianbergmann/phpunit/blob/d22fe3b3008c603781a813be1931aea61bfefd47/.github/workflows/ci.yaml) |
| `brianium/paratest` | 7.29.0 | 7.25.0+ allow `~8.6.0` but need PHPUnit 13.3.4+. The PHPUnit 12 line caps at `~8.5.0` | [Packagist metadata](https://repo.packagist.org/p2/brianium/paratest.json) |
| `orchestra/testbench` 11 / `testbench-core` 11 | 11.3.0 / 11.6.1 | Supported: testbench-core runs a required 8.6 job since #428 | [#428](https://github.com/orchestral/testbench-core/pull/428) |
| `orchestra/testbench` 10 (Laravel 12) | none | No 8.6 job. Conflicts with `phpunit/phpunit >=13.2.0` | [10.x composer.json @ `35d42a7`](https://github.com/orchestral/testbench-core/blob/35d42a795ef02da1fbc52a9c5e4e7eb6e901e0e4/composer.json) |
| `larastan/larastan` 3 | 3.15.0 | `^8.2`, uncapped. CI on 8.2–8.5, no 8.6 issue or PR | [tests.yml @ `3b2e33e`](https://github.com/larastan/larastan/blob/3b2e33e8f43bf29c2d2c2214e9494c07d3483a47/.github/workflows/tests.yml) |
| `phpstan/phpstan` 2 | 2.3.7 | Supported: "Full support for PHP 8.6" since 2.3.6 | [2.3.6 release](https://github.com/phpstan/phpstan/releases/tag/2.3.6) |
| `laravel/pint` | 1.32.3 | Supported: CI on 8.3–8.6 since `aab177d`, so in 1.32.2+ | [`aab177d`](https://github.com/laravel/pint/commit/aab177d2333356888b17e85b4fe85933c076d0d9) |
| `filament/filament` 5 | 5.12.0 | Supported: CI on 8.6 with Laravel 13, passing. 5.12.0 has the fixes for 8.6's stateless closure cache | [tests.yml @ `9708193`](https://github.com/filamentphp/filament/blob/9708193bc69c796d39ba7fe8f91a1663762ca591/.github/workflows/tests.yml), [v5.12.0](https://github.com/filamentphp/filament/releases/tag/v5.12.0) |
| `livewire/livewire` 4 | 4.4.10 | `^8.1`, uncapped. CI on 8.1–8.5. Filament's 8.6 job runs it | [test-4.x.yml @ `0b8f03c`](https://github.com/livewire/livewire/blob/0b8f03c9b7f51c7267613eb0be6bad245c27ce2c/.github/workflows/test-4.x.yml) |
| `laravel/pulse` | 1.11.0 | `^8.2`, uncapped. CI on 8.2–8.5, no 8.6 issue or PR | [tests.yml @ `33cc6fc`](https://github.com/laravel/pulse/blob/33cc6fc68cd3052aa06227e88e8d933dd3d70a51/.github/workflows/tests.yml) |
| `spatie/laravel-package-tools` | 1.93.3 | Supported: CI on 8.2–8.6 since #190 | [`132077d`](https://github.com/spatie/laravel-package-tools/commit/132077d5f74d3a6b57ed5d2a47f6f3e71be419f4) |
| `laravel/boost` | 2.10.4 | `^8.2`, uncapped. CI on 8.2–8.5 | [tests.yml @ `c1c27ed`](https://github.com/laravel/boost/blob/c1c27ed55bf32e0c82f71663516f89e23eefde3e/.github/workflows/tests.yml) |
| Xdebug (local `composer mutate`) | none | Only 3.6.0alpha1 supports 8.6 | [compat table](https://xdebug.org/docs/compat), [updates](https://xdebug.org/updates) |
| PCOV (local) | none | 8.6 build fix merged 2026-11-24 but unreleased. 1.0.12 needs patching to build on 8.6 | [krakjoe/pcov#134](https://github.com/krakjoe/pcov/pull/134), [PECL](https://pecl.php.net/package/pcov) |

## 1. Is PHP 8.6 out, and can setup-php install it?

**PHP 8.6.0 is GA, released 2026-11-19.** The last pre-release was 8.6.0RC7 on 2026-11-12, and the php.net front page lists 8.6.0 as the current stable release, next to 8.5.13, 8.4.28 and 8.3.37 ([php.net](https://www.php.net/), [8.6 release page](https://www.php.net/releases/8.6/en.php)).

**setup-php installs 8.6.0 on Ubuntu, even at the pinned 2.40.0.**

- CI pins `shivammathur/setup-php@eb7c497e…` (2.40.0, released 2026-10-08). That is also where the rolling `v2` tag points today, and 2.40.0 is the latest release.
- 2.40.0 still treats 8.6 as a nightly version: `nightly_versions="8.[6-9]"` in `src/scripts/unix.sh` and `"nightly": "8.6"` in `src/configs/php-versions.json` (read from the 2.40.0 source tarball). The commit that marks 8.6 stable, [`2001cd3`](https://github.com/shivammathur/setup-php/commit/2001cd3148dcbaf467b7fa06cd7155ebb6d4cacf) on 2026-11-19, is on `main` and not released yet.
- That doesn't matter on GitHub-hosted Ubuntu. There, setup-php installs PHP from the cached builds in `shivammathur/php-ubuntu`, and that repo switched 8.6 to its stable build on 2026-11-19 ([`8369c13`](https://github.com/shivammathur/php-ubuntu/commit/8369c135cf7be6740c474d31c78204d94b6bff74), "Route PHP 8.6 to the stable Ubuntu cache").
- Evidence from real runs:
  - Pest's CI, pinned to the same `eb7c497` SHA, logged `Installed PHP 8.6.0` on `ubuntu-latest` on 2026-11-26 ([run 45845068362](https://github.com/pestphp/pest/actions/runs/45845068362)). So did Filament's 8.6 job ([run 45854850054](https://github.com/filamentphp/filament/actions/runs/45854850054)).
  - PHPStan's [phpstan-src#7114](https://github.com/phpstan/phpstan-src/pull/7114) puts it in so many words: "setup-php's Linux 8.6 build is 8.6.0". Only Windows still gets a nightly under 2.40.0.
- Dependabot will raise the pin when setup-php's next release ships. Nothing waits on it, since CI is Ubuntu-only.

## 2. Do Laravel 13 and Laravel 12 support it?

**Laravel 13: yes.**

- `composer.json` on 13.x requires `php: ^8.3`, uncapped.
- [#61771](https://github.com/laravel/framework/pull/61771), "[13.x] Supports for PHP 8.6", was merged on 2026-11-20. It swaps out the deprecated `SORT_LOCALE_STRING` and `strcoll()`, fixes a `trim()` issue in validation, and adds this to the CI matrix ([tests.yml @ `b9bb152`](https://github.com/laravel/framework/blob/b9bb152c76827df0bf2f32285b6e49fa8520886c/.github/workflows/tests.yml)):

  ```yaml
  include:
    - php: 8.6
      phpunit: '13.3'
      stability: prefer-stable
  ```

  On Linux the job is required. It sets no `experimental` flag, so `continue-on-error` is false and `--ignore-platform-reqs` isn't passed. On Windows the 8.6 job is marked `experimental: true`.
- v13.44.0, tagged on 2026-11-24, is the first release that contains it: the commit is in `v13.44.0` and not in `v13.43.0` (checked with GitHub's compare API). v13.44.0 has no GitHub release page, v13.44.1's release notes are empty, and the 13.x `CHANGELOG.md` stops at v13.43.0. So the PR and the tags are the only record of it.
- Laravel's docs haven't caught up. The support table in [laravel/docs `13.x/releases.md`](https://github.com/laravel/docs/blob/13.x/releases.md) still lists 13 as "8.3 - 8.5", and there is no docs PR about 8.6.

**Laravel 12: no.**

- `composer.json` on 12.x requires `php: ^8.2`, uncapped, so Composer doesn't block it.
- But its CI runs PHP 8.2–8.4 plus 8.5 and no 8.6 ([tests.yml @ `a6887ee`](https://github.com/laravel/framework/blob/a6887ee7b1ceafdadea1d8cfaa7c7c7422671f13/.github/workflows/tests.yml)). 8.5 came in with "[12.x] PHP 8.5 Compatibility" (#57835, 2025-11-21), and no later change to the workflow adds 8.6.
- No 12.x PR mentions PHP 8.6. The 8.6 fixes in #61771 went to 13.x only.
- The [support policy](https://laravel.com/docs/13.x/releases#support-policy) lists Laravel 12 as PHP 8.2–8.5, with bug fixes until 2026-08-13 and security fixes until 2027-02-24. So 12.x is past bug fixes, and 8.6 support isn't likely to come.

## 3. Do Pest 5, its plugins and PHPUnit support it?

**Pest 5: yes.**

- Pest requires `php: ^8.4`, uncapped. Its 5.x CI runs PHP 8.4, 8.5 and 8.6 on Ubuntu and macOS ([tests.yml on 5.x](https://github.com/pestphp/pest/blob/8d004b80d7511b6f30f170d53f20e515c18ec2e8/.github/workflows/tests.yml)). 8.6 was added in [#1989](https://github.com/pestphp/pest/pull/1989), merged on 2026-10-22.
- [v5.3.4](https://github.com/pestphp/pest/releases/tag/v5.3.4) ships #1989 and [#1988](https://github.com/pestphp/pest/pull/1988), which fixes the reflection deprecation behind most of the 99 deprecations #1989 first found. The release notes don't say "supports PHP 8.6" in words. The CI change is the declaration.
- The 8.6 jobs pass on 5.x HEAD as of 2026-11-26.

**Pest 4: can't be installed on PHP 8.6.** Pest 4.7 requires `phpunit/phpunit ^12.5` and `brianium/paratest ^7.20`. Every paratest release that allows PHP 8.6 needs PHPUnit 13.3.4 or later: 7.25.0 and later for PHPUnit 13, and 7.8.6 for PHPUnit 11. The PHPUnit 12 releases (7.14.0–7.20.0) cap PHP at `~8.5.0` ([Packagist metadata](https://repo.packagist.org/p2/brianium/paratest.json)). Filament runs Pest 4 on 8.6 only by passing `--ignore-platform-req=php+`.

**Plugins: their constraints allow 8.6, but none tests it yet.**

- [`pest-plugin-laravel`](https://github.com/pestphp/pest-plugin-laravel/blob/d1564646e3198f1b607e64a36501d6edff7d104e/.github/workflows/tests.yml), [`pest-plugin-browser`](https://github.com/pestphp/pest-plugin-browser/blob/7f85b3719f6c8afd71f6622ab49777a04004e4af/.github/workflows/tests.yml) and [`pest-plugin-mutate`](https://github.com/pestphp/pest-plugin-mutate/blob/d0c87110416b699413cc073b8dcc92e440d81931/.github/workflows/tests.yml) all require `php: ^8.4`, uncapped, and their CI stops at 8.5.
- Each got a "Test on PHP 8.6" PR on 2026-11-21. All are open, and all are green on 8.6:
  - [pest-plugin-laravel#82](https://github.com/pestphp/pest-plugin-laravel/pull/82) only changes CI, plus the Laravel and Carbon versions its lowest-dependency 8.6 jobs use.
  - [pest-plugin-browser#330](https://github.com/pestphp/pest-plugin-browser/pull/330) also aliases anonymous Livewire components passed to the plugin's `Livewire::test()`, since their class names contain a NUL byte. This package's browser tests don't use `Livewire::test()` with anonymous components.
  - [pest-plugin-mutate#54](https://github.com/pestphp/pest-plugin-mutate/pull/54) also makes the function-call-unwrap mutators handle php-parser's newer argument types, which covers PHP 8.6's partial-function-application placeholders. `src/` doesn't use that syntax, and mutation testing is local only anyway.

**PHPUnit: yes.** CI resolves PHPUnit 13.4.7, which requires `php: >=8.4.1`, uncapped. The 13.4 branch's CI includes 8.6 ([ci.yaml @ `d22fe3b`](https://github.com/sebastianbergmann/phpunit/blob/d22fe3b3008c603781a813be1931aea61bfefd47/.github/workflows/ci.yaml)), and so does 12.5's. [phpunit.de](https://phpunit.de/supported-versions.html) lists PHPUnit 13 as ">= PHP 8.4" without naming 8.6.

## 4. Does anything else in the tree hold it back?

Nothing holds it back on Laravel 13. Laravel 12 is held back by Testbench 10, below.

- **Orchestra Testbench**
  - `testbench-core` 11.x runs a required PHP 8.6 job with PHPUnit 13.3 and highest dependencies. It was added as experimental in [#420](https://github.com/orchestral/testbench-core/pull/420) (2026-09-22) and made required in [#428](https://github.com/orchestral/testbench-core/pull/428) (2026-11-20), and it passes as of 2026-11-26.
  - The `orchestra/testbench` 11.x wrapper's own CI stops at 8.5.
  - `testbench-core` 10.x, the Laravel 12 line, has no 8.6 job, and its `composer.json` conflicts with `phpunit/phpunit >=13.2.0`. Together with Laravel 12's `symfony/process ^7.2`, that keeps Pest 5 off Laravel 12 entirely.
- **Larastan and PHPStan**
  - Larastan 3.x requires `php: ^8.2` and `phpstan/phpstan ^2.3.5`. Its CI runs 8.2–8.5, and there is no 8.6 issue or PR.
  - PHPStan [2.3.6](https://github.com/phpstan/phpstan/releases/tag/2.3.6) (2026-11-15) says "Full support for PHP 8.6, including Partial Function Application". CI resolves 2.3.7.
  - The Checks job stays on 8.5 anyway.
- **Pint**
  - Pint requires `php: ^8.3.0`. Its CI added 8.6 on 2026-11-05 ([`aab177d`](https://github.com/laravel/pint/commit/aab177d2333356888b17e85b4fe85933c076d0d9)), and the latest runs pass.
  - PHP-CS-Fixer, which Pint builds on, only declares syntax support up to 8.5 (`ConfigInterface::PHP_VERSION_SYNTAX_SUPPORTED = '8.5'`), and its 8.6 work is still open ([#9954](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/pull/9954), [#9944](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/pull/9944)). Pint calls `setUnsupportedPhpVersionAllowed(true)` ([`ConfigurationFactory.php` @ `1cf8e03`](https://github.com/laravel/pint/blob/1cf8e0320bed4b3c6e51df92039a5636c3887728/app/Factories/ConfigurationFactory.php), the v1.32.3 source), so it runs on 8.6 regardless. The only risk is formatting 8.6-only syntax, and the package uses none.
- **Filament v5**
  - Filament requires `php: ^8.2`. Its 5.x CI runs PHP 8.6 on Laravel 13 and deliberately excludes 8.6 on Laravel 12 ("ci: respect Laravel PHP compatibility boundaries", [`9708193`](https://github.com/filamentphp/filament/commit/9708193bc69c796d39ba7fe8f91a1663762ca591), 2026-11-20).
  - Its 8.6 job on 2026-11-26 ran Laravel 13.44.1 with Livewire 4.4.10: 11,594 passed and 12 skipped.
  - PHP 8.6 adds a stateless closure cache ([php-src UPGRADING](https://github.com/php/php-src/blob/PHP-8.6/UPGRADING), [RFC](https://wiki.php.net/rfc/closure-optimizations#stateless_closure_caching)), so identical static closures can share one object. That broke Filament's render hooks and visibility cache ([#21057](https://github.com/filamentphp/filament/issues/21057)). The fixes ([#21067](https://github.com/filamentphp/filament/pull/21067), [#21068](https://github.com/filamentphp/filament/pull/21068), [#21069](https://github.com/filamentphp/filament/pull/21069), [#21071](https://github.com/filamentphp/filament/pull/21071)) are in [v5.12.0](https://github.com/filamentphp/filament/releases/tag/v5.12.0), which is already this package's floor.
- **Livewire 4**
  - Livewire requires `php: ^8.1`, uncapped. Its 4.x CI runs 8.1–8.5.
  - The one PHP 8.6 PR, [#10699](https://github.com/livewire/livewire/pull/10699), was closed unmerged ("closing for now"). It guarded component lookup against NUL-byte paths, which PHP 8.6's filesystem functions now reject with `ValueError`.
  - Filament's passing 8.6 suite is the best evidence that Livewire 4.4.10 works on 8.6.
- **`laravel/pulse`**: requires `php: ^8.2`, uncapped. CI runs 8.2–8.4 plus 8.5. There is no 8.6 issue or PR.
- **Others**: `spatie/laravel-package-tools` tests 8.6 since [#190](https://github.com/spatie/laravel-package-tools/commit/132077d5f74d3a6b57ed5d2a47f6f3e71be419f4). `laravel/boost` requires `^8.2`, uncapped, and tests 8.2–8.5. Every package in the installed tree that caps PHP allows 8.6: `brianium/paratest` 7.29.0, `nette/*` and `openspout/openspout`.
- **The package's own code**: a scan of `src/`, `tests/` and `workbench/app/` found none of the functions PHP 8.6 deprecates. `phpunit.xml.dist` doesn't set `failOnDeprecation`, so a vendor deprecation wouldn't fail the suite.
- **Xdebug and PCOV (local `composer mutate` only, CI uses `coverage: none`)**
  - Xdebug's [compat table](https://xdebug.org/docs/compat) lists only 3.6 for PHP 8.6, and the only 3.6 release is 3.6.0alpha1 (2026-09-08, [updates](https://xdebug.org/updates)). Homebrew's `shivammathur/extensions/xdebug@8.6` builds a master snapshot (`5e19866`) labelled 3.5.0.
  - PCOV's last release is 1.0.12 (2024-12-04, [PECL](https://pecl.php.net/package/pcov)). The PHP 8.6 build fix, [krakjoe/pcov#134](https://github.com/krakjoe/pcov/pull/134), merged on 2026-11-24 and isn't released. Homebrew's `pcov@8.6` formula patches 1.0.12 to build.
  - Keep running `composer mutate` on PHP 8.5 until Xdebug 3.6.0 is stable.

## Resolution check

To check the tree as a whole, I ran Composer's resolver against a copy of `composer.json` with `config.platform.php` set to `8.6.0`. These were `composer update --dry-run` runs, using CI's `--with=laravel/framework:^…` flag.

- **Laravel 13, highest: resolves.** It picks `laravel/framework` 13.44.1, `pestphp/pest` 5.3.7, `phpunit/phpunit` 13.4.7, `brianium/paratest` 7.29.0, `filament/filament` 5.12.0, `livewire/livewire` 4.4.10, `laravel/pulse` 1.11.0, `orchestra/testbench` 11.3.0, `larastan/larastan` 3.15.0, `phpstan/phpstan` 2.3.7 and `laravel/pint` 1.32.3. Those are the same versions as today's install on PHP 8.5.
- **Laravel 13, lowest: resolves**, but on `laravel/framework` 13.40.0, Pest 5.3.0 and PHPUnit 13.3.6, all older than their 8.6 fixes. That's a reason to keep PHP 8.6 off the lowest jobs, which run only on 8.3 today anyway.
- **Laravel 12, highest: fails.** Composer's reasons, in short:
  - `brianium/paratest[v7.21.0, ..., v7.24.1] require php ~8.4.0 || ~8.5.0`, and every paratest release that allows 8.6 requires `phpunit/phpunit ^13.3.4` or later.
  - `orchestra/testbench-core[v10.15.0, ..., v10.16.1] conflict with phpunit/phpunit 13.x`.
  - `orchestra/testbench v10.12.0 requires symfony/process ^7.2`, but `pestphp/pest[v5.3.5, ..., v5.3.7] require symfony/process ^8.1.10`.
- **Laravel 12, runtime only** (`require-dev` removed): resolves, on `laravel/framework` 12.74.0. So users on Laravel 12 with PHP 8.6 can install the package. It's only the test toolchain that can't.

## Signals to watch

None of these blocks adding the Laravel 13 job.

- **Pest plugins**: merges and releases of [pest-plugin-laravel#82](https://github.com/pestphp/pest-plugin-laravel/pull/82), [pest-plugin-browser#330](https://github.com/pestphp/pest-plugin-browser/pull/330) and [pest-plugin-mutate#54](https://github.com/pestphp/pest-plugin-mutate/pull/54).
- **setup-php**: the first release after 2.40.0, which will carry [`2001cd3`](https://github.com/shivammathur/setup-php/commit/2001cd3148dcbaf467b7fa06cd7155ebb6d4cacf). Dependabot will open the bump.
- **Laravel's docs**: the support table in [laravel/docs `13.x/releases.md`](https://github.com/laravel/docs/blob/13.x/releases.md) listing PHP 8.6 for Laravel 13.
- **Livewire and Pulse**: 8.6 added to [livewire/livewire `test-4.x.yml`](https://github.com/livewire/livewire/blob/4.x/.github/workflows/test-4.x.yml) and [laravel/pulse `tests.yml`](https://github.com/laravel/pulse/blob/1.x/.github/workflows/tests.yml).
- **Laravel 12 on PHP 8.6**: no signal is expected. It would take Laravel 12.x adding 8.6 to [its CI](https://github.com/laravel/framework/blob/12.x/.github/workflows/tests.yml), which is unlikely now that 12.x gets security fixes only, plus a [paratest release](https://github.com/paratestphp/paratest/releases) that allows PHP 8.6 with PHPUnit 12. The likely end is dropping Laravel 12, which #11 already plans for a later minor release.
- **Local mutation testing**: Xdebug 3.6.0 stable ([xdebug.org/updates](https://xdebug.org/updates)), or a PCOV release after [krakjoe/pcov#134](https://github.com/krakjoe/pcov/pull/134).

## What I couldn't verify

- **This package's suite on PHP 8.6.** This machine has PHP 8.5.12 only, and the Docker daemon wasn't running. I didn't install PHP 8.6. Everything above about 8.6 comes from other projects' CI and from Composer's resolver, so the first CI run on the PR that adds the job is the real test.
- **Extensions.** I didn't check one by one that setup-php's 8.6 build has every extension CI asks for. Laravel's 8.6 job asks for `dom`, `mbstring`, `pdo_sqlite` and `zip`, but I saw no 8.6 log that shows `intl` or `sockets`.
- **Browser tests on 8.6.** The browser plugin's own suite passes on 8.6 in #330, but this package's browser tests weren't run on it.
- **Homebrew's `xdebug@8.6` snapshot.** I didn't check whether it works on 8.6.0 GA.
- **Pest's wording.** No Pest release note says "PHP 8.6 support" in words. I read the CI change in v5.3.4 as the declaration.
