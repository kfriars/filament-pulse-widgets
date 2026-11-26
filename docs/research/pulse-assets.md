# How Pulse's CSS and JS behave inside a Filament v5 panel

Research for [#3](https://github.com/kfriars/filament-pulse-widgets/issues/3). This is throwaway research and is not meant to be merged.

Terms: a **Pulse card** is a Livewire component from `laravel/pulse`. A **widget** is a Filament dashboard widget. A **Pulse widget** is this package's widget that renders one Pulse card.

## Sources read

| Source | Version | Notes |
|---|---|---|
| [laravel/pulse](https://github.com/laravel/pulse/tree/v1.11.0) | tag `v1.11.0` (`e84f5b3`) | The `1.x` branch is one commit ahead, and that commit only touches `CHANGELOG.md` (checked with `gh api repos/laravel/pulse/compare/v1.11.0...1.x`) |
| [filamentphp/filament](https://github.com/filamentphp/filament/tree/v5.12.0) | tag `v5.12.0` | Latest v5 release at the time of writing |
| [tailwindlabs/tailwindcss](https://github.com/tailwindlabs/tailwindcss/tree/v4.3.5) | tag `v4.3.5` | Used to check behaviour that the upgrade guide describes differently |
| [Tailwind v3→v4 upgrade guide](https://tailwindcss.com/docs/upgrade-guide) | source: [`src/docs/upgrade-guide.mdx`](https://github.com/tailwindlabs/tailwindcss.com/blob/main/src/docs/upgrade-guide.mdx) on `main` | |
| [livewire/livewire](https://github.com/livewire/livewire/tree/v4.4.10) | tag `v4.4.10` | `dist/livewire.js` and the lazy-loading and lifecycle features |
| [Laravel Pulse docs](https://laravel.com/docs/12.x/pulse#custom-card-styling) | [`laravel/docs` `12.x` `pulse.md`](https://github.com/laravel/docs/blob/12.x/pulse.md) | |
| [MDN `@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/@layer) | | Cascade-layer precedence |

Everything was read from source. Nothing was built or run, and nothing was checked in a browser. The claims about what would happen in a browser are worked out from the CSS cascade rules and are marked as such.

## TL;DR

1. **`dist/pulse.css` is unsafe on a Filament v5 page.** It is a full Tailwind 3.4 build: preflight, the `@tailwindcss/forms` base styles, a `*` rule that resets every `--tw-*` variable, and unscoped utilities, all outside any cascade layer. Filament v5's CSS sits entirely inside cascade layers, and unlayered normal declarations beat every layered one regardless of specificity. So loading this file would make Filament's coloured buttons transparent, give Filament inputs white backgrounds and grey borders, remove Filament's rings and shadows, break translate/scale/blur, and swap the panel font to Figtree.
2. **If the panel theme compiles Pulse's views with `@source`, most of it works, but not all.** These break:
   - the Servers card's `grid-cols-[a,b,…]`, because v4 no longer turns commas into spaces;
   - Pulse's custom `default:` and `scrollbar*:` variants;
   - the safelisted dynamic grid classes, because v4 has no regex safelist;
   - the `@tailwindcss/forms` `<select>` styling;
   - the hand-written CSS in `resources/css/pulse.css`.

   Some things change slightly: `shadow-sm`, `focus-within:ring` and the `space-y-*` selector.
3. **Dark mode matches.** Pulse uses the class strategy (`.dark` on `<html>`), and so does Filament. Pulse's own theme switcher only exists in the Pulse dashboard layout, not in any Pulse card.
4. **`dist/pulse.js` is Chart.js 4.4.1 plus a `formatDate` helper, and nothing else.** It sets `window.Chart` and `window.formatDate`. It contains no Alpine components and has no Livewire dependency, so it can be loaded without `livewire.js`. Two catches:
   - It is a non-IIFE bundle with top-level `const` declarations, so it must run exactly once per document, or be loaded as `type="module"`.
   - `Pulse::js()` must not be used on a Filament page: it inlines a second copy of `livewire.js`.
5. **`Card::css()` is collected in `Card::dehydrate()`, and only on non-Livewire requests.** It is emitted only where something echoes `Pulse::css()`, which Pulse does in its own layout `<head>`. That output always starts with the whole of `dist/pulse.css`, and there is no public API that returns only the CSS that cards registered.

---

## Q1. What does `dist/pulse.css` contain, and what would leak into Filament?

### Build

- Built by Vite from [`resources/css/pulse.css`](https://github.com/laravel/pulse/blob/v1.11.0/resources/css/pulse.css#L1-L20), which uses `@tailwind base; @tailwind components; @tailwind utilities;` plus a little hand-written CSS ([`vite.config.js`](https://github.com/laravel/pulse/blob/v1.11.0/vite.config.js)).
- Tailwind `^3.4.19` (3.4.19 in the lockfile), `@tailwindcss/forms` 0.5.7 and `@tailwindcss/container-queries` ([`package.json`](https://github.com/laravel/pulse/blob/v1.11.0/package.json#L7-L12)).
- [`tailwind.config.js`](https://github.com/laravel/pulse/blob/v1.11.0/tailwind.config.js):
  - `content` is Pulse's Blade views (L5).
  - A regex `safelist` covers `grid-cols-*`, `col-span-*`, `row-span-*` and `h-*` with breakpoint and `default:` variants (L6-L19).
  - `darkMode: 'class'` (L20).
  - The Figtree font (L23-L24) and `h-128` (L27).
  - Plugins (L31-L39): forms, container queries, and the custom variants `default` (`html :where(&)`), `scrollbar`, `scrollbar-track` and `scrollbar-thumb`.

### Contents

`dist/pulse.css` is 65,063 bytes on one minified line. Splitting it at top-level braces gives 531 rules:

| Rules | What they are | Global? |
|---|---|---|
| 1–41 | **Tailwind 3 preflight.** Covers `*, ::before, ::after` (box-sizing, `border-width:0`, `border-color:#e5e7eb`), `html, :host` (with `font-family: Figtree, ui-sans-serif, …`), `body`, `h1–h6`, `a`, `button, input, select, textarea`, `button { background-color: transparent; background-image: none }`, `ol, ul, menu { list-style: none }`, `img, svg, video, canvas… { display: block }`, `[hidden]`, and so on. Rules 1–2 set about 50 `--tw-*` custom properties on `*, ::before, ::after` and `::backdrop`. Examples: `--tw-ring-shadow: 0 0 #0000`, `--tw-shadow: 0 0 #0000`, `--tw-ring-color: rgb(59 130 246 / .5)`, `--tw-translate-x: 0`, `--tw-scale-x: 1`, `--tw-blur: `, `--tw-backdrop-blur: `, `--tw-gradient-*`. | Yes, element and universal selectors |
| 42–66 | **`@tailwindcss/forms` base.** `[type=text], [type=email], …, textarea, select` get `appearance:none; background-color:#fff; border-color:#6b7280; border-width:1px; border-radius:0; padding:.5rem .75rem; font-size:1rem`, plus blue (`#2563eb`) focus rings. `select` gets a caret `background-image` and `padding-right:2.5rem`. There are also checkbox, radio and file styles. | Yes, attribute and element selectors |
| 67–409 | **Utilities.** Plain, unscoped class names: `.container`, `.hidden`, `.flex`, `.block`, `.grid`, `.table`, `.fixed`, `.border`, `.rounded`, `.shadow-sm`, `.ring-1`, `.text-*`, `.bg-*`, spacing, every safelisted `h-*`, `max-h-*`, `min-h-*`, `col-span-*`, `row-span-*`, `grid-cols-*`, and `@keyframes pulse` with `.animate-pulse`. One `!important` (`.\!p-0`). | Yes, wherever a page uses the same class name |
| 410–412 | Hand-written: `[x-cloak]{display:none}`, `@keyframes detect-scroll`, and `@supports selector(::-webkit-scrollbar){ .supports-scrollbars{…} }` | `[x-cloak]` is global |
| 413–531 | Variants: `after:`, `first:`/`last:`, `hover:`/`focus:`, `@container` queries (`@sm:`…`@6xl:`), `html :where(.default\:*)`, `::-webkit-scrollbar` variants, 37 `.dark\:*:is(.dark *)` rules, and `sm:` to `2xl:` media blocks (mostly the safelisted grid classes) | |

There are **no `@layer` blocks** (`grep -c @layer` gives 0). This is how Tailwind 3 works: it rewrote `@layer` at build time, and v4 is the release that moved to native cascade layers ([upgrade guide, "Adding custom utilities"](https://tailwindcss.com/docs/upgrade-guide#adding-custom-utilities): "In v4 we are using native cascade layers and no longer hijacking the `@layer` at-rule").

### Why this clashes with Filament v5

- **Filament v5's CSS is all in layers.**
  - Tailwind 4 declares `@layer theme, base, components, utilities;` and imports preflight as `layer(base)` ([`tailwindcss/index.css` L1-L5](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.5/packages/tailwindcss/index.css#L1-L5)).
  - Filament imports every component stylesheet with `layer(components)` and its base and utilities into `base` and `utilities` ([`support/resources/css/index.css` L1-L38](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/css/index.css#L1-L38), [`panels/resources/css/index.css` L1-L25](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/resources/css/index.css#L1-L25)).
- **The cascade rule.** MDN [`@layer`](https://developer.mozilla.org/en-US/docs/Web/CSS/@layer): "all normal declarations within CSS layers have a lower priority than declarations declared outside of a layer." Specificity and source order do not change this.

Applying that rule to Filament v5.12.0's own selectors gives the following. This is worked out from the cascade rules and has not been seen in a browser.

| Pulse rule (unlayered) | Filament rule (layered) | Result |
|---|---|---|
| `button { background-color: transparent }` | `.fi-btn.fi-color { @apply bg-(--bg) … }` in `components` ([`button.css` L99-L101](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/css/components/button.css#L99-L101)) | Coloured Filament buttons lose their background |
| `[type=text], …, select { background-color:#fff; border-width:1px; border-color:#6b7280; padding:.5rem .75rem; font-size:1rem }` | `.fi-input { … border-none bg-transparent px-3 py-1.5 text-sm … }` ([`input/index.css` L2](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/css/components/input/index.css#L2)) | Every Filament text input and select gets a white background, a grey border and different padding, including in dark mode |
| `*, ::before, ::after { --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-ring-color: … }` | `.fi-section … { @apply … shadow-sm ring-1 ring-gray-950/5 … }` ([`section.css` L22](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/css/components/section.css#L22)); `.fi-input-wrp { … shadow-sm ring-1 … }` ([`input/wrapper.css` L2](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/css/components/input/wrapper.css#L2)) | Rings and shadows set by Tailwind 4 utilities collapse to nothing. Tailwind 4 still uses these variable names (`'--tw-ring-shadow'`, `'--tw-shadow'`, `'--tw-translate-x'`, `'--tw-backdrop-blur'` and others all appear in [`src/utilities.ts`](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.5/packages/tailwindcss/src/utilities.ts)) |
| Same `*` rule: `--tw-translate-x: 0`, `--tw-scale-x: 1`, `--tw-blur: `, `--tw-backdrop-blur: `, `--tw-gradient-*` | Any Filament translate, scale, blur, backdrop-blur or gradient utility | Those utilities stop working: sidebar and modal transforms, the modal backdrop blur |
| `*, ::before, ::after { border-width: 0; border-color: #e5e7eb }` | `@apply border-t border-gray-200 … dark:border-white/10` in `components` ([`section.css` L18](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/css/components/section.css#L18), L38) | Borders set inside a layer are removed |
| `html, :host { font-family: Figtree, … }` | Tailwind 4 preflight `html { font-family: --theme(--default-font-family …) }` in `base` ([`preflight.css` L28-L33](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.5/packages/tailwindcss/preflight.css#L28-L33)), with `--font-sans: var(--font-family)` ([`panels/…/index.css` L29-L36](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/resources/css/index.css#L29-L36)). The default family is `Inter Variable` ([`HasFont.php` L63-L65](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/src/Panel/Concerns/HasFont.php#L63-L65)) | The whole panel switches to Figtree. Filament does not load Figtree, so the browser falls back to the system sans font |
| `.hidden`, `.flex`, `.block`, `.grid`, … | Only elements that carry those literal class names. Filament's own views mostly use `fi-*` classes with `@apply`, so they are not affected, but app views and other plugins that use raw utilities are | `hidden md:flex`-style patterns stop toggling, because the unlayered `.hidden` beats the layered `md:flex` |

**Layering does not fix it either.**
- Wrapping `dist/pulse.css` in a layer declared *after* Filament's layers makes it behave just like unlayered CSS.
- Wrapping it in a layer *before* `base` makes Tailwind 4's own preflight (`*, ::after, ::before { margin:0; padding:0; border:0 solid }`, [`preflight.css` L7-L16](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.5/packages/tailwindcss/preflight.css#L7-L16)) beat Pulse's spacing and border utilities inside the cards.

To be safe, the file would have to lose its preflight, forms and `*` rules and have its utilities scoped. That is reasoned from the rules, not tested.

---

## Q2. Which Tailwind 3 classes in Pulse's views change under Tailwind 4?

This applies if the panel's Tailwind 4 theme compiles `vendor/laravel/pulse/resources/views/**` via `@source`. Filament's theme starts from `@import 'tailwindcss' source(none)` ([`panels/resources/css/theme.css` L1](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/resources/css/theme.css#L1)), so Pulse's views must be added explicitly. Filament's docs tell plugin authors to have users do exactly that, or to ship a compiled stylesheet if the plugin must work with the default theme ([`docs/09-advanced/02-assets.md` L142-L158](https://github.com/filamentphp/filament/blob/v5.12.0/docs/09-advanced/02-assets.md)).

The class inventory comes from grepping `resources/views/**` and the compiled `dist/pulse.css`.

### Broken: they generate nothing, or the wrong CSS

| Class(es) | Where | Why | Effect |
|---|---|---|---|
| `grid-cols-[max-content,minmax(max-content,1fr),max-content,minmax(min-content,2fr),max-content,minmax(min-content,2fr),minmax(max-content,1fr)]` | [`livewire/servers.blade.php` L32](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/servers.blade.php#L32) | v3 turned commas into spaces in `grid-cols-[…]`; v4 does not ([upgrade guide, "Arbitrary values in grid and object-position utilities"](https://tailwindcss.com/docs/upgrade-guide#arbitrary-values-in-grid-and-object-position-utilities)). v3 output was `grid-template-columns:max-content minmax(max-content,1fr) …` | v4 emits a comma-separated `grid-template-columns`, which is invalid, so the **Servers card layout collapses** |
| `default:col-span-full`, `default:lg:col-span-{$cols}`, `default:row-span-{$rows}`, `default:grid-cols-{$cols}`, `default:gap-6`, `default:text-left` | [`components/card.blade.php` L3](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/card.blade.php#L3), [`servers.blade.php` L28](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/servers.blade.php#L28), [`servers-placeholder.blade.php` L5](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/servers-placeholder.blade.php#L5), [`th.blade.php` L1](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/th.blade.php#L1), [`pulse.blade.php` L48](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/pulse.blade.php#L48) | `default` is a Pulse plugin variant (`tailwind.config.js` L35) that Filament's theme does not define | Grid placement classes do nothing, which may be fine inside a widget. Table headers lose `text-left` and fall back to the browser's centred `<th>`. Fix: `@custom-variant default (html :where(&));` |
| `scrollbar:w-1.5`, `scrollbar:h-1.5`, `scrollbar:bg-transparent`, `scrollbar-track:*`, `scrollbar-thumb:*` (9 classes) | [`components/scroll.blade.php` L25](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/scroll.blade.php#L25) | Pulse plugin variants (`tailwind.config.js` L36-L38) | Card bodies show native scrollbars. Fix: three `@custom-variant` rules |
| Dynamic `{$cols}`, `{$rows}`, `h-\d+` classes | as above | Tailwind cannot see interpolated class names. Pulse covered them with a regex `safelist`, which v4 does not support ([upgrade guide, "Using a JavaScript config file"](https://tailwindcss.com/docs/upgrade-guide#using-a-javascript-config-file)) | They must be listed with `@source inline("…")`, which supports brace expansion and ranges ([Tailwind docs, safelisting with ranges](https://tailwindcss.com/docs/detecting-classes-in-source-files#safelisting-specific-utilities)) |
| `<select>` styling | [`components/select.blade.php` L10-L14](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/select.blade.php#L10-L14) | Relied on `@tailwindcss/forms` (appearance reset and caret image). Filament v5 does not use the forms plugin: its root `package.json` only has `tailwindcss ^4` | The sort and type selects in six Pulse cards (Usage, Exceptions, and the four Slow cards) render with native browser styling |
| `.supports-scrollbars`, `@keyframes detect-scroll`, `[x-cloak]` | [`resources/css/pulse.css` L5-L20](https://github.com/laravel/pulse/blob/v1.11.0/resources/css/pulse.css#L5-L20) | Hand-written CSS, not utilities, so `@source` cannot produce it | Must be copied into the theme or the package stylesheet. `[x-cloak]` is already covered by Filament's inline style ([`layout/base.blade.php` L49-L71](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/resources/views/components/layout/base.blade.php#L49-L71)) |

### Changed, but still working

| Class(es) | Where | Change |
|---|---|---|
| `shadow-sm` | `card.blade.php` L3, `queues.blade.php` L73, `servers.blade.php` L69/L93 | v4 `shadow-sm` is v3's `shadow`, so it is larger ([upgrade guide, "Renamed utilities"](https://tailwindcss.com/docs/upgrade-guide#renamed-utilities); [`theme.css` L407-L408](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.5/packages/tailwindcss/theme.css#L407-L408)). Side effect: the Pulse card's `shadow-sm ring-1` then matches Filament's own `fi-section` (`shadow-sm ring-1`, `section.css` L22) |
| `focus-within:ring` | `select.blade.php` L7 | Bare `ring` changed from 3px blue-500/50 to 1px `currentColor` ([upgrade guide, "Default ring width and color"](https://tailwindcss.com/docs/upgrade-guide#default-ring-width-and-color)) |
| `space-y-1`, `space-y-4` | `scroll.blade.php` L25, `placeholder.blade.php` L5 | The selector changed from `> :not([hidden]) ~ :not([hidden])` with `margin-top` to `> :not(:last-child)` with `margin-bottom` ([upgrade guide, "Space-between selector"](https://tailwindcss.com/docs/upgrade-guide#space-between-selector)). Negligible here |
| `hover:*` | many | Only applies under `@media (hover: hover)` ([upgrade guide, "Hover styles on mobile"](https://tailwindcss.com/docs/upgrade-guide#hover-styles-on-mobile)) |
| `gray-*` colours | everywhere | In Filament's theme, `--color-gray-*` maps to Filament's `--gray-*` palette ([`support/…/index.css` L65-L75](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/css/index.css#L65-L75)), so Pulse greys follow the panel's grey. `purple`, `blue`, `red`, `green`, `cyan` and `orange` use v4's oklch defaults, which shift slightly. Chart lines are hard-coded hex in JS and are not affected |
| `font-sans` | `pulse.blade.php` (layout only) | Resolves to the Filament font instead of Figtree |
| `[&>svg]:dark:stroke-gray-600` | card-header icons | Stacked variants now apply left to right ([upgrade guide, "Variant stacking order"](https://tailwindcss.com/docs/upgrade-guide#variant-stacking-order)). For this pair the result is equivalent (an svg with a `.dark` ancestor) |
| `[scrollbar-color:theme(colors.gray.500)_transparent]` | `scroll.blade.php` | Dot-notation `theme()` still resolves through the compatibility layer ([`compat/apply-compat-hooks.ts` L150-L160](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.5/packages/tailwindcss/src/compat/apply-compat-hooks.ts#L150-L160)), but the guide recommends CSS variables ([upgrade guide, "Using the theme() function"](https://tailwindcss.com/docs/upgrade-guide#using-the-theme-function)) |
| `!p-0` | | The leading `!` is deprecated but still supported ([upgrade guide, "The important modifier"](https://tailwindcss.com/docs/upgrade-guide#the-important-modifier)) |

### Unchanged, even though the upgrade guide suggests otherwise

- **`flex-shrink-0`, `flex-grow`, `flex-grow-[10000]`, `overflow-ellipsis`.** The guide lists these under "Removed deprecated utilities" ([guide](https://tailwindcss.com/docs/upgrade-guide#removed-deprecated-utilities)). v4.3.5 still registers all of them, unconditionally, in [`src/compat/legacy-utilities.ts` L61-L108](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.5/packages/tailwindcss/src/compat/legacy-utilities.ts#L61-L108), called from `apply-compat-hooks.ts` L150.
- **`bg-gradient-to-r`, `bg-gradient-to-t`.** Kept as legacy aliases ([`legacy-utilities.ts` L7-L22](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.5/packages/tailwindcss/src/compat/legacy-utilities.ts#L7-L22)). They now interpolate `in oklab`.
- **Bare `rounded`.** Still 0.25rem via the deprecated `--radius` ([`theme.css` L502-L509](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.5/packages/tailwindcss/theme.css#L502-L509)).
- **`@container`, `@container/scroll-wrapper`, `@sm:`/`@md:`/`@lg:`/`@3xl:`/`@6xl:`.** These are native in v4, with the same sizes as the v3 plugin (24, 28, 32, 48 and 72rem; [`theme.css` L336-L344](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.5/packages/tailwindcss/theme.css#L336-L344)).
- **`h-128`.** Pulse had to add it to its theme; v4's spacing multiplier produces 32rem natively.
- **Default border colour.** This changed to `currentColor` in v4 ([guide](https://tailwindcss.com/docs/upgrade-guide#default-border-color)), but every `border*` in Pulse's views has an explicit colour, so it has no effect.
- **The cursor on buttons.** v4 preflight uses `cursor: default`, but Filament adds `cursor: pointer` back ([`support/resources/css/base.css` L1-L4](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/resources/css/base.css#L1-L4)).

---

## Q3. How does Pulse do dark mode, and does it match Filament?

**Pulse** uses the **class strategy**: `darkMode: 'class'` ([`tailwind.config.js` L20](https://github.com/laravel/pulse/blob/v1.11.0/tailwind.config.js#L20)). Tailwind 3.4 compiles this to `.dark\:X:is(.dark *)`, and there are 37 such rules in `dist/pulse.css`. The `.dark` class is set by an inline script in [`components/theme-switcher.blade.php` L1-L13](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/theme-switcher.blade.php#L1-L13):
- it adds `dark` to `document.documentElement` when `localStorage.theme === 'dark'`, or when no theme is stored and `prefers-color-scheme: dark` matches;
- it listens for OS changes.

That switcher is rendered only in the Pulse dashboard layout ([`components/pulse.blade.php` L41](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/pulse.blade.php#L41)), **not inside any Pulse card**.

**Filament v5** also uses the **class strategy, with `.dark` on `<html>`**:
- `@variant dark (&:where(.dark, .dark *));` ([`panels/resources/css/index.css` L27](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/resources/css/index.css#L27)).
- An inline head script reads `localStorage['filament:theme:mode']` (light, dark or system) and adds `dark` to `document.documentElement`, and re-runs on `livewire:navigated` ([`layout/base.blade.php` L102-L130](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/resources/views/components/layout/base.blade.php#L102-L130)).
- Forced dark mode puts the class on `<html>` server-side (L20-L23).
- An Alpine effect keeps it in sync ([`panels/resources/js/dark-mode.js` L39-L45](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/resources/js/dark-mode.js#L39-L45)).
- `:root.dark { color-scheme: dark }` ([`panels/resources/css/base.css` L1-L3](https://github.com/filamentphp/filament/blob/v5.12.0/packages/panels/resources/css/base.css#L1-L3)).

**Verdict: the two match.** Both key off a `.dark` class on `<html>`. Pulse's `:is(.dark *)` needs `.dark` on an ancestor, and `<html>` is an ancestor of every card. So Pulse cards inside a Filament panel follow Filament's theme switcher, including when Filament disables or forces dark mode. Details:
- **Different storage keys.** Pulse uses `theme`, Filament uses `filament:theme:mode`, so there is no interference.
- **Specificity differs.** Pulse's compiled dark rules are (0,2,0); Filament's `:where()` version adds none. This only matters when overriding Pulse styles.
- **Charts are not theme-aware.** Chart.js colours are hard-coded hex values (for example `borderColor: '#9333ea'` in [`servers.blade.php` L136](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/servers.blade.php#L136)), and axes and grid lines are hidden, so nothing in them reacts to dark mode.

---

## Q4. What does `dist/pulse.js` contain, and can it be loaded without `livewire.js`?

**Contents.** The source is 27 lines ([`resources/js/pulse.js`](https://github.com/laravel/pulse/blob/v1.11.0/resources/js/pulse.js)):
- `import Chart from 'chart.js/auto'`;
- a `formatDate(value)` helper that parses `Y-m-d H:i:s` as UTC and calls `toLocaleString`;
- `window.Chart = Chart; window.formatDate = formatDate;`.

`dist/pulse.js` is 204,503 bytes. It bundles **Chart.js v4.4.1** (all controllers, elements, plugins and scales registered) and `@kurkle/color` 0.3.2, and ends with `window.Chart=gt;window.formatDate=Sh;`. It contains **no Alpine components** and no reference to `Livewire` or `Alpine`.

**Where the Alpine components are.** They live in the card views, inside Livewire `@script` blocks:
- `Alpine.data('cpuChart' | 'memoryChart' | 'storageChart', …)` in [`servers.blade.php` L123-L350](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/servers.blade.php#L123-L350);
- `Alpine.data('queueChart', …)` in [`queues.blade.php` L83-L209](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/queues.blade.php#L83-L209);
- inline `x-data` objects that call `Livewire.hook('commit', …)` ([`components/card.blade.php` L4-L15](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/card.blade.php#L4-L15));
- `Livewire.on('servers-chart-update' | 'queues-chart-update', …)`.

**`window.formatDate` is used by 9 of the 10 card views,** in `x-bind:title` on the card header (for example [`slow-requests.blade.php` L4](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/livewire/slow-requests.blade.php#L4)). So every built-in Pulse card needs `dist/pulse.js`, not only the chart cards.

**`Pulse::js()`** ([`src/Pulse.php` L522-L535](https://github.com/laravel/pulse/blob/v1.11.0/src/Pulse.php#L522-L535)) returns two inline `<script>` tags: the full contents of `vendor/livewire/livewire/dist/livewire.js`, then `dist/pulse.js`.
- On a Filament page, Livewire and Alpine are already running, so this would load a **second Livewire and a second Alpine**.
- Livewire 4's bundle warns "Detected multiple instances of Livewire running" / "…of Alpine running" and then **overwrites `window.Livewire` and `window.Alpine`** ([`dist/livewire.js` L17499-L17513](https://github.com/livewire/livewire/blob/v4.4.10/dist/livewire.js#L17499-L17513)).
- It would also add about 0.8 MB of inline JS to every response (`livewire.js` v4.4.10 is 610,095 bytes).

**→ Do not use `Pulse::js()` in a Filament panel.**

**Loading `dist/pulse.js` on its own works,** because it depends only on the DOM. Three caveats:
1. **It is not wrapped in an IIFE.** The file starts `var xo=…;var _o=…;var M=…;function ye(i){…}const xt=…` and ends `…const wh=[…];gt.register(...wh);const Sh=…;window.Chart=gt;…`. As a classic `<script>`, its top-level `var`s become `window` properties (`window.M` and others), and its top-level `const`s go into the shared global lexical scope. Running it twice in one document throws `SyntaxError: Identifier '…' has already been declared` (ECMAScript GlobalDeclarationInstantiation), and the second copy does not run. A clash with another classic script's top-level names would fail the same way. Filament's own bundles are IIFEs (`(()=>{…})()`), so they do not clash. Pulse avoids the problem only because its layout inlines the file once per full page load.
   - **→ Load it exactly once**, for example as a Filament `Js` asset (rendered once, with `data-navigate-once` in SPA mode: [`support/src/Assets/Js.php` L18, L131](https://github.com/filamentphp/filament/blob/v5.12.0/packages/support/src/Assets/Js.php)), **or load it as `type="module"`** (`Js::make(...)->module()`). Module scope removes the global leaks and double-execution, and the file only communicates through `window.*` anyway. *Not tested.*
2. **Timing.** `Chart` and `formatDate` must exist before Alpine evaluates the cards' `@script` and `x-bind` expressions. Livewire 4 auto-starts on `DOMContentLoaded` ([`dist/livewire.js` L17506-L17512](https://github.com/livewire/livewire/blob/v4.4.10/dist/livewire.js#L17506-L17512)). Classic scripts parsed before that point, and module scripts (which run before `DOMContentLoaded`), both satisfy this. *Not tested* for SPA navigation or for widgets added after page load.
3. **Globals.** `window.Chart` becomes Chart.js 4.4.1. Filament's own chart widgets bundle Chart.js 4.5.1 inside ES modules and never read or write `window.Chart` ([`widgets/dist/components/chart.js`](https://github.com/filamentphp/filament/blob/v5.12.0/packages/widgets/dist/components/chart.js)), so the two do not conflict. An app that sets its own `window.Chart` or `window.formatDate` would be overwritten.

---

## Q5. How do custom Pulse cards add CSS, and how is it collected and emitted?

**Registration.** `Card::css()` is a protected method returning `string|Htmlable|array|null`, `null` by default ([`src/Livewire/Card.php` L67-L75](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Card.php#L67-L75)). A string is a **file path**. `Card::dehydrate()` (L58-L65):

```php
if (Livewire::isLivewireRequest()) {
    return;
}

Pulse::css($this->css());
```

So the CSS is collected **only during a full-page (non-Livewire) render**. It is not collected during lazy-load, poll or action requests.

All built-in Pulse cards are `#[Lazy]` (for example [`Servers.php` L16](https://github.com/laravel/pulse/blob/v1.11.0/src/Livewire/Servers.php#L16)). For those, Livewire skips `mount` and render and returns the placeholder ([`SupportLazyLoading.php` L98-L106](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportLazyLoading/SupportLazyLoading.php#L98-L106)). The `dehydrate` hook has no skip guard ([`SupportLifecycleHooks.php` L153-L156](https://github.com/livewire/livewire/blob/v4.4.10/src/Features/SupportLifecycleHooks/SupportLifecycleHooks.php#L153-L156)), so registration should still happen during the initial placeholder render. *This comes from reading the code, not from running it.*

**Collection.** `Pulse` is a container singleton ([`PulseServiceProvider.php` L49](https://github.com/laravel/pulse/blob/v1.11.0/src/PulseServiceProvider.php#L49)). `Pulse::css($x)` with one argument merges `Arr::wrap($x)` into the protected `$css` list and deduplicates it with `array_unique(..., SORT_REGULAR)` ([`src/Pulse.php` L498-L504](https://github.com/laravel/pulse/blob/v1.11.0/src/Pulse.php#L498-L504)). A `null` argument does nothing. **The list always starts with `dist/pulse.css`** (`protected $css = [__DIR__.'/../dist/pulse.css']`, L88).

**Emission.** `Pulse::css()` with no arguments (L506-L517) reduces the list:
- an `Htmlable` is output raw via `toHtml()`;
- a path is read with `file_get_contents` and wrapped in `<style>…</style>`, and an unreadable path throws a `RuntimeException`.

The only caller is Pulse's layout, `{!! Pulse::css() !!}` in `<head>` ([`components/pulse.blade.php` L16](https://github.com/laravel/pulse/blob/v1.11.0/resources/views/components/pulse.blade.php#L16)). This works even though `<head>` comes before the cards in the markup, because Blade renders a component's slot before the component's own view: `startComponent()` opens an output buffer ([`ManagesComponents.php` L54-L56](https://github.com/laravel/framework/blob/12.x/src/Illuminate/View/Concerns/ManagesComponents.php#L54-L56)), and `renderComponent()` turns it into `$slot` (L117). So every card inside `<x-pulse>` has already dehydrated by the time the head runs.

**The documented options for custom cards** ([Pulse docs, "Styling"](https://laravel.com/docs/12.x/pulse#custom-card-styling), [`pulse.md` L602-L676](https://github.com/laravel/docs/blob/12.x/pulse.md#L602-L676)):
1. **`@vite('resources/css/pulse/….css')`** inside the `<x-pulse>` dashboard view, for cards that live in the app.
2. **`protected function css()` returning a file path**, for packaged cards. The file is inlined as `<style>`.
3. **Tailwind 4**, done the way the docs now show: no preflight, `@custom-variant dark (&:where(.dark, .dark *))`, and utilities scoped under an id with `#top-sellers { @import "tailwindcss/utilities.css" source(none); }`, with `id="top-sellers"` on `<x-pulse::card>`. Third-party cards may therefore ship CSS built with Tailwind 3 or Tailwind 4. *Whether that scoped import emits a nested `@layer utilities` was not verified.*

**What this means in Filament:**
- Nothing calls `Pulse::css()`, so a custom card's `css()` is silently ignored unless the package emits it, for example through a head render hook.
- Calling `Pulse::css()` also inlines the whole of `dist/pulse.css`, which Q1 shows is harmful.
- There is no public getter for the list. `$css` is protected, but `Laravel\Pulse\Pulse` is not `final` ([`src/Pulse.php` L29](https://github.com/laravel/pulse/blob/v1.11.0/src/Pulse.php#L29)), so a subclass bound in the container could expose it or drop the first entry.
- If a Pulse card is first instantiated inside a Livewire request (for example, a lazy Filament widget that only mounts the card in its lazy-load request), its `css()` is never collected.
- Emitting from a render hook only works if the hook renders after the Pulse cards have dehydrated. *I did not verify that Filament's full-page Livewire render finishes the page body before the layout's `<head>` hooks run.*

---

## Implications for other tickets

These are observations, not decisions.

- **Ruled out:**
  - Inlining `Pulse::css()` or `dist/pulse.css` unmodified on a Filament page.
  - Using `Pulse::js()` at all.
- **Viable styling routes:**
  - **(a)** Have users `@source` Pulse's views in their panel theme, and ship a small compatibility CSS file: `@custom-variant default` and the `scrollbar*` variants, `@source inline()` for the dynamic classes, a hand-written rule for the Servers card's `grid-cols-[…]` class, and the `.supports-scrollbars` CSS.
  - **(b)** Ship a package-compiled Tailwind 4 stylesheet of Pulse's classes, with no preflight and scoped or layered, so it also works with Filament's default theme. This is the route Filament's plugin docs recommend for plugins that must work without a custom theme.
- **`dist/pulse.js`** has to be loaded once per document, before Livewire starts, by the package itself (for example a `FilamentAsset` `Js`, possibly `->module()`), even on panels whose Pulse widgets contain no charts, because of `formatDate`.
- **Custom-card CSS** needs its own path: collect it without the default `dist/pulse.css` entry, and make sure it is collected on the full-page request.

## Not confirmed

- Browser behaviour of every Q1 clash. It follows from the cascade rules but has not been seen in a browser.
- That `Card::dehydrate()` runs for `#[Lazy]` cards on the initial render. This is from reading Livewire 4.4.10, not from running it.
- That `type="module"` loading of `dist/pulse.js` works with Livewire 4 start-up and Filament SPA mode.
- The order of Filament's `<head>` render hooks relative to widget rendering.
- What Tailwind 4 emits for `#id { @import "tailwindcss/utilities.css" }`.
