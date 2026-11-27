# PROTOTYPE: README variants (throwaway)

For [What does the README say, and with which screenshots?](https://github.com/kfriars/filament-pulse-widgets/issues/29). This branch is never merged.

Three README drafts that disagree about structure. All cover the same content; react to the shape, and steal bits across them.

| Variant | Shape | Screenshots |
| --- | --- | --- |
| [A: the guide](A-guide.md) | Linear: install, place, secure, customise, policy. Option tables inside prose sections. | One hero that follows the reader's light/dark mode (`<picture>`), plus a period selector close-up. |
| [B: recipes](B-recipes.md) | Short top with the two warnings up front, then one heading per task ("Only admins should see Pulse widgets"). | Light and dark side by side at the top, plus the period selector. |
| [C: reference card](C-reference.md) | One example, then tables: widgets, options, access, translation keys. Almost no prose. | One tall shot of every Pulse widget, light only, at the bottom. |

Screenshots are scripted with Playwright against the workbench (`screenshots.mjs`, about 15 seconds for six shots at 2x):

```bash
composer build && php vendor/bin/testbench serve --port=8765 &
node prototype-readme/screenshots.mjs
```

What the raw shots show (see `screenshots/`):
- the workbench's app name is "Laravel" and its user "Workbench Admin", with Faker names like "Dr. Dane Cruickshank";
- the Activity custom card is below the fold of the hero shot, so the hero shows only first-party Pulse cards;
- 2x at 1440x900 gives 2880x1800 PNGs of about 400 KB each.
