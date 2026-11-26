# What Filament v5 gives a widget and a plugin

Research for [#5](https://github.com/kfriars/filament-pulse-widgets/issues/5). Throwaway branch, never merged.

**Sources read** (all primary; nothing below comes from a secondary write-up):

- `filamentphp/filament` branch **`5.x`** at commit `8eb96db` (2026-11-25). Every file cited below is byte-identical to tag **v5.12.0** (`a961cf3`, the latest v5 tag); between the two, only `Panel/Concerns/HasMiddleware.php`, `HasRoutes.php`, some resource/user docs and translations differ. Links point at the tag: `https://github.com/filamentphp/filament/blob/v5.12.0/<path>`. Where behaviour changed inside v5, I compared against tag `v5.0.0` and used the GitHub contents API to find the minor release that introduced a file.
- Filament v5 docs. These are the `docs/` and `packages/*/docs/` folders of the same repo, rendered at `https://filamentphp.com/docs/5.x/...`. I checked that every URL below returns 200, and spot-checked that the quoted sentences are live on the site.
- `livewire/livewire` tag **v4.4.10**. Filament requires `livewire/livewire: ^4.4.9` (`packages/support/composer.json`). I read only what Filament's widget plumbing depends on: mount params, lazy loading and lifecycle hooks.
- `laravel/pulse` tag **v1.11.0**. I read only `Card.php`, one concrete card, `card.blade.php`, `tailwind.config.js` and `dist/pulse.css`, enough to answer "could a Pulse card subclass be a widget?" (#4 covers Pulse cards in depth).

I read the code only. Nothing was executed. Claims marked **(inferred)** come from reading and were not tested.

---

## TL;DR

| Question | Answer |
|---|---|
| Base class | `Filament\Widgets\Widget` is an `abstract class` extending `Livewire\Component`. It adds an authorization trait, a lazy-loading trait, `$sort`, `$view`, `$columnSpan` and `$columnStart`. Nothing else. |
| Static API the page calls | **`::canView()`**, **`::getDefaultProperties()`**, and **`::getSort()`** (only for widgets registered on the panel). `::isDiscovered()` is used only by `discoverWidgets()`, which also requires `is_subclass_of(Widget::class)`. `::make()` is for users, not for the page. **Nothing at runtime checks `instanceof Widget` on the render path**, so a class that does not extend `Widget` can sit on a dashboard if it supplies those statics. |
| Instance side | The page wraps each widget in `<div class="fi-sc-liberated">`, which is `display: contents`. **The widget's own root element must therefore carry the grid placement.** Stock widgets do this with `<x-filament-widgets::widget>`, which calls `$this->getColumnSpan()` / `$this->getColumnStart()` (these are *instance* methods, not static). |
| Non-`Widget` class (Pulse card subclass) | **Possible, with caveats.** Add the three statics, make the view's root element emit `gridColumn(...)`, and re-implement authorization for lazy requests, because Filament's lazy-hydrate `canView()` check runs only for `instanceof Widget`. Discovery won't find the class; it must be listed in `->widgets([...])` or on the page. PHP attributes are not inherited, so a subclass of a Pulse card loses that card's `#[Lazy]`. |
| Grid | `Dashboard::getColumns()` returns `2`, which means `['lg' => 2]`: 1 column below `lg` (64rem) and 2 at `lg` and up. `$columnSpan` takes 1–12, `'full'`, or a per-breakpoint array. It is pure CSS-variable grid (`fi-grid`, `--cols-*`, `--col-span-*`). **There is no row-span.** |
| Page filters | The page holds a `#[Url] public ?array $filters`, persisted in the session per tenant. The page passes it to every widget as the **`pageFilters`** mount param. `InteractsWithPageFilters` declares `#[Reactive] public ?array $pageFilters`. `HasFiltersForm` updates live; `HasFiltersAction` + `FilterAction` updates on Apply. This works on **any** `Page` that has a `filters` property, not just `Dashboard`. |
| Polling | The base `Widget` has **no polling**. The `CanPoll` trait (`$pollingInterval = '5s'`) is used only by `StatsOverviewWidget` and `ChartWidget`, and each of those views writes its own `wire:poll`. |
| Lazy loading | **On by default** for every `Widget` (`CanBeLazyLoaded`, where `$isLazy = true`). `getDefaultProperties()` returns `['lazy' => true]`. Livewire 4 renders the placeholder with `x-intersect`, so the widget loads when scrolled into view. Turn it off with `protected static ?bool $isLazyLoaded = false` (v5.12+) or `protected static bool $isLazy = false` (all v5). |
| Height | No widget height API. Only `$placeholderHeight` (lazy placeholder, default `8rem`) and `ChartWidget::$maxHeight`. |
| Plugin | `Filament\Contracts\Plugin`: `getId()`, `register(Panel)`, `boot(Panel)`. `register()` runs as soon as the user calls `->plugin()` inside `panel()`. `boot()` runs only for the **current** panel, from the `SetUpPanel` middleware. Per-panel registration is done in `register()` with `$panel->pages([...])`, `->widgets([...])`, `->livewireComponents([...])` and `->assets([...])`. |
| Assets | `FilamentAsset::register([Css::make(...), Js::make(...), AlpineComponent::make(...)], package: 'vendor/name')` in a service provider's boot, or `$panel->assets([...])` for one panel only. `php artisan filament:assets` copies them to `public/css/vendor/name/{id}.css` (and the matching `js` path). `->loadedOnRequest()` combined with `x-load-css` / `x-load-js` loads a file only where it is used. |
| Tailwind classes in plugin views | **Not available without a custom theme.** The default panel stylesheet is Tailwind **v4.3.0** compiled with `source(none)`. It contains only `fi-*` component and utility classes: no `.flex`, `.p-4`, `.text-sm`, `.hidden`. The docs say a custom theme is required to use Tailwind classes. Their recommendation for plugin authors is to let users `@source` the plugin's views into their theme, and, if the plugin must work on the default stylesheet, to **ship a compiled stylesheet** with the styles it needs. |
| Cascade layers (implication) | Everything in Filament's CSS is inside `@layer theme/base/components/utilities`. Pulse's `dist/pulse.css` (Tailwind 3: preflight, `@tailwindcss/forms`, utilities) has **no layers**. Unlayered rules beat layered ones, so loading `pulse.css` into a panel would override Filament's button and input styling. |

---

## 1. The widget contract

### 1.1 The base class

[`packages/widgets/src/Widget.php`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/widgets/src/Widget.php):

- L11: `abstract class Widget extends Component` (`Livewire\Component`).
- L13–14: `use CanAuthorizeAccess; use CanBeLazyLoaded;`.
- L16: `protected static bool $isDiscovered = true`. L18: `protected static ?int $sort = null`.
- L23: `protected string $view` (instance property; `render()` at L79–83 returns `view($this->view, $this->getViewData())`).
- L28 / L33: `protected int | string | array $columnSpan = 1` and `$columnStart = []` (instance properties).

Its methods:

| Method | Kind | Lines | Default |
|---|---|---|---|
| `canView(): bool` | static | L35–39 | `true` |
| `getSort(): int` | static | L41–45 | `static::$sort ?? -1` |
| `getColumnSpan()` | **instance** | L50–54 | `$this->columnSpan` |
| `getColumnStart()` | **instance** | L59–63 | `$this->columnStart` |
| `getViewData(): array` | instance, protected | L68–71 | `[]` |
| `isDiscovered(): bool` | static | L73–77 | `static::$isDiscovered` |
| `make(array $properties = []): WidgetConfiguration` | static | L88–92 | `app(WidgetConfiguration::class, ['widget' => static::class, 'properties' => $properties])` |
| `getPlaceholderData(): array` | instance | L97–104 | `['columnSpan' => …, 'columnStart' => …]`, so the lazy placeholder occupies the same grid cell |
| `getDefaultProperties(): array` | static | L109–119 | `['lazy' => true]` when `static::isLazyLoaded()` |

All public methods carry `#[Unexposed]`. Filament's `MethodAccess` hook returns a 403 if the browser tries to call one ([`packages/support/src/Livewire/MethodAccess.php` L23](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/src/Livewire/MethodAccess.php#L23)).

[`WidgetConfiguration`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/widgets/src/WidgetConfiguration.php) is a value object: `public readonly string $widget` and `protected array $properties` (L11–14), plus `getProperties()` (L19–22). The `class-string<Widget>` type exists only in the docblock; nothing checks it at runtime.

### 1.2 What a page calls on a widget class

All call sites in `packages/` (excluding tests and generators):

| Caller | Call | Where |
|---|---|---|
| `Panel::getWidgets()`, which is what `Dashboard::getWidgets()` returns via `Filament::getWidgets()` | `::getSort()` to order panel widgets | [`panels/src/Panel/Concerns/HasComponents.php` L490–496](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Panel/Concerns/HasComponents.php#L490-L496); [`panels/src/Pages/Dashboard.php` L52–55](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Pages/Dashboard.php#L52-L55) |
| `Page::getWidgetsSchemaComponents()`, used by the dashboard, header and footer widgets | `::canView()` to drop hidden widgets before mounting | [`panels/src/Pages/Page.php` L629](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Pages/Page.php#L629) |
| same | `::getDefaultProperties()` (or `$config->widget::getDefaultProperties()` merged with `$config->getProperties()`) | [`Page.php` L635–638](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Pages/Page.php#L635-L638) |
| `Panel::discoverWidgets()` via `discoverComponents()` | `is_subclass_of($class, Widget::class)`, then `::isDiscovered()` (guarded by `method_exists`) | [`HasComponents.php` L386–403, L539–552](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Panel/Concerns/HasComponents.php#L539-L552) |
| `Panel::widgets([...])` | none; it queues the class for `Livewire::component()` registration under its Finder name | [`HasComponents.php` L228–244, L692–697](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Panel/Concerns/HasComponents.php#L228-L244) |
| deprecated `<x-filament-widgets::widgets>` Blade component | `::getDefaultProperties()` | [`widgets/resources/views/components/widgets.blade.php` L37](https://github.com/filamentphp/filament/blob/v5.12.0/packages/widgets/resources/views/components/widgets.blade.php#L37) |

`getWidgetsSchemaComponents()` (L625–643) turns each widget into a schemas `Livewire` component:

```php
Livewire::make($widgetClass, fn (): array => [
    ...$this->getWidgetData(),
    ...$data,
    ...($widget instanceof WidgetConfiguration ? [...$widget->widget::getDefaultProperties(), ...$widget->getProperties()] : $widget::getDefaultProperties()),
    ...(property_exists($this, 'filters') ? ['pageFilters' => $this->filters] : []),
])->key("{$widgetClass}-{$widgetKey}")->liberatedFromContainerGrid()
```

The schemas component also adds `'record' => $this->getRecord()` (null on a dashboard). It then calls `Livewire::mount($component, $properties, $key)` ([`schemas/src/Components/Livewire.php` L107–121, L128–149](https://github.com/filamentphp/filament/blob/v5.12.0/packages/schemas/src/Components/Livewire.php#L107-L149)).

So every widget is mounted with these params: `lazy` (if lazy), `record` (null), `pageFilters` (if the page has `filters`), the page's `getWidgetData()`, and `make([...])` props. Livewire 4 routes any param that is neither a public property nor a `mount()` argument into the component's HTML attributes. Scalars go into the view's `$attributes` bag and arrays become plain view variables ([`livewire/src/Mechanisms/HandleComponents/HandleComponents.php` L42–53, L87–123](https://github.com/livewire/livewire/blob/v4.4.10/src/Mechanisms/HandleComponents/HandleComponents.php#L87-L123); [`SupportHtmlAttributeForwarding.php` L43–50](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportHtmlAttributeForwarding/SupportHtmlAttributeForwarding.php#L43-L50)). Unknown params are therefore harmless. They are also **not reactive**: a widget without a `pageFilters` property never sees filter changes.

### 1.3 What the widget must do itself: grid placement

- The dashboard grid is `Grid::make($this->getColumns())` ([`Dashboard.php` L100–104](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Pages/Dashboard.php#L100-L104)).
- Each widget is marked `liberatedFromContainerGrid()`, so `Schema` renders it as `<div class="fi-sc-liberated" wire:key=…>{widget html}</div>` ([`schemas/src/Schema.php` L255–265](https://github.com/filamentphp/filament/blob/v5.12.0/packages/schemas/src/Schema.php#L255-L265)), and that wrapper is `@apply contents` ([`schemas/resources/css/schema.css` L4–6](https://github.com/filamentphp/filament/blob/v5.12.0/packages/schemas/resources/css/schema.css#L4-L6)).
- So **the Livewire component's root element is the grid item**. Stock widgets wrap their view in `<x-filament-widgets::widget>`, which is just `<div {{ $attributes->gridColumn($this->getColumnSpan(), $this->getColumnStart())->class(['fi-wi-widget']) }}>` ([`widgets/resources/views/components/widget.blade.php` L1–5](https://github.com/filamentphp/filament/blob/v5.12.0/packages/widgets/resources/views/components/widget.blade.php)). The generated custom-widget view is `<x-filament-widgets::widget><x-filament::section>…</x-filament::section></x-filament-widgets::widget>` ([`widgets/stubs/WidgetView.stub`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/widgets/stubs/WidgetView.stub)).
- `gridColumn()` is a `ComponentAttributeBag` macro. It emits `fi-grid-col` and `{bp}:fi-grid-col-span` classes plus `--col-span-{bp}: span N / span N` (`'full'` becomes `1 / -1`) ([`support/src/SupportServiceProvider.php` L259–324](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/src/SupportServiceProvider.php#L259-L324)). Those `fi-*` classes are compiled into Filament's default CSS ([`support/resources/css/components/grid.css` L209+](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/css/components/grid.css#L209)). **A plugin can position a Pulse widget in the grid without any Tailwind utility of its own.**

### 1.4 Could a class that does not extend `Widget` satisfy this? (e.g. a Pulse card subclass)

PHP allows one parent, and both `Filament\Widgets\Widget` and `Laravel\Pulse\Livewire\Card` are abstract classes extending `Livewire\Component` ([Pulse `src/Livewire/Card.php` L13](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Card.php#L13)). From the call sites above, a `Card` subclass works on a dashboard or page if it provides:

1. `public static function canView(): bool`
2. `public static function getDefaultProperties(): array`, returning `['lazy' => true]` or `[]`
3. `public static function getSort(): int`, needed if registered with `$panel->widgets([...])`
4. optionally `public static function make(array $props = []): WidgetConfiguration`, returning `new WidgetConfiguration(static::class, $props)`. The constructor is untyped beyond `string` (only PHPStan would object).
5. a root element in its view that carries `gridColumn(...)`. If the view uses `<x-filament-widgets::widget>`, the class also needs instance methods `getColumnSpan()` and `getColumnStart()`. (Pulse's own root is `<section class="… default:col-span-full default:lg:col-span-{cols} default:row-span-{rows}">` ([`resources/views/components/card.blade.php` L3](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/card.blade.php#L3)), which uses Pulse-only Tailwind 3 classes that mean nothing to Filament's grid.)

Caveats, all from source:

- **Authorization on lazy requests.** `CanAuthorizeAccess::bootCanAuthorizeAccess()` aborts 403 unless `static::canView()` ([`widgets/src/Concerns/CanAuthorizeAccess.php` L12–22](https://github.com/filamentphp/filament/blob/v5.12.0/packages/widgets/src/Concerns/CanAuthorizeAccess.php#L12-L22)). Livewire calls `boot{Trait}` hooks on any component ([`livewire/src/Features/SupportLifecycleHooks/SupportLifecycleHooks.php` L25–46](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportLifecycleHooks/SupportLifecycleHooks.php#L25-L46)), so a `Card` subclass may `use` the trait. But Livewire skips boot on a lazy component's first load. Filament covers that gap with a `Livewire::listen('hydrate', …)` that fires **only when `$component instanceof Widget`** ([`widgets/src/WidgetsServiceProvider.php` L28–33](https://github.com/filamentphp/filament/blob/v5.12.0/packages/widgets/src/WidgetsServiceProvider.php#L28-L33)). A non-`Widget` class needs its own listener. (That `canView()` check is also separate from Pulse's `viewPulse` gate; see #4.)
- **Discovery.** `discoverWidgets()` ignores non-subclasses (L543). The class must be listed explicitly.
- **`#[Lazy]` isn't inherited.** Livewire reads `#[Lazy]` with `ReflectionClass::getAttributes()` on the concrete class ([`SupportLazyLoading.php` L67–77](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportLazyLoading/SupportLazyLoading.php#L67-L77)). PHP does not return a parent class's attributes there, so `class MyServers extends \Laravel\Pulse\Livewire\Servers` drops Pulse's `#[Lazy]` ([`Servers.php` L16](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Servers.php#L16)). Conversely, a class that does carry `#[Lazy]` stays lazy even when `getDefaultProperties()` returns `[]`; only an explicit `lazy => false` param disables it (L72–75).
- **Placeholder.** Livewire uses the component's `placeholder()` method. `Card::placeholder()` renders `pulse::components.placeholder` with Pulse's grid classes ([`Card.php` L44–51](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Card.php#L44-L51)). Filament's version renders `filament::components.loading-section` with `gridColumn(...)` ([`support/src/Concerns/CanBeLazyLoaded.php` L37–52](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/src/Concerns/CanBeLazyLoaded.php#L37-L52); [`support/resources/views/components/loading-section.blade.php`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/views/components/loading-section.blade.php)). A `Card` subclass that `use`s `CanBeLazyLoaded` gets Filament's, because trait methods override inherited ones **(inferred)**.

The other shape is **composition**: a real `Widget` subclass whose view nests the Pulse card (`@livewire(...)`). It gets the whole contract (authorization, lazy, grid, filters) for free, at the cost of two Livewire components per Pulse widget. Choosing between the two is for the design tickets. Both are viable as far as Filament's code is concerned.

### 1.5 Polling

- The base `Widget` has no polling.
- [`widgets/src/Concerns/CanPoll.php`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/widgets/src/Concerns/CanPoll.php) gives `protected ?string $pollingInterval = '5s'` and `getPollingInterval()`. It is used only by `StatsOverviewWidget` and `ChartWidget`, and each view writes its own `wire:poll`: `stats-overview-widget.blade.php` L5/L17 and `chart-widget.blade.php` L93–94 (`wire:poll.{interval}="updateChartData"`).
- Docs: "Live updating stats/chart data (polling)". Set `$pollingInterval = '10s'`, or `null` to disable ([stats-overview](https://filamentphp.com/docs/5.x/widgets/stats-overview#live-updating-stats-polling), [charts](https://filamentphp.com/docs/5.x/widgets/charts#live-updating-chart-data-polling)).
- So polling is a per-view concern. Pulse cards hard-code `wire:poll.5s` in their own views (see #4), which matches Filament's convention.

### 1.6 Lazy loading

- [`support/src/Concerns/CanBeLazyLoaded.php`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/src/Concerns/CanBeLazyLoaded.php): `protected static ?bool $isLazyLoaded = null` (L12), deprecated `protected static bool $isLazy = true` (L17), and `isLazyLoaded()` returns `static::$isLazyLoaded ?? static::isLazy()` (L22–25). **Every widget is lazy by default.**
- `getDefaultProperties()` turns that into the Livewire-reserved `lazy => true` param ([`HandleComponents.php` L125–143](https://github.com/livewire/livewire/blob/v4.4.10/src/Mechanisms/HandleComponents/HandleComponents.php#L125-L143)). Livewire then skips mount and renders the placeholder with `x-intersect="$wire.__lazyLoad(...)"`, so the widget loads when it scrolls into view ([`SupportLazyLoading.php` L98–104, L192](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportLazyLoading/SupportLazyLoading.php#L192)).
- Placeholder: override `getLoadingPlaceholder()` to replace the inner content, set `$placeholderHeight` to change the height (default `8rem`), or override `placeholder()` to replace everything. In the last case your view must handle the grid position itself ([docs: widgets overview, "Customizing the loading placeholder"](https://filamentphp.com/docs/5.x/widgets/overview#customizing-the-loading-placeholder)).
- To disable it, set `protected static ?bool $isLazyLoaded = false` ([docs](https://filamentphp.com/docs/5.x/widgets/stats-overview#disabling-lazy-loading)), or per instance with `Widget::make(['lazy' => false])`. **Version note:** `$isLazyLoaded` and `CanBeLazyLoaded` first ship in **v5.12.0**; v5.0–v5.11 have `CanBeLazy` with `$isLazy`. `$isLazy` still works in v5.12 as a deprecated fallback.

### 1.7 Height

- There is no widget height or row-span API. `gridColumn()` handles span, start and order only. A search for row-span across `support`, `schemas` and `widgets` finds nothing.
- `$placeholderHeight` only sizes the lazy placeholder (`loading-section.blade.php` L20, `height: {$height ?? '8rem'}`). `ChartWidget::$maxHeight` caps the chart canvas (`ChartWidget.php` L38, L107).
- Pulse's `rows` prop (row-span) has no Filament equivalent **(inferred: it would need custom CSS)**.

### 1.8 Authorization (summary)

Two layers, per the [docs](https://filamentphp.com/docs/5.x/widgets/overview#conditionally-hiding-widgets) and source:

- The page filters out denied widgets with `::canView()` before mounting them (`Page.php` L629).
- `CanAuthorizeAccess` returns a 403 on mount, on the lazy load (via the `instanceof Widget` hydrate listener) and on every later update.

The docs warn that `boot()` and `placeholder()` can run before the check. **Version note:** `CanAuthorizeAccess` first ships in **v5.7.0**. Before that, only the page-level filter existed.

---

## 2. Dashboard grid and page filters

### 2.1 Grid

- `Dashboard::getColumns(): int | array` returns `2` ([`Dashboard.php` L72–75](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Pages/Dashboard.php#L72-L75)). An int becomes `['lg' => n]` ([`schemas/src/Concerns/HasColumns.php` L26–30](https://github.com/filamentphp/filament/blob/v5.12.0/packages/schemas/src/Concerns/HasColumns.php#L26-L30)), and the `grid()` macro defaults `default` to 1 ([`SupportServiceProvider.php` L227–256](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/src/SupportServiceProvider.php#L227-L256)). So the default dashboard is 1 column on mobile and 2 at `lg` (`--breakpoint-lg: 64rem` in the default theme).
- Responsive: `getColumns()` may return `['md' => 4, 'xl' => 5]`. Widgets set `$columnSpan` to 1–12, `'full'`, or `['md' => 2, 'xl' => 3]` ([docs](https://filamentphp.com/docs/5.x/widgets/overview#customizing-the-widgets-grid)). Container-query breakpoints (`@md` etc.) are supported by the same macros.
- Other pages: `getHeaderWidgets()` / `getFooterWidgets()` with `getHeaderWidgetsColumns()` / `getFooterWidgetsColumns()` (default 2) ([`Page.php` L477–552, L646–669](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Pages/Page.php#L646-L669); [docs: custom pages, "Adding widgets to pages"](https://filamentphp.com/docs/5.x/navigation/custom-pages#adding-widgets-to-pages)). A page can pass data to all its widgets with `getWidgetData()`, and per widget with `Widget::make([...])`.
- Pulse uses a 12-column grid (`<x-pulse>` `@props(['cols' => 12])`) and defaults cards to 6 columns (`card.blade.php` `@props(['cols' => 6, 'rows' => 1])`); see #4. A Pulse page can match that by returning `12` from `getColumns()`.

### 2.2 How page filters reach widgets

1. **State on the page.** [`Pages/Dashboard/Concerns/HasFilters.php`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Pages/Dashboard/Concerns/HasFilters.php) holds `#[Url] public ?array $filters = null` (L16–17) and `$persistsFiltersInSession = true` (L19). `mountHasFilters()` (L22–54) restores the filters from the session when the URL has none, normalizes `'true'`/`'false'`/`'null'` strings, and fills the filters form. `updatedFilters()` (L85–93) writes them back. The session key is `filament.dashboard.{sha256(class[+tenant])}.filters` (L96–107), so filters are kept per page class and per tenant.
2. **Two ways to edit it.**
   - `HasFiltersForm` ([source](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Pages/Dashboard/Concerns/HasFiltersForm.php)) builds a `live()` schema with `statePath('filters')` (L31–41). You fill it in `filtersForm(Schema $schema)`. `Dashboard::content()` puts `EmbeddedSchema::make('filtersForm')` above the widget grid when `getFiltersForm()` exists (`Dashboard.php` L84–97). The filters update as the user types.
   - `HasFiltersAction` ([source](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Pages/Dashboard/Concerns/HasFiltersAction.php)) is just `use HasFilters`. You add `FilterAction::make()->schema([...])` to `getHeaderActions()`. It is a slide-over that fills from `$livewire->filters` and assigns `$livewire->filters = $data` on Apply ([`FilterAction.php` L34–50](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Pages/Dashboard/Actions/FilterAction.php#L34-L50)). The data is validated, and widgets don't reload until Apply.
3. **Handed to widgets.** `getWidgetsSchemaComponents()` adds `'pageFilters' => $this->filters` whenever `property_exists($this, 'filters')` (`Page.php` L639).
4. **Received by widgets.** [`panels/src/Widgets/Concerns/InteractsWithPageFilters.php`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Widgets/Concerns/InteractsWithPageFilters.php) declares `#[Reactive] public ?array $pageFilters = null` (L13–14), plus a `__get('filters')` alias kept for backward compatibility (L17–25). Because the prop is `#[Reactive]`, Livewire re-renders the child whenever the parent's `filters` change. The docs warn that `HasFiltersForm` data is **not validated** ([docs: "Filtering widget data"](https://filamentphp.com/docs/5.x/widgets/overview#filtering-widget-data)).

The trait lives under `Filament\Widgets\Concerns`, but it is a plain Livewire concern, so a non-`Widget` Livewire component can `use` it too **(inferred; it only needs `parent::__get`, which `Livewire\Component` has)**.

### 2.3 Consequences for a Pulse page

- Steps 3 and 4 work on **any** `Filament\Pages\Page` that has a `filters` property, not just on `Dashboard`. But only `Dashboard::content()` renders the filters form automatically. A non-dashboard Pulse page would use `HasFiltersAction` (no form to render) or render `filtersForm` in its own `content()`.
- The documented way to get a second dashboard is to extend `Filament\Pages\Dashboard`, set `protected static string $routePath = 'pulse'`, and override `getWidgets()` / `getColumns()` ([docs: "Creating multiple dashboards"](https://filamentphp.com/docs/5.x/widgets/overview#creating-multiple-dashboards)). The default `getWidgets()` returns the panel-wide `Filament::getWidgets()`, so a Pulse page must override it, or the panel's dashboard widgets show up there too.
- Pulse's own period is a `#[Url] $period` read at mount (see #4). It is a different mechanism from `pageFilters`, so mapping one to the other is a design decision for later tickets.

---

## 3. The plugin contract

### 3.1 Interface and lifecycle

[`panels/src/Contracts/Plugin.php`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Contracts/Plugin.php) L7–14:

```php
interface Plugin
{
    public function getId(): string;
    public function register(Panel $panel): void;
    public function boot(Panel $panel): void;
}
```

- **`register()`** runs immediately when the user calls `$panel->plugin($plugin)` ([`Panel/Concerns/HasPlugins.php` L15–21](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Panel/Concerns/HasPlugins.php#L15-L21)), inside the user's `panel()` closure. That closure runs when `PanelRegistry` resolves ([`Facades/Filament.php` L199–205](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Facades/Filament.php#L199-L205)), and it does so for **every** panel on every request. Configuration set on the plugin before it is passed to `->plugin()` is therefore visible in `register()`.
- **`boot()`** runs from `Panel::boot()` (L108–110), after assets, colors, icons and render hooks are registered. `Panel::boot()` runs only for the **current** panel, via the `SetUpPanel` route middleware calling `Filament::bootCurrentPanel()` ([`Panel.php` L87–115](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Panel.php#L87-L115); [`Http/Middleware/SetUpPanel.php` L21](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Http/Middleware/SetUpPanel.php#L21)). The docs say the same: "`boot()` is run only when the panel … is actually in-use. It is executed by a middleware class."
- Lookup: `$panel->getPlugin($id)` throws if the plugin is missing (L43–46). `filament($id)` returns the plugin for the current (or default) panel ([`panels/src/global_helpers.php` L10–20](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/global_helpers.php#L10-L20); `FilamentManager::getPlugin()` L408–411). The docs suggest adding `make()` (`app(static::class)`) and `get()` (`filament(app(static::class)->getId())`) helpers ([docs: plugin development](https://filamentphp.com/docs/5.x/plugins/panel-plugins)).
- The docs use setter/getter pairs on the plugin object for per-panel options. Since each panel gets its own plugin instance, options are per panel.

### 3.2 Registering pages and widgets per panel

From inside `register(Panel $panel)`:

- `$panel->pages([PulsePage::class])` ([`HasComponents.php` L128+](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Panel/Concerns/HasComponents.php#L128)). It also accepts `PageConfiguration` objects, which register one page class several times under different keys and slugs (since **v5.3.0**; [docs: configurable resources and pages](https://filamentphp.com/docs/5.x/plugins/configurable-resources-and-pages)).
- `$panel->widgets([...])` (L228–244) adds the widgets to the panel's **default dashboard** and registers them with Livewire.
- `$panel->livewireComponents([...])` (L562–573) registers Livewire components that are not widgets or pages. This is relevant if a Pulse widget nests Pulse cards.
- `$panel->assets([...], package)` registers assets that load only on this panel (§4.2).
- All of these are no-ops when `php artisan filament:cache-components` has cached the panel (`hasCachedComponents()` early return, L230). The registrations end up in the cache instead **(inferred from the guard; caching itself not traced)**.
- Every class registered through `pages()` / `widgets()` / `livewireComponents()` is queued for `Livewire::component($name, $class)` under its Livewire Finder name (L575–658, L692–697). Widgets that appear only in a page's `getHeaderWidgets()` are not queued this way.

---

## 4. Assets

### 4.1 `FilamentAsset` and the asset classes

- `FilamentAsset::register(array $assets, string $package = 'app')` ([`support/src/Facades/FilamentAsset.php` L43](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/src/Facades/FilamentAsset.php#L43)) feeds `AssetManager::register()`, which sorts assets by type: `Theme`, `AlpineComponent`, `Css`, `Font`, `Js` ([`AssetManager.php` L62–76](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/src/Assets/AssetManager.php#L62-L76)). Other helpers: `registerCssVariables()` and `registerScriptData()` (the latter exposed as `window.filamentData`).
- Asset classes ([`support/src/Assets/`](https://github.com/filamentphp/filament/tree/v5.12.0/packages/support/src/Assets)): `Css`, `Js` (with `->module()`, `->async()`, `->defer()`, `->extraAttributes()`, and a Vite CSP nonce added between v5.0.0 and v5.12.0), `AlpineComponent`, `Font`, and `Theme` (`class Theme extends Css {}`). Every asset is `Asset::make($id, $path)` with `->loadedOnRequest()` (`Asset.php` L26–36). Remote URLs are not copied.
- `php artisan filament:assets` copies local files into `public/` ([`support/src/Commands/AssetsCommand.php` L25–67](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/src/Commands/AssetsCommand.php#L25-L67)). CSS goes to `{config('filament.assets_path')}/css/{package}/{id}.css` (`Css.php` L55–64), cache-busted with `?v={installed package version}` (`Asset.php` L65–85). The docs say to pass the Composer package name as `package` so files from different plugins don't clash ([docs: assets](https://filamentphp.com/docs/5.x/advanced/assets#registering-assets-for-a-plugin)).
- For JavaScript that needs bundling, the docs say plugins should prebuild an ES module and register it as a published `Js` asset, not depend on the host app's Vite manifest ([docs: custom widgets, "Building reusable plugin widgets"](https://filamentphp.com/docs/5.x/widgets/custom-widgets#building-reusable-plugin-widgets)).

### 4.2 Where and when they load

- The panel layout prints `@filamentStyles` (all registered `Css` and `Font` assets that are not `loadedOnRequest`, plus CSS variables), **then** the panel theme `<link>`. Scripts come at the end of `<body>` via `@filamentScripts(withCore: true)` ([`panels/resources/views/components/layout/base.blade.php` L73–75, L153](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/resources/views/components/layout/base.blade.php#L73-L75); [`support/resources/views/assets.blade.php` L15–19](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/views/assets.blade.php#L15-L19)).
- **Global vs per panel.** `FilamentAsset::register()` in a service provider loads on every Filament page. `$panel->assets([...])` stores assets on the panel and registers them only in `Panel::boot()` (current panel) or, in console, in `Panel::register()`, so that `filament:assets` sees them ([`Panel/Concerns/HasAssets.php` L18–33](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Panel/Concerns/HasAssets.php#L18-L33); [`Panel.php` L72–91](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Panel.php#L72-L91)). The docs recommend `packageBooted()` for assets shared by standalone components, and `$panel->assets()` inside the plugin's `register()` for assets every page of one panel needs ([docs: plugins getting started, "Registering assets"](https://filamentphp.com/docs/5.x/plugins/getting-started#registering-assets)).
- **Only where used.** Register with `->loadedOnRequest()` and put `x-load-css="[@js(FilamentAsset::getStyleHref('id', package: 'vendor/name'))]"` (or `x-load-js` / `getScriptSrc`) on the element that needs it ([docs: lazy loading CSS](https://filamentphp.com/docs/5.x/advanced/assets#lazy-loading-css)). This is the natural fit for CSS that only Pulse widgets need.
- **The theme.** A panel has exactly **one** theme: `->theme()` / `->viteTheme()` replace it, with the default being the `app` theme from `panels/dist/theme.css` ([`Panel/Concerns/HasTheme.php` L27–76](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Panel/Concerns/HasTheme.php#L27-L76); [`FilamentServiceProvider.php` L149](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/FilamentServiceProvider.php#L149)). A plugin must not call `$panel->theme()`, because that would overwrite the user's own theme **(inferred from the setter)**.

### 4.3 Are Tailwind classes in a plugin's Blade views available without a custom theme?

**No.**

- The default theme source is `@import 'tailwindcss' source(none); @import './index.css';` ([`panels/resources/css/theme.css`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/resources/css/theme.css)). `source(none)` turns off class scanning, so the build contains only what Filament's own CSS `@apply`s.
- The compiled [`panels/dist/theme.css`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/dist/theme.css) (header: `tailwindcss v4.3.0`; v5.0.0 shipped `v4.1.18`) contains no `.flex{`, `.grid{`, `.p-4{`, `.text-sm{`, `.hidden{`, `.gap-6{`, `.rounded-xl{`, `.bg-white{`, `.w-full{`, `.sr-only{` or `.col-span-*` rules. Its `@layer utilities` block holds only `fi-*` utilities (`.fi-color-*`, `.fi-bg-color-*`, `.fi-text-color-*`, …). I checked by string search.
- Docs, [styling overview](https://filamentphp.com/docs/5.x/styling/overview#using-tailwind-css-classes-in-your-blade-views-or-php-files): "**A custom theme is required to use Tailwind CSS classes in your own code.** Filament's default compiled stylesheet does not include arbitrary Tailwind classes - it only contains the styles needed for Filament's own UI components." … "Without a custom theme, any Tailwind classes you add to your code will simply not work."
- Upgrade guide, [new requirements](https://filamentphp.com/docs/5.x/upgrade-guide#new-requirements): "Tailwind CSS v4.1+ when compiling custom theme or standalone CSS. Panels using Filament's precompiled CSS do not require a local Tailwind CSS compiler." The upgrade guide has nothing else plugin- or theme-specific. It defers to Livewire's v4 upgrade guide.

**The documented recommendation for plugin authors** ([assets, "Using Tailwind CSS in plugins"](https://filamentphp.com/docs/5.x/advanced/assets#using-tailwind-css-in-plugins)):

> "…if you are a plugin developer, you probably should not be building your Tailwind CSS files into your plugin. Instead, you should provide the raw CSS files and instruct the user that they should build the Tailwind CSS file themselves. To do this, they need to add your vendor directory to their custom theme's CSS file using the `@source` directive."
>
> "If your plugin must also work with Filament's default stylesheet, register a compiled stylesheet containing the styles it needs. The default stylesheet does not include every Tailwind utility used by third-party plugins, and users without a custom theme are not compiling those utilities themselves."

The v5.0.0 wording was stronger: "This is one of the few situations where I would recommend compiling and registering a Tailwind CSS-compiled stylesheet in your plugin." The panel-plugin tutorial says "Filament recommends that users style their plugins with a custom filament theme" and removes the skeleton's CSS ([building a panel plugin](https://filamentphp.com/docs/5.x/plugins/building-a-panel-plugin)). The standalone-plugin tutorial instead ships a small plain-CSS file (built with postcss-nesting, not Tailwind) that uses Filament's CSS variables (`var(--gray-600)`), loaded with `loadedOnRequest()` + `x-load-css` ([building a standalone plugin](https://filamentphp.com/docs/5.x/plugins/building-a-standalone-plugin)).

So for a plugin that must work out of the box, the documented options are:

- **(a)** a compiled stylesheet registered through `FilamentAsset`;
- **(b)** plain CSS built on Filament's CSS variables and `fi-*` hooks;
- **(c)** asking users to `@source` the vendor views into a custom theme. This one is opt-in.

### 4.4 Cascade layers: why Pulse's own CSS can't just be dropped in

- Filament's CSS is entirely inside cascade layers. `panels/resources/css/index.css` imports each part with `layer(base)`, `layer(components)` and `layer(utilities)`. The compiled `dist/theme.css` has only `@layer theme`, `base`, `components`, `utilities` and `properties` blocks, plus `@property` rules, at its top level (checked by parsing the top-level blocks).
- Pulse's [`dist/pulse.css`](https://github.com/laravel/pulse/blob/v1.11.0/dist/pulse.css) (65 KB, Tailwind 3) has **zero** `@layer` blocks. It includes preflight (`button,input,optgroup,select,textarea{…;color:inherit;margin:0;padding:0}`, `h1…h6{font-size:inherit…}`), `@tailwindcss/forms` resets (`[type=text]…`, `select{background-image…}`) and unscoped utilities (`.hidden`, `.flex`, …). Its config uses `darkMode: 'class'` and a custom `default:` variant ([`tailwind.config.js`](https://github.com/laravel/pulse/blob/v1.11.0/tailwind.config.js)).
- Under CSS Cascade Level 5, normal declarations **outside** any layer beat declarations inside layers, whatever their specificity. Loading `pulse.css` on a panel page would therefore override Filament's layered `.fi-btn`, `.fi-input` and other component styles wherever Pulse's resets match (`button`, `input`, `select`, headings). **(Inferred from the spec plus the two files. Not tested in a browser.)** A side note: Pulse's `.dark` class strategy happens to match Filament's `.dark` class.

---

## 5. Things that changed inside v5 (affect the minimum version we require)

| Since | Change | Evidence |
|---|---|---|
| v5.0.0 | Widgets rendered as schema `Livewire` components with `liberatedFromContainerGrid()`; `pageFilters` param; `InteractsWithPageFilters` reactive prop | `git show v5.0.0:packages/panels/src/Pages/Page.php` L374–392 is identical in substance |
| v5.3.0 | `PageConfiguration`, configurable pages (register one page class several times) | `packages/panels/src/Pages/PageConfiguration.php` absent at v5.2.0, present at v5.3.0 |
| v5.7.0 | `Widgets\Concerns\CanAuthorizeAccess` (403 on mount, lazy load and update) and the `instanceof Widget` hydrate listener | file absent at v5.6.0, present at v5.7.0 |
| v5.12.0 | `CanBeLazy` becomes `CanBeLazyLoaded`; `$isLazyLoaded` and `isLazyLoaded()` added (old names deprecated, still honoured); `getLoadingPlaceholder()` added. `$placeholderHeight` and the schemas `Livewire::lazyLoaded()` already existed at v5.11.0 | `CanBeLazyLoaded.php` absent at v5.11.0, present at v5.12.0; v5.11.0 `CanBeLazy.php` has `$placeholderHeight` but no `getLoadingPlaceholder` |
| v5.x | Dashboard filter session key namespaced per tenant (`filament.dashboard.{sha256}.filters`) | `git diff v5.0.0 v5.12.0 -- …/HasFilters.php` |

Other v5 additions that could matter later: JavaScript-rendered widgets (`HasJsRenderer`, `make:filament-widget --react|--vue|--svelte|--js`), which "handle the widget's grid placement and lazy loading" and sync dashboard filters ([docs: custom widgets](https://filamentphp.com/docs/5.x/widgets/custom-widgets)).

---

## 6. Not confirmed

- I didn't run anything. The non-`Widget` route (§1.4), trait-method precedence over Pulse's `placeholder()`, and the cascade-layer clash (§4.4) are read from source and spec, not exercised.
- I didn't trace how `filament:cache-components` stores plugin-registered widgets and pages.
- I didn't check whether Livewire's `#[Reactive]` prop works when the child is a non-`Widget` `Card` subclass. Nothing in the source suggests otherwise.
- I didn't look at Filament's `tests/` for coverage of non-`Widget` classes on dashboards. I found no runtime type checks; there may be static-analysis or test-level assumptions.
