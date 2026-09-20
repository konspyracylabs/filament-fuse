# Filament Fuse

A live circuit-breaker dashboard for your Filament panel, on top of
[`harris21/laravel-fuse`](https://github.com/harris21/laravel-fuse).

![The dashboard with one circuit breaker open](https://raw.githubusercontent.com/konspyracylabs/filament-fuse/main/art/dashboard-open.png)

## What it does

- **Dashboard** — a tile for your circuit that turns red the moment it opens, refreshed
  every few seconds.
- **Circuit page** — live state, how long it has been that way, when the next probe is
  due, and the four counters behind it: attempts, failures, failure rate, minimum requests.
- **Topbar indicator and navigation badge** — the open count, on every page of the panel.
- **Drills** — open a circuit on purpose to see the dashboard respond before you need it to.
- **One permission**, `viewFilamentFuse`, for panels that already have roles.
- **No database.** Everything is read from Fuse's own cache: no migrations, no models.

It monitors **one** circuit. A [Pro edition](#pro-edition) that monitors all of them and
keeps their history is on the way.

## Requirements

PHP 8.3+ · Laravel 11, 12 or 13 · Filament 5 · `harris21/laravel-fuse` ^0.10

Fuse keeps circuit state in the cache, so `CACHE_STORE` must be one your web requests, CLI
and queue workers share — not `array`.

## Install

```bash
composer require konspyracylabs/filament-fuse

# only if Fuse is not set up yet — this creates config/fuse.php
php artisan vendor:publish --tag=fuse-config
```

Declare at least one circuit in `config/fuse.php` (an empty array uses Fuse's defaults):

```php
'services' => [
    'stripe' => [],
],
```

Run the installer. It publishes `config/filament-fuse.php`, asks which circuit to monitor
and offers to write that to your `.env`:

```bash
php artisan filament-fuse:install
```

Register the plugin and publish its stylesheet:

```php
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;

$panel->plugin(FilamentFusePlugin::make());
```

```bash
php artisan filament:assets
```

**Fuse Monitor** is now in your panel's navigation, under *Monitoring*.

## Minimum configuration

Nothing is required. These are the settings most installs touch:

```env
FILAMENT_FUSE_CIRCUIT=stripe          # the one circuit to monitor; blank = the first one declared
FILAMENT_FUSE_TESTING_CIRCUIT=true    # registers the package's own circuit, for drills
```

| Env | Default | |
|---|---|---|
| `FILAMENT_FUSE_ENABLED` | `true` | Master switch. Off shows nothing to anyone. |
| `FILAMENT_FUSE_CIRCUIT` | *(blank)* | The circuit to monitor. Must be declared in `config/fuse.php`. |
| `FILAMENT_FUSE_POLL` | `5` | Seconds between refreshes. `0` stops polling. |
| `FILAMENT_FUSE_TIMEZONE` | *(blank)* | Falls back to Filament's, then the app's, then UTC. |
| `FILAMENT_FUSE_ENFORCE_PERMISSIONS` | `false` | See [Permissions](#permissions). |
| `FILAMENT_FUSE_TESTING_CIRCUIT` | `false` | Enables [drills](#commands). |
| `FILAMENT_FUSE_TESTING_CIRCUIT_NAME` | `testing-circuit-breaker` | Must not be the name of a real circuit. |
| `FILAMENT_FUSE_TESTING_CIRCUIT_DURATION` | `300` | Seconds a drill holds the circuit open. |
| `FILAMENT_FUSE_TESTING_CIRCUIT_SCHEDULE` | `false` | Close finished drills from the scheduler. |
| `FILAMENT_FUSE_TESTING_CIRCUIT_PRODUCTION` | `false` | Allow a drill to start in production. |

The slug, navigation group, icons, indicator and badge are in `config/filament-fuse.php`,
each with a comment, and can be set per panel on the plugin:

```php
FilamentFusePlugin::make()->slug('circuits')->navigationGroup('Ops')->poll(10);
```

## Commands

| Command | |
|---|---|
| `php artisan filament-fuse:install` | Publish the config and choose the circuit to monitor. |
| `php artisan filament-fuse:drill` | Open the package's testing circuit for the configured duration. |
| `php artisan filament-fuse:drill --for=60` | …or for this many seconds. |
| `php artisan filament-fuse:drill --stop` | Close it now. |
| `php artisan filament-fuse:drill --tick` | Close it if its time is up. What the scheduler runs. |

A drill only ever opens the package's own testing circuit, which carries no traffic. It is
**refused in production** unless `FILAMENT_FUSE_TESTING_CIRCUIT_PRODUCTION=true`, and
refused outright if the testing circuit's name belongs to a circuit you declared yourself.

Fuse's own commands are not guarded by anybody. `php artisan fuse:open {service}` on a real
circuit is an outage you caused: that service's jobs stop going out until it closes.
`fuse:close {service}` closes one circuit; `fuse:reset` closes all of them and clears their
counters, which hides an outage rather than ending it. Keep them to local and staging.

## Permissions

By default **any signed-in user of the panel** can see the dashboard — your circuit's name,
its thresholds, and how many other circuits you have declared. Guests never can. On a panel
your customers log into, hand the decision to your Gate:

```env
FILAMENT_FUSE_ENFORCE_PERMISSIONS=true
```

```php
Gate::define('viewFilamentFuse', fn (User $user): bool => $user->isAdmin());
```

With spatie/laravel-permission, create a permission named `viewFilamentFuse` and grant it to
a role. To decide per panel instead, pass a callback; `$user` may be null:

```php
FilamentFusePlugin::make()->authorize(fn (?User $user): bool => $user?->hasRole('sre') ?? false);
```

## Troubleshooting

- **Nothing in the navigation, or a 403.** `FILAMENT_FUSE_ENFORCE_PERMISSIONS=true` with
  nobody granted `viewFilamentFuse`, or `FILAMENT_FUSE_ENABLED=false`.
- **"No circuits are being monitored."** `config/fuse.php` declares no services. Publish it
  with `--tag=fuse-config` and add one.
- **The wrong circuit is shown.** `FILAMENT_FUSE_CIRCUIT` names a circuit that is not
  declared, so the first declared one is used; the dashboard's subheading says so.
- **A change to `.env` does nothing.** Your config is cached. Run `php artisan config:cache`
  again, or `config:clear`.
- **The tile never turns red.** Fuse protects *queued* jobs that carry its middleware; a job
  run synchronously never trips a circuit. Or the cache store is `array`, or not the same
  one your workers use.
- **"State unreadable."** The cache store could not be reached. The tile says so rather than
  guessing.
- **The dashboard is unstyled.** Run `php artisan filament:assets`.
- **A drill refuses to start.** The testing circuit is off, you are in production, or its
  name is also a circuit in `config/fuse.php`. The command says which.
- **A drill is still open.** `php artisan filament-fuse:drill --stop`. If you renamed the
  testing circuit while it ran, `php artisan fuse:close {old name}`.
- **Customised views stopped updating.** Published views win over the package's. Do not
  publish `filament-fuse-views` unless you mean to own those files.

## Screenshots

<details>
<summary>Closed, the circuit page, a drill, dark mode</summary>

![Every circuit breaker closed](https://raw.githubusercontent.com/konspyracylabs/filament-fuse/main/art/dashboard-closed.png)

![The page of an open circuit](https://raw.githubusercontent.com/konspyracylabs/filament-fuse/main/art/circuit-open.png)

![The page of a closed circuit](https://raw.githubusercontent.com/konspyracylabs/filament-fuse/main/art/circuit-closed.png)

![A drill holding the testing circuit open](https://raw.githubusercontent.com/konspyracylabs/filament-fuse/main/art/drill.png)

![The dashboard in dark mode](https://raw.githubusercontent.com/konspyracylabs/filament-fuse/main/art/dashboard-dark.png)

</details>

## Pro edition

Coming soon. This package watches one circuit and keeps no history; the Pro edition will
monitor every circuit in `config/fuse.php`, record each outage and recovery attempt, and add
an incident history and per-circuit email notifications. It is not available yet — watch
this repository for the announcement.

## License

MIT. See [LICENSE.md](LICENSE.md).
