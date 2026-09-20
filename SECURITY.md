# Security policy

## Supported versions

Pre-1.0, only the latest minor receives fixes.

| Version | Supported |
|---|---|
| 0.1.x | Yes |
| < 0.1 | No |

## Reporting a vulnerability

Please do not open a public issue for a security problem.

Use GitHub's private reporting instead: **Security → Report a vulnerability** on
[konspyracylabs/filament-fuse](https://github.com/konspyracylabs/filament-fuse/security/advisories/new).
You will get an acknowledgement within a few days and a fix or a decision as soon as the
report has been reproduced.

## What is in scope

This package reads Fuse's cache and renders it inside an authenticated Filament panel.
It keeps no database of its own. Things worth a report:

- Reading a circuit's live state by a user the `viewFilamentFuse` gate or the plugin's
  `authorize()` callback rejects.
- Reaching a circuit that is not the one monitored — including the package's own testing
  circuit when it is off — through the detail route.
- The install command writing anything to `.env` beyond the exact, quoted
  `FILAMENT_FUSE_CIRCUIT` line, or writing it from anything other than a name already
  declared in `config('fuse.services')`.
- A drill leaving the testing circuit open longer than its configured duration, or
  opening any circuit other than the package's own testing one.

Vulnerabilities in Filament, Livewire, Laravel or `harris21/laravel-fuse` should go to
those projects.
