<?php

namespace KonspyracyLabs\FilamentFuse\Authorization;

/**
 * The one thing a host can grant.
 *
 * The value is a gate name. A host wires it with `Gate::define()`, with a policy, or —
 * the common case — by creating a permission of the same name in whatever permission
 * package the panel already uses: spatie/laravel-permission and its kin register every
 * permission as a gate ability, so `Gate::allows('viewFilamentFuse')` asks the user's
 * roles with no glue code on either side.
 */
enum Ability: string
{
    /** The dashboard and a circuit's detail page. Read-only. */
    case View = 'viewFilamentFuse';
}
