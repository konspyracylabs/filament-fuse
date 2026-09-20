# Contributing

Thanks for looking. This file covers how the package is built and tested, and what a
pull request needs before it can be merged.

## Setting up

```bash
git clone https://github.com/konspyracylabs/filament-fuse.git
cd filament-fuse
composer install
composer test
```

The suite needs no database and no services.

## The one command

```bash
composer finalize
```

Runs Rector, Pint, PHPStan and Pest, in that order. The order matters — Rector expands
class references that Pint then imports, so Pint first leaves the tree dirty.

Individually: `composer refactor`, `composer lint`, `composer analyse`, `composer test`.

## How the panel is put together

**Pages are Livewire; components are class-based Blade.** The two pages
(`Circuits`, `CircuitDetail`) hold state. The components under
`src/View/Components/` do not — each resolves its strings, classes and icons in PHP and
exposes them as public properties, and the template beside it in
`resources/views/components/` is markup only.

The rules that follow from that:

- No `@php` blocks, no inline `style=` attributes, no value-computing conditionals in a
  template. Layout goes in `resources/dist/filament-fuse.css` (the "Layout" block at the
  bottom; the design tokens above it ship verbatim from the design handoff). Decisions
  go in the component class.
- No `trans_choice()` and no parameterised `__()` in a template. Plain `__('key')` for a
  static label is fine.
- Every visible string is a key in `resources/lang/en/filament-fuse.php`.
- Nothing reads `config('filament-fuse.slug|navigation|dashboard.*')` directly. Those
  settings are per-panel, resolved through `FilamentFusePlugin::resolve()`, which falls
  back to config when no plugin is registered.

Three framework traps that fail silently and have cost real time here:

1. Blade cannot parse an `@if` inside a component tag's attribute list — the whole
   component renders as nothing. Pass a prop and branch inside the component's template.
2. Livewire overwrites a `getViewData()` key with a public property of the same name.
   Never name view data after a property.
3. A render hook must render the component (`Blade::render('<x-filament-fuse::indicator />')`),
   not its view, or the class never runs.

## Tests

| File | Question it answers |
|---|---|
| `ComponentsTest` | Does each component say the right thing? |
| `ComponentSnapshotsTest` | Has the markup changed without anyone noticing? |
| `PagesTest` | Do the pages behave — access, the one-circuit rule, the 404 guard? |
| `PluginSettingsTest` | Does every setting resolve plugin-first, config-second? |
| `CircuitInspectorTest`, `UnreachableCacheTest` | Is the data underneath correct, and cheap, even when the cache is down? |
| `OneCircuitTest` | Does the package pick the right circuit, and only one? |
| `InstallCommandTest` | Does the install command ask, and write to `.env` safely? |
| `DrillTest` | Does a drill open and always close the testing circuit? |
| `DependencyContractTest` | Has Fuse changed the surface this plugin reads? |

### Snapshots

```bash
composer test:snapshots
```

regenerates `tests/.pest/snapshots/`. **Read the diff before committing.** A snapshot
updated without being read is worse than no snapshot. The snapshots are built in memory
with a frozen clock and explicit ids, so a change in them means the markup changed.

### The Fuse pin

`harris21/laravel-fuse` is pinned to one minor on purpose. To widen or move it, run the
contract test first — `php vendor/bin/pest tests/Feature/DependencyContractTest.php` —
then the full suite, then record the verified versions in `CHANGELOG.md`.

## Pull requests

- One concern per PR, with a commit message that says why, not just what.
- Add or adjust a test for every behaviour change. Snapshots regenerated deliberately.
- Update `CHANGELOG.md`: add a `## Unreleased` heading at the top if there is not
  already one, and put the entry under it. A maintainer turns it into a dated version
  heading when it ships. Update `README.md` too, where a user would notice.
- New user-facing strings get a translation key; new settings get a config default, a
  plugin method and a comment in the config file. A setting with an environment variable
  also gets a row in the README's table, which is kept deliberately short.

## Reporting bugs

Use the issue template. A failing test, or the relevant `filament-fuse` and `fuse`
config values alongside the Fuse and Filament versions, shortens the round trip
enormously.
