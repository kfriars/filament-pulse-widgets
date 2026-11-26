# What a Pulse card exposes to the page hosting it

Research for [#4](https://github.com/kfriars/filament-pulse-widgets/issues/4). Throwaway branch, never merged.

**Sources read** (all primary; nothing below comes from a secondary write-up):

- `laravel/pulse` at tag **v1.11.0** (commit `e84f5b3`). The `1.x` branch head is one commit past it, and that commit only touches `CHANGELOG.md`. Links below point at the tag: `https://github.com/laravel/pulse/blob/v1.11.0/<path>`.
- Pulse docs: the `laravel/docs` repo, `13.x/pulse.md` (rendered at <https://laravel.com/docs/13.x/pulse>). Apart from a cache-serialization note, `12.x` has the same text.
- `livewire/livewire` at tag **v4.4.10** (latest 4.x tag on 2026-11-25), source plus the `docs/` folder (rendered at `https://livewire.laravel.com/docs/4.x/...`).
- `filamentphp/filament` at tag **v5.12.0**, only the files about middleware and widgets that bear on authorization.
- `laravel/sentinel` (`HEAD`), which Pulse's route group uses.

I read the code only. Nothing was executed. Claims marked **(inferred)** come from reading and were not tested.

---

## TL;DR

| Question | Answer |
|---|---|
| Props | Every **public property** on a Pulse card is a prop. The `Card` base class gives `cols`, `rows`, `expand`, `class` and `period`. Card-specific ones: `servers` takes `ignore-after`, `sort-by` and `sort-direction`. `usage` takes `type` (and a `#[Url]` `usage`). `slow-queries` takes `without-highlighting`. The `exceptions` and `slow-*` cards take a `#[Url]` `orderBy`. `lazy`/`defer` are Livewire-reserved params. **No card has a `limit` prop, and no card has `ignore-after-seconds`.** |
| Grid | `cols`/`rows` become `default:lg:col-span-{cols}` and `default:row-span-{rows}` classes on the card's root `<section>`. They only mean something inside a CSS grid like `<x-pulse>` (12 columns). Defaults are 6 columns and 1 row, or full width for `servers`. |
| Period | `public ?string $period = '1_hour'` with `#[Url]`, in the `HasPeriod` trait. A card reads `?period=` **once, at mount**. Pulse's period selector does **not** talk to cards: it sets `?period=` and reloads the whole page. There are no events and no reactive props. |
| Polling | `wire:poll.5s=""` sits **in each card's own view**: on the `<x-pulse::scroll>` element for most cards, on the root `<section>` for `servers`. The host can't configure it. The Pulse docs make it optional for custom cards. |
| Authorization | Only Pulse's `Authorize` middleware enforces the `viewPulse` gate, on the `/pulse` route. Pulse registers that middleware as Livewire persistent middleware, but Livewire re-applies only the middleware that was on the **original page's route**. `Card` never checks the gate. **So when a Pulse card is rendered on a Filament page, its `/livewire/update` requests (polls, the order-by select, lazy load) are not checked against `viewPulse`.** They are still checked by Filament's panel authentication. |
| Extension points | Extend `Laravel\Pulse\Livewire\Card` and implement `render()`. The view should use `<x-pulse::card :cols :rows :class>`, `<x-pulse::card-header>` and `<x-pulse::scroll :expand>`. Data comes from `aggregate`, `aggregateTypes`, `aggregateTotal`, `graph`, `values` and `remember`. Extra CSS comes from `protected function css()`, which is collected by `Pulse::css()` **only on non-Livewire requests**. |

---

## 1. Props and attributes

### 1.1 How Livewire 4 turns tag attributes into props

- When a component mounts, Livewire splits the passed params into **component params** and **HTML attributes**. Kebab-case keys are camel-cased first (`ignore-after` becomes `ignoreAfter`). A key that matches a public property or a `mount()` parameter is a prop. `lazy`, `defer`, `lazy.bundle`, `defer.bundle`, `wire:ref` and `@…` are reserved. Every other key becomes an HTML attribute. See [`HandleComponents.php` L87-L143](https://github.com/livewire/livewire/blob/v4.4.10/src/Mechanisms/HandleComponents/HandleComponents.php#L87-L143).
- Props are assigned to the matching properties in an `on('mount')` listener ([`SupportNestingComponents.php` L34-L38, L118](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportNestingComponents/SupportNestingComponents.php#L34-L38)). This happens **even for lazy components**: the lazy hook only skips the component's own `mount()` method (`skipMount`, [`SupportLifecycleHooks.php` L25-L27](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportLifecycleHooks/SupportLifecycleHooks.php#L25-L27)).
- Leftover HTML attributes go to the card view as `$attributes` ([`SupportHtmlAttributeForwarding.php`](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportHtmlAttributeForwarding/SupportHtmlAttributeForwarding.php)). **No first-party Pulse card view echoes `$attributes`**, so something like `id="…"` passed to a first-party card is dropped. Use the `class` prop instead.
- So for a Pulse widget, the contract for "props" is **any public property of the card class**, passed as an `array<string, mixed>` to `@livewire($name, $params)` or `<livewire:dynamic-component>`. These properties are also **client-mutable**, because none of them is `#[Locked]`.

### 1.2 Props from the `Card` base class

From [`src/Livewire/Card.php` L13-L39](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Card.php#L13-L39) and [`Concerns/HasPeriod.php` L15-L16](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Concerns/HasPeriod.php#L15-L16):

| Prop | Type / default | Effect |
|---|---|---|
| `cols` | `int\|string\|null` = `null`. Allowed values: 1–12 or `'full'` | Becomes the `default:lg:col-span-{cols}` class. `null` falls back to the Blade component's default of **6** ([`components/card.blade.php` L1-L3](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/card.blade.php#L1-L3)). |
| `rows` | `int\|string\|null` = `null`. Allowed values: 1–6 or `'full'` | Becomes the `default:row-span-{rows}` class. Default is **1**. |
| `expand` | `bool` = `false` | Passed to `<x-pulse::scroll :expand>`. When `false`, the body gets `basis-56` (14rem) and scrolls. When `true`, the body grows to fit its content ([`components/scroll.blade.php` L1, L23](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/scroll.blade.php#L23)). The `servers` card has no scroll area, so `expand` does nothing there. |
| `class` | `string` = `''` | Merged into the card's root `<section>` classes. |
| `period` | `?string` = `'1_hour'`, with `#[Url]`. Values: `1_hour`, `6_hours`, `24_hours`, `7_days` | The window that queries look back over (see §2). Unknown values fall back to 1 hour (`match … default => 1`). |

`Card::placeholder()` ([L44-L51](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Card.php#L44-L51)) renders [`components/placeholder.blade.php`](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/placeholder.blade.php) with `cols`, `rows` and `class`, so lazy placeholders take up the same grid space as the loaded card.

### 1.3 Livewire-reserved params

- **`lazy`**: every first-party card has the `#[Lazy]` class attribute. The Pulse docs also tell custom cards to use `#[Lazy]` ([docs, Card Components](https://laravel.com/docs/13.x/pulse#custom-card-components)). In Livewire 4 ([`SupportLazyLoading.php` L48-L106](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportLazyLoading/SupportLazyLoading.php#L48-L106)):
  - `:lazy="false"` turns the attribute off.
  - `lazy="on-load"` or `defer` loads the card on `x-init` instead of `x-intersect` (when it scrolls into view).
  - `lazy.bundle` and `defer.bundle` send the lazy loads in one request. Lazy requests are isolated, one per card, by default.
  - Each lazy card makes its own `__lazyLoad` request to `/livewire/update`.
- `wire:key` (Livewire) can be used to force a card to remount (see §2.4).

### 1.4 Card-specific props (first-party cards)

Every first-party card class is marked `@internal`. Their tag names are registered in [`PulseServiceProvider.php` L193-L202](https://github.com/laravel/pulse/blob/v1.11.0/src/PulseServiceProvider.php#L193-L202).

| Tag | Class | Extra public properties (props) | Notes |
|---|---|---|---|
| `pulse.servers` | [`Servers`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Servers.php#L21-L25) | `ignoreAfter` (`int\|string\|null`; attribute `ignore-after`; seconds, or a relative string such as `"3 hours"`), `sortBy` (`'name'`), `sortDirection` (`'asc'`) | Doesn't use `<x-pulse::card>`. It has its own root `<section>` with no card chrome, and `cols` defaults to `'full'` ([`servers.blade.php` L12-L28](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/servers.blade.php#L12-L28)). Its own placeholder is `servers-placeholder`. The docs spell the prop `ignore-after` ([docs, Servers](https://laravel.com/docs/13.x/pulse#servers-card)). **`ignore-after-seconds` doesn't exist.** `sortBy` was added in PR #477 and isn't in the docs. |
| `pulse.usage` | [`Usage`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Usage.php#L26-L34) | `type` (`'requests'\|'slow_requests'\|'jobs'\|null`), `usage` (`#[Url]`, `'requests'`) | `type` fixes the metric and hides the dropdown. Without `type`, the dropdown binds `wire:model.live="usage"`. Top 10 is hard-coded (`limit: 10`, [L52](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Usage.php#L52)). |
| `pulse.slow-queries` | [`SlowQueries`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/SlowQueries.php#L26-L39) | `orderBy` (`#[Url(as: 'slow-queries')]`, `'slowest'\|'count'`), `withoutHighlighting` (`bool`), `disableHighlighting` (`bool`, deprecated) | |
| `pulse.exceptions` | [`Exceptions`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Exceptions.php#L24-L25) | `orderBy` (`#[Url(as: 'exceptions')]`, `'count'\|'latest'`) | |
| `pulse.slow-requests` | [`SlowRequests`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/SlowRequests.php#L26-L27) | `orderBy` (`#[Url(as: 'slow-requests')]`) | |
| `pulse.slow-jobs` | [`SlowJobs`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/SlowJobs.php#L26-L27) | `orderBy` (`#[Url(as: 'slow-jobs')]`) | |
| `pulse.slow-outgoing-requests` | [`SlowOutgoingRequests`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/SlowOutgoingRequests.php#L26-L27) | `orderBy` (`#[Url(as: 'slow-outgoing-requests')]`) | |
| `pulse.queues` | [`Queues`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Queues.php) | none | Has a Chart.js chart. |
| `pulse.cache` | [`Cache`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Cache.php) | none | |
| `pulse.period-selector` | [`PeriodSelector`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/PeriodSelector.php) | `period` (`#[Url]`) | **Not a `Card`.** It's the dashboard's header control (§2). |

**About `limit`:** there is no `limit` prop on any card. `Card::aggregate()` and `aggregateTypes()` take a `$limit = 101` PHP argument ([`Card.php` L110-L135](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Card.php#L110-L135)), and the `slow-*` views show at most 100 rows with a "Limited to 100 entries" note (e.g. [`slow-queries.blade.php` L60, L101](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/slow-queries.blade.php#L60)).

The `#[Url]` keys (`period`, `usage`, `exceptions`, `slow-queries`, `slow-requests`, `slow-jobs`, `slow-outgoing-requests`) are **page-global query-string names**. Two instances of the same card on one page share one key, and a host page that uses the same name for something else will collide with it.

### 1.5 How the Pulse dashboard's grid uses them

- [`dashboard.blade.php` L1-L19](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/dashboard.blade.php) is the default layout: `servers cols="full"`, `usage cols="4" rows="2"`, `queues`/`cache cols="4"`, `slow-queries cols="8"`, and the rest `cols="6"`.
- [`components/pulse.blade.php` L2, L48](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/pulse.blade.php#L48) takes props `cols` (default 12) and `fullWidth` and renders `<div class="mx-auto grid default:grid-cols-{cols} default:gap-6 container">`.
- A card's root carries `default:col-span-full default:lg:col-span-{cols} default:row-span-{rows}`. That makes it full width below `lg` and `cols` columns from `lg` up. The rows have no fixed height, so `rows="2"` means "as tall as two neighbouring auto rows".
- `default:` is a custom Tailwind variant, `html :where(&)`, which has zero specificity, so any host CSS overrides it ([`tailwind.config.js`](https://github.com/laravel/pulse/blob/v1.11.0/tailwind.config.js)). `grid-cols-*` and `(row|col)-span-*` are safelisted for that variant.
- **For a Pulse widget (inferred):** outside a CSS grid parent, the `col-span`/`row-span` classes do nothing, so Filament's own widget `columnSpan` decides the width. Card roots also carry `@container`, so card internals respond to the card's own width.

---

## 2. How the period reaches a card

### 2.1 Mechanism

- `HasPeriod` defines `#[Url] public ?string $period = '1_hour'`, plus `periodAsInterval()` (1, 6, 24 or 168 hours) and `periodForHumans()` ([`HasPeriod.php` L15-L42](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Concerns/HasPeriod.php#L15-L42)).
- Every query helper on `Card` (`graph`, `aggregate`, `aggregateTypes`, `aggregateTotal`) passes `$this->periodAsInterval()`. `remember()` caches results for 5 seconds under `laravel:pulse:{class}:{key}:{period}` ([`RemembersQueries.php` L20-L29](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Concerns/RemembersQueries.php#L20-L29)).
- Livewire 4's `#[Url]` sets the property **in its `mount` hook** ([`BaseUrl.php` L22-L27, L163-L185](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportQueryString/BaseUrl.php#L163-L185)):
  - On a normal page request it reads `request()->query('period')`.
  - On a Livewire request (`X-Livewire` header) it reads the query string from the **`Referer` header**.
  - If the key is missing, the property keeps its current value.
  - Prop assignment happens before attribute mount hooks (`SupportNestingComponents` is registered as a listener before `ComponentHookRegistry::boot()`, [`LivewireServiceProvider.php` L161-L217](https://github.com/livewire/livewire/blob/v4.4.10/src/LivewireServiceProvider.php#L161-L217)). So the precedence is **`?period=` in the URL > `period` prop > `'1_hour'`**.
  - This all runs on the lazy placeholder render too, because attribute hooks aren't skipped by `skipMount`.
- After mount, `period` is ordinary component state that travels in the snapshot. The `#[Url]` effect only rewrites the browser URL when the value changes client-side. When the initial value isn't in the URL and hasn't changed, the URL is left alone ([`js/plugins/history/index.js` `track()`, L43-L78](https://github.com/livewire/livewire/blob/v4.4.10/js/plugins/history/index.js#L43-L78)). So passing `period` as a prop does not pollute the host's URL.

### 2.2 How Pulse's period selector changes it

[`livewire/period-selector.blade.php` L1-L19](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/period-selector.blade.php):

```js
setPeriod(period) {
    let query = new URLSearchParams(window.location.search)
    if (period === '1_hour') { query.delete('period') } else { query.set('period', period) }
    window.location = `${location.pathname}?${query}`
}
```

It's a **full page navigation**. There are no Livewire events, no `$dispatch`, no `#[Reactive]` and no `#[On]`. Every card picks up the new period from the query string when it remounts. The `PeriodSelector` component itself ([`PeriodSelector.php`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/PeriodSelector.php)) only highlights the active button. It is `@internal`.

### 2.3 Other in-card state

`orderBy` and `usage` are changed **inside the card** with `wire:model.live` selects (e.g. [`slow-queries.blade.php` L30](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/slow-queries.blade.php#L30), [`usage.blade.php` L23](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/usage.blade.php#L23)). Because they are `#[Url]`, they're written back to the host page's query string.

### 2.4 What this leaves a host (inferred, from Livewire 4 docs and code)

Livewire props are **not reactive by default** ([`docs/nesting.md` "Reactive props"](https://livewire.laravel.com/docs/4.x/nesting#reactive-props)), and `period` isn't `#[Reactive]`. Re-rendering a parent with a new `period` therefore does **not** update an already-mounted card. A host has three ways to change a card's period:

- **(a)** Do what Pulse does: set `?period=` and reload.
- **(b)** Remount the card with a `wire:key` that includes the period, passing `period` as a prop.
- **(c)** Set it client-side with `$wire.$set('period', …)` on the card. This works because the property is public and not locked.

---

## 3. Polling

- Polling is **in each card's view, not in `Card`**. Most cards have `wire:poll.5s=""` on `<x-pulse::scroll>`: [cache L27](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/cache.blade.php#L27), [exceptions L24](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/exceptions.blade.php#L24), [queues L37](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/queues.blade.php#L37), [slow-jobs L24](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/slow-jobs.blade.php#L24), [slow-outgoing-requests L38](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/slow-outgoing-requests.blade.php#L38), [slow-queries L42](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/slow-queries.blade.php#L42), [slow-requests L24](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/slow-requests.blade.php#L24) and [usage L46](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/usage.blade.php#L46). The `servers` card has `wire:poll.5s` on its root `<section>` ([servers L17](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/servers.blade.php#L17)).
- The interval is **5 seconds**, hard-coded, and there is no prop for it. An empty expression means `$refresh`, i.e. a full re-render. Query results are cached for the same 5 seconds by `remember()`.
- Custom cards: the docs put `wire:poll.5s=""` on the root `<x-pulse::card>` and call it optional ("You may also wish to include…", [docs, Card Components](https://laravel.com/docs/13.x/pulse#custom-card-components)). **A host can't assume a custom card polls, or polls at 5 seconds.**
- Livewire 4's `wire:poll` behaviour ([`js/directives/wire-poll.js` L5-L45](https://github.com/livewire/livewire/blob/v4.4.10/js/directives/wire-poll.js#L5-L45)):
  - It skips about 95% of ticks while the tab is hidden, unless `.keep-alive` is set.
  - It pauses while offline or when the session has expired, and pauses off-screen only with `.visible`.
  - All directives with the same interval share one `setInterval`.
- The `queues` and `servers` cards push new chart data with `$this->dispatch('queues-chart-update' | 'servers-chart-update')`, but only on Livewire requests ([`Queues.php` L29-L31](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Queues.php#L29-L31), [`Servers.php` L59-L61](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Servers.php#L59-L61)). `@script` blocks catch the events with `Livewire.on(...)`.

---

## 4. Authorization

### 4.1 How the Pulse dashboard is guarded

- The gate: `viewPulse` is defined as `fn ($user = null) => $app->environment('local')` ([`PulseServiceProvider.php` L97-L102](https://github.com/laravel/pulse/blob/v1.11.0/src/PulseServiceProvider.php#L97-L102)). Apps override it with `Gate::define('viewPulse', …)` ([docs, Authorization](https://laravel.com/docs/13.x/pulse#dashboard-authorization)).
- The middleware: [`Http/Middleware/Authorize.php` L22-L27](https://github.com/laravel/pulse/blob/v1.11.0/src/Http/Middleware/Authorize.php#L22-L27) just calls `$this->gate->authorize('viewPulse')`.
- The route: the `pulse` middleware group is `[SentinelMiddleware::class.':pulse', ...config('pulse.middleware')]` ([`PulseServiceProvider.php` L81-L84](https://github.com/laravel/pulse/blob/v1.11.0/src/PulseServiceProvider.php#L81-L84)). The default config is `['web', Authorize::class]` ([`config/pulse.php` L123-L126](https://github.com/laravel/pulse/blob/v1.11.0/config/pulse.php#L123-L126)). `GET /{pulse.path}` uses that group ([L107-L122](https://github.com/laravel/pulse/blob/v1.11.0/src/PulseServiceProvider.php#L107-L122)), and `Pulse::ignoreRoutes()` turns the route off.
  - `SentinelMiddleware` (laravel/sentinel) only acts in the `local` environment, where it blocks access through tunnels and reverse proxies ([`Drivers/Laravel.php`](https://github.com/laravel/sentinel/blob/HEAD/src/Drivers/Laravel.php)). It isn't persistent and doesn't matter outside `/pulse`.
- **Livewire persistence:** Pulse calls `Livewire::addPersistentMiddleware(config('pulse.middleware'))`, with any `:args` stripped ([`PulseServiceProvider.php` L184-L191](https://github.com/laravel/pulse/blob/v1.11.0/src/PulseServiceProvider.php#L184-L191)). With the default config, the persistent list gains `'web'` and `Laravel\Pulse\Http\Middleware\Authorize`. Component registration and persistent middleware are set up whether or not the route is registered.
- Pulse's own test shows the design: a card snapshot from `/pulse` replayed to the Livewire update endpoint calls the gate a second time ([`tests/Feature/AuthTest.php` L31-L65](https://github.com/laravel/pulse/blob/v1.11.0/tests/Feature/AuthTest.php#L31-L65)). Another test checks that Pulse's gate is **not** applied to other Livewire requests (L67-L80).

### 4.2 How Livewire 4 persistent middleware decides what to re-run

From [`PersistentMiddleware.php`](https://github.com/livewire/livewire/blob/v4.4.10/src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php):

1. On `dehydrate`, Livewire stores the **original page's** `path`, `method`, `origin` and route `{uri, domain}` in the snapshot memo (L37-L45). A child rendered during a Livewire request inherits its parent's values (L87-L94).
2. On every `/livewire/update` (`snapshot-verified`), Livewire builds a fake request for that original path, matches the **original route**, and gathers that route's middleware. It runs **only the middleware that is both on that route and in the persistent list** (L113-L243). If the original route had no persistent middleware, nothing runs (L134).
3. Matching uses exact class names (`Str::before($value, ':') == $persistent`, L238). A subclass doesn't match its parent class.

The docs say the same thing: "If any of the above middlewares are applied to the initial page-load, they will be persisted" ([`docs/security.md` "Configuring persistent middleware"](https://livewire.laravel.com/docs/4.x/security#configuring-persistent-middleware)).

### 4.3 Does `Card` check the gate itself?

**No.** The only references to `viewPulse` in `src/` are the gate definition and `Authorize`. `Card` (and every first-party card) has no `mount`/`boot`/`hydrate` authorization and doesn't use `AuthorizesRequests`.

### 4.4 So: a Pulse card rendered on a Filament page

- The original route is the Filament page route. Unless that route carries `Laravel\Pulse\Http\Middleware\Authorize`, the card's update requests run **no `viewPulse` check**. That covers the lazy load, every 5-second poll, and `orderBy`/`usage` changes.
- They are still covered by Filament's persistent middleware. Filament registers `Filament\Http\Middleware\Authenticate` (which enforces `canAccessPanel()`), `SetUpPanel`, `IdentifyTenant` and others as persistent ([`FilamentServiceProvider.php` L152-L162](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/FilamentServiceProvider.php#L152-L162)). So the protection is "**any panel user**", not "**users who pass `viewPulse`**".
- A Filament widget's own `canView()` is re-checked whenever **the widget** boots ([`CanAuthorizeAccess.php`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/widgets/src/Concerns/CanAuthorizeAccess.php)). A nested card sends **its own** update requests, which don't boot the parent widget, so the widget's `canView()` doesn't protect the card's polls **(inferred from Livewire's per-component update model; not tested)**.
- Snapshot checksums are an HMAC over the snapshot using the app key only, with no user or session binding ([`Checksum.php` L80-L92](https://github.com/livewire/livewire/blob/v4.4.10/src/Mechanisms/HandleComponents/Checksum.php#L80-L92)). That gives two consequences:
  - **(i)** A user whose `viewPulse` is revoked keeps receiving fresh data until the page reloads. This is exactly the case Livewire's persistent middleware exists to prevent.
  - **(ii)** Any panel user who gets hold of a card snapshot can replay it.
- **Options for a Pulse widget (inferred, not verified):**
  - Render the card only when `Gate::allows('viewPulse')`, as the widget's `canView()` or render guard. This is necessary but not enough.
  - **And** re-check on the card's own requests. Possible ways:
    - A global Livewire hook, such as `Livewire\on('hydrate', fn ($component) => …)` or a component hook that calls `Gate::authorize('viewPulse')` for `instanceof Laravel\Pulse\Livewire\Card`. This is generic for any Card subclass, but doesn't cover custom cards that don't extend `Card`.
    - Put `Laravel\Pulse\Http\Middleware\Authorize` on the Filament page's route middleware. It's already in Livewire's persistent list, but it gates the whole page.

---

## 5. Extension points a custom Pulse card uses

From the [docs, Custom Cards](https://laravel.com/docs/13.x/pulse#custom-cards) and [`Card.php`](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Card.php):

- **Class:** `abstract class Card extends Livewire\Component`, using `HasPeriod` and `RemembersQueries`. A custom card extends it, implements `render()`, and usually adds `#[Lazy]`.
  - Cards in a package must be registered with `Livewire::component(...)`.
  - Nothing *requires* a "Pulse card" to extend `Card`. Pulse's dashboard is just Blade plus `<livewire:…>` tags. The props in §1.2 only exist on `Card` subclasses.
- **Protected helpers on `Card`, all scoped to `period`:**
  - `values(string $type, ?array $keys)` (not period-scoped)
  - `graph(array $types, string $aggregate)`
  - `aggregate(string $type, string|array $aggregates, ?string $orderBy, string $direction = 'desc', int $limit = 101)`
  - `aggregateTypes(...)`
  - `aggregateTotal(...)`
  - `remember(callable $query, string $key = '', $ttl = 5)`, from `RemembersQueries`
  - `periodAsInterval()` and `periodForHumans()`, which are public
- **View contract.** This is a convention, not enforced:

  ```blade
  <x-pulse::card :cols="$cols" :rows="$rows" :class="$class" wire:poll.5s="">
      <x-pulse::card-header name="…" title="…" details="…">
          <x-slot:icon>…</x-slot:icon>
          <x-slot:actions>…</x-slot:actions>
      </x-pulse::card-header>
      <x-pulse::scroll :expand="$expand">…</x-pulse::scroll>
  </x-pulse::card>
  ```

  "The `$cols`, `$rows`, `$class`, and `$expand` variables should be passed to their respective Blade components so the card layout may be customized from the dashboard view" ([docs](https://laravel.com/docs/13.x/pulse#custom-card-components)). Other anonymous components live in `resources/views/components/`: `no-results`, `table`, `th`, `td`, `thead`, `select`, `user-card`, `http-method-badge` and `icons.*`. They're registered under the `pulse` namespace ([`PulseServiceProvider.php` L180-L182](https://github.com/laravel/pulse/blob/v1.11.0/src/PulseServiceProvider.php#L180-L182)).
- **`css()`:** `protected function css(): string|Htmlable|array|null` returns file paths or `Htmlable`s ([`Card.php` L58-L75](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Card.php#L58-L75)).
  - `Card::dehydrate()` calls `Pulse::css($this->css())` **only when `! Livewire::isLivewireRequest()`**.
  - `Pulse::css()` with no arguments returns all registered CSS inlined as `<style>` tags. The default is `dist/pulse.css` ([`Pulse.php` L88, L498-L517](https://github.com/laravel/pulse/blob/v1.11.0/src/Pulse.php#L498-L517)).
  - The dashboard echoes `{!! Pulse::css() !!}` in `<head>` ([`components/pulse.blade.php` L16](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/pulse.blade.php#L16)). That works because Blade renders the slot, with the cards, before the layout **(inferred from Blade component semantics)**.
  - `dehydrate()` also runs on the lazy placeholder render ([`SupportLifecycleHooks::dehydrate`](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportLifecycleHooks/SupportLifecycleHooks.php#L153-L161) isn't skipped by `skipMount`).
- **Page-level assets every first-party card assumes.** These come from the `<x-pulse>` layout, not from the card:
  - **CSS:** `dist/pulse.css` is a precompiled **Tailwind v3.4** build with Preflight, `darkMode: 'class'`, the `default:` variant and container queries.
  - **JS:** `Pulse::js()` ([`Pulse.php` L522-L535](https://github.com/laravel/pulse/blob/v1.11.0/src/Pulse.php#L522-L535)) inlines **the whole Livewire bundle** plus `dist/pulse.js`. `dist/pulse.js` defines `window.Chart` (Chart.js 4) and `window.formatDate` ([`resources/js/pulse.js`](https://github.com/laravel/pulse/blob/v1.11.0/resources/js/pulse.js)).
  - Every first-party card header calls `formatDate(...)` in `x-bind:title`. `queues` and `servers` also call `new Chart(...)` inside `@script` / `Alpine.data(...)`.
  - The theme switcher toggles `.dark` on `<html>`, which is the same convention Filament uses.

---

## Open / not confirmed

- I didn't run any code. Every behaviour above is read from source. The Blade render order (§5) and the "nested card polls don't boot the parent widget" claim (§4.4) are inferences.
- I didn't check custom cards that are Livewire 4 single-file or multi-file components, i.e. not a `Card` subclass. They are hostable by name, but they won't have the `Card` props.
- I didn't check how Filament's dashboard grid sizes rows. It decides whether `rows` means anything inside a Pulse widget.
