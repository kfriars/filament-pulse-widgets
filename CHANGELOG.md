# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-27

### Added

- A Pulse widget for each of the nine first-party Pulse cards: Servers, Usage, Queues, Cache, Slow Queries, Exceptions, Slow Requests, Slow Jobs and Slow Outgoing Requests.
- A Pulse widget for any custom Pulse card with `PulseWidget::make()->card(…)`, which brings the Pulse card's own `css()` with it.
- A period selector in each Pulse card's header, which sets the period for that Pulse widget.
- Access rules: Pulse widgets follow Pulse's `viewPulse` gate by default, the plugin's `->authorize()` replaces it, and each placement's `->visible()` and `->authorize()` narrow it further.
- Styling that works on Filament's default theme, with no custom theme to set up.
- Requires PHP 8.3+, Laravel 12 or 13, Filament 5.12+ and Pulse 1.11+.

[Unreleased]: https://github.com/kfriars/filament-pulse-widgets/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/kfriars/filament-pulse-widgets/releases/tag/v1.0.0
