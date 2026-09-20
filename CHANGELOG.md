# Changelog

All notable changes to this package are documented here.

## 0.1.1 — 2026-09-21

### Fixed

- Static analysis failed on Linux for the dashboard's glossary: its view name was written
  out as a literal, which the analyser could not prove exists there. It is now built from
  the package's view namespace and checked before rendering, as the components' names
  already are. Nothing about the panel changes.

## 0.1.0 — 2026-09-20

First release.

A Filament panel plugin over `harris21/laravel-fuse`: a live dashboard for one circuit
breaker, a detail page with the four counters that explain it, a topbar indicator, a
navigation badge, and drills you can run against the package's own testing circuit to
prove the dashboard actually shows an outage.

- The dashboard reads Fuse's cache directly, with no database and no configuration
  beyond naming the circuit to watch.
- Circuit detail page: the live orb, the duration while down, the next-probe countdown,
  and attempts / failures / failure rate / minimum requests for the current window.
- One circuit at a time, chosen by `FILAMENT_FUSE_CIRCUIT` or the first circuit declared
  in `config/fuse.php`. `php artisan filament-fuse:install` asks which one and offers to
  write it to `.env`.
- `php artisan filament-fuse:drill` opens the package's own testing circuit for real —
  the tile turns red, the topbar counts it — for a configured duration, then closes
  itself; `--stop` closes it by hand, `--tick` is what the scheduler calls. Refused in
  production unless `FILAMENT_FUSE_TESTING_CIRCUIT_PRODUCTION` says otherwise.
  Never touches a circuit that carries traffic — including when its configured name
  collides with a circuit `config/fuse.php` already declares: the drill refuses to
  start, and `--stop`/`--tick` leave that circuit alone rather than force-close it,
  whether or not drills are switched on. A refused `--stop` exits non-zero and names
  `php artisan fuse:close` as the deliberate way to close the real circuit. This holds
  under a cached config too — the package records the name it registered so a cached
  copy of its own entry is never mistaken for a circuit you declared.
- One permission: `viewFilamentFuse`. By default, any authenticated panel user is
  allowed and a guest is always denied; switch `authorization.enforce` on to hand the
  decision entirely to your Gate.
- On by default — there is nothing to install before it is safe to run.
