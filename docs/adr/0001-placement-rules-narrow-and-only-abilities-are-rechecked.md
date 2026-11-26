# Placement rules only narrow access, and only gate abilities are re-checked

A Pulse widget's access rules are checked on its first render and again on every request its Pulse card makes (lazy load, polls, sort changes). Filament decides whether a widget shows by calling the static `canView()` on its class, and the card's later requests carry only that class, so rules that must be re-checked live on the class: the plugin's `->authorize()` (panel-wide, or per class with `widgets:`) or a subclass's `canView()`. A placement's own rules can therefore only narrow what its class allows. `->visible()` / `->hidden()` take closures but run once, while the page renders: they are for layout, not security. `->authorize()` on a placement takes gate ability names only, because a string can be stored in the Pulse widget's locked state and re-checked on every request, while a closure cannot.

## Considered Options

- **Serializing placement closures** (for example with `laravel/serializable-closure`) into the Livewire snapshot or the session, so they could be re-run on the card's requests. Rejected: the snapshot would send the closure's source to the browser, and session storage would pile up stale entries and capture whatever models the closure `use`s.
- **Re-running the page's widget list** on the card's requests to find the placement's closure. Rejected: widgets can come from a dashboard, a page's header or footer, or the panel, and rebuilding any of them outside the page's own request is fragile.
