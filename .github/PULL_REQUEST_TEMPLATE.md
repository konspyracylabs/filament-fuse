## What this changes

<!-- One or two sentences. Link the issue if there is one. -->

## Why

<!-- The reason, not the diff. What was wrong, or what could not be done before. -->

## Checklist

- [ ] `composer finalize` passes (Rector, Pint, PHPStan, Pest — in that order)
- [ ] Behaviour changes have a test; snapshots regenerated deliberately and the diff read
- [ ] New strings have a translation key; new settings have a config default *and* a plugin method
- [ ] `CHANGELOG.md` updated under **Unreleased**
- [ ] README updated where a user would notice
