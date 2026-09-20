<?php

namespace KonspyracyLabs\FilamentFuse\Authorization;

use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use KonspyracyLabs\FilamentFuse\FilamentFuse;
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;
use Throwable;

/**
 * Decides whether the current user may view the dashboard and a circuit's detail page.
 *
 * One rule, applied in order, for every page and the topbar indicator:
 *
 *   1. Disabled package: nothing is allowed.
 *   2. A per-panel `authorize()` callback on the plugin decides alone.
 *   3. With `authorization.enforce` on, the Gate decides alone — an ability nobody has
 *      granted is denied. This is the mode for a panel that already has roles.
 *   4. Otherwise: a `viewFilamentFuse` gate decides if one exists; failing that, any
 *      authenticated panel user is allowed. Filament has already required a login, and a
 *      monitoring tool should not invent a second authorisation layer nobody asked for —
 *      but "nobody has said otherwise" still requires somebody to be signed in, since
 *      this package is on and open by default and a panel route can be guest-reachable.
 */
class Authorizer
{
    public function __construct(
        private readonly FilamentFuse $fuse,
    ) {}

    public function allows(Ability $ability, ?Panel $panel = null): bool
    {
        if (! $this->fuse->isEnabled()) {
            return false;
        }

        $plugin = FilamentFusePlugin::resolve($panel);

        if ($plugin->hasAuthorization()) {
            return $plugin->isAuthorized($ability);
        }

        if ((bool) config('filament-fuse.authorization.enforce', false)) {
            return Gate::allows($ability->value);
        }

        if (Gate::has($ability->value)) {
            return Gate::allows($ability->value);
        }

        return $this->currentUser() instanceof Authenticatable;
    }

    /**
     * The signed-in panel user, or null for a guest or an unreachable auth guard.
     *
     * Guarded the same way {@see FilamentFusePlugin::currentUser()} is: a queue worker
     * or a test can call this with no panel request in flight, and asking anyway should
     * fail closed rather than throw.
     */
    private function currentUser(): ?Authenticatable
    {
        try {
            return filament()->auth()->user();
        } catch (Throwable) {
            return null;
        }
    }
}
