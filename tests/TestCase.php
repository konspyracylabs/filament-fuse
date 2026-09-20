<?php

namespace KonspyracyLabs\FilamentFuse\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\QueryBuilder\QueryBuilderServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Harris21\Fuse\FuseServiceProvider;
use Illuminate\Auth\GenericUser;
use KonspyracyLabs\FilamentFuse\FilamentFuseServiceProvider;
use KonspyracyLabs\FilamentFuse\Tests\Fixtures\TestPanelProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Filament panels are assembled from a stack of providers. Testbench does not run
     * package discovery for the host app, so they are listed explicitly — the same
     * approach the official Filament plugin skeleton takes.
     *
     * The order is not cosmetic. Filament's SupportServiceProvider rebinds Livewire's
     * DataStore with a plain bind(), while Livewire registers it with instance(); the
     * provider that registers last decides whether the store is shared. Composer
     * discovery loads livewire/livewire after filament/*, so Livewire must come after
     * Filament here too. Sorting this list alphabetically inverts that, leaves the
     * store transient, and every Livewire component silently loses its error bag.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            QueryBuilderServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,

            LivewireServiceProvider::class,

            FuseServiceProvider::class,
            FilamentFuseServiceProvider::class,

            // A panel with the plugin installed, so the pages have somewhere to live.
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('cache.default', 'array');

        // This package keeps no database of its own and none of its tests need one.
        // The default connection here is a real, working sqlite database purely so
        // that DB::enableQueryLog()/getQueryLog() can be used to prove the package
        // makes no queries; the specific claim that the dashboard needs no *usable*
        // connection at all is pinned separately, by pointing at a bogus one for
        // that one assertion only.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // This package is on by default and default-open, so the default-open branch in Authorizer must
        // still refuse a guest (see AuthorizationTest). Most of the suite is about
        // what an authenticated user sees, so that is the default here; the handful
        // of tests that care about a guest log this user back out.
        $this->actingAs(new GenericUser(['id' => 1]));
    }
}
