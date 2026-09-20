<?php

namespace KonspyracyLabs\FilamentFuse\Tests\Fixtures;

use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;

/**
 * A minimal panel with the plugin installed, so the pages can be exercised.
 *
 * Registering the plugin is the point: it is what proves the pages reach a panel at
 * all, and it means a mistake in FilamentFusePlugin fails a test rather than only
 * showing up in somebody's application.
 */
class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('testing')
            ->path('testing')
            ->middleware([
                EncryptCookies::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugin(FilamentFusePlugin::make());
    }
}
