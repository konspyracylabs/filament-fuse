<?php

namespace KonspyracyLabs\FilamentFuse;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use KonspyracyLabs\FilamentFuse\Commands\DrillCommand;
use KonspyracyLabs\FilamentFuse\Support\CircuitInspector;
use KonspyracyLabs\FilamentFuse\Support\Coerce;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Throwable;

class FilamentFuseServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-fuse';

    public static string $viewNamespace = 'filament-fuse';

    /**
     * The pattern a circuit name has to match before it is written to .env.
     *
     * The value offered is always one of the keys already in config('fuse.services'),
     * never free text, but the write itself still guards against a name a host somehow
     * configured with a character that would corrupt the file: phpdotenv interpolates
     * ${VAR} inside double-quoted values, and a quote, a `#` or a newline would break
     * the line entirely.
     */
    private const string SAFE_NAME = '/\A[A-Za-z0-9._:\/-]{1,64}\z/';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews(static::$viewNamespace)
            ->hasCommands([DrillCommand::class])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command->setHidden(false);
                $command
                    ->publishConfigFile()
                    ->endWith(function (InstallCommand $command): void {
                        $chosen = $this->chooseCircuit($command);

                        $command->newLine();
                        $command->comment('Two steps left:');
                        $command->line('  1. Register the plugin on your panel: ->plugin(FilamentFusePlugin::make())');
                        $command->line($chosen === null
                            ? '  2. Declare a circuit in config/fuse.php, then set FILAMENT_FUSE_CIRCUIT to its name'
                            : "  2. Nothing — {$chosen} is being monitored");
                    });
            });
    }

    public function packageRegistered(): void
    {
        $this->app->scoped(FilamentFuse::class, static fn (): FilamentFuse => new FilamentFuse);

        // Scoped as well, so the nav badge and the topbar indicator — both rendered on
        // every panel request — share one pass over Fuse's cache instead of two.
        $this->app->scoped(CircuitInspector::class);
    }

    public function packageBooted(): void
    {
        $this->registerTestingCircuit();
        $this->registerScheduledDrillTick();
        $this->registerComponents();
        $this->registerAssets();
    }

    /**
     * Expose the view components as <x-filament-fuse::tile> and friends.
     *
     * Class-based rather than anonymous, and deliberately so: every component resolves
     * its own strings, classes and icons in PHP, which keeps the templates to markup
     * and puts the decisions somewhere PHPStan and a unit test can reach them.
     */
    protected function registerComponents(): void
    {
        Blade::componentNamespace(
            'KonspyracyLabs\\FilamentFuse\\View\\Components',
            static::$viewNamespace,
        );
    }

    /**
     * Register the package's stylesheets with Filament's asset manager.
     *
     * Published by `php artisan filament:assets` alongside every other plugin's, so
     * hosts have one command to run rather than a package-specific one to remember.
     */
    protected function registerAssets(): void
    {
        FilamentAsset::register($this->assets(), 'konspyracylabs/filament-fuse');
    }

    /**
     * The one stylesheet this package ships.
     *
     * Local, and the only asset there is: the panel makes no request to anybody else's
     * server on this package's account, so there is nothing to allow in a Content
     * Security Policy and nothing that tells a third party who is looking at the panel.
     *
     * @return list<Css>
     */
    public function assets(): array
    {
        return [
            Css::make('filament-fuse', __DIR__.'/../resources/dist/filament-fuse.css'),
        ];
    }

    /**
     * Register the package's own circuit with Fuse, when the config asks for it.
     *
     * A circuit that exists only to be broken is a panel concern, so switching it on is a
     * change to this package's config and nothing else. Fuse's registry is merged into at
     * runtime rather than asking the host to add a fake service to config/fuse.php, which
     * would put a testing fixture in the circuit breaker's own list of real dependencies
     * and leave it there long after anybody remembered why.
     *
     * Registered rather than special-cased so that everything downstream — the dashboard,
     * the navigation, the detail page, the breaker's own thresholds — treats it as the
     * ordinary circuit it is. The settings below are chosen to trip quickly, because the
     * point is to rehearse an outage rather than to wait for one.
     *
     * Never overwrites a circuit the host already declared under the same name: theirs
     * carries traffic, and this must never be able to quietly take one over. Whether that
     * happened is recorded in filament-fuse.testing_circuit._collides_with_host — a
     * runtime-only flag, never published or read from a config file — so that Drill and
     * FilamentFuse can tell a genuine testing circuit apart from a name that only looks
     * like one, instead of each repeating this check with a stale answer.
     *
     * The collision has to be worked out fresh on every boot, before anything looks at
     * `enabled`. `php artisan config:cache` boots the application to build the cache it
     * dumps, so this method's own entry in `fuse.services` from that boot is baked into
     * the cache alongside everything else — and the very next request boots again with
     * that entry already sitting in `config('fuse.services')` before this method runs a
     * second time. `array_key_exists()` alone cannot tell that entry apart from one the
     * host declared; the name it was registered under is recorded beside it, and cached
     * with it, so a later boot can. And because the flag has to be right regardless of
     * `enabled`, a host circuit stays protected from `--stop`/`--tick` even while drills
     * are switched off — turning drills off must never turn off the one check that stops
     * them closing something that was never theirs.
     *
     * Public, and safe to call more than once: a real request boots this exactly once,
     * before anything else runs, but a test that changes filament-fuse.testing_circuit.*
     * after the application has already booted needs a way to make that config take
     * effect without rebooting the whole thing.
     */
    public function registerTestingCircuit(): void
    {
        $name = Coerce::string(config('filament-fuse.testing_circuit.name'), 'testing-circuit-breaker');

        /** @var array<string, mixed> $services */
        $services = config('fuse.services', []);

        $collides = array_key_exists($name, $services)
            && config('filament-fuse.testing_circuit._registered_as') !== $name;

        config()->set('filament-fuse.testing_circuit._collides_with_host', $collides);

        if ($collides || ! config('filament-fuse.testing_circuit.enabled', false)) {
            return;
        }

        config()->set('filament-fuse.testing_circuit._registered_as', $name);

        $services[$name] = [
            'threshold' => 50,
            'min_requests' => 2,
            'timeout' => 30,
            'window' => 120,
            'release' => 5,
        ];

        config()->set('fuse.services', $services);
    }

    /**
     * Close finished drills from the scheduler.
     *
     * A drill opens a real circuit, so the one thing that must not happen is it staying
     * open because whoever started it walked away. The deadline is recorded rather than
     * held in the starting process, and this closes it once it passes — so a drill
     * survives a deploy, a crashed console, or a closed laptop, and still ends on time.
     *
     * Registered only when the config asks for it, so turning it on and off leaves
     * nothing behind in the host's routes/console.php.
     */
    protected function registerScheduledDrillTick(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (! config('filament-fuse.testing_circuit.enabled', false) || ! config('filament-fuse.testing_circuit.schedule', false)) {
                return;
            }

            $schedule->command('filament-fuse:drill --tick')
                ->everyMinute()
                ->withoutOverlapping();
        });
    }

    /**
     * Ask which circuit to monitor, and offer to write it to .env.
     *
     * @return string|null The circuit chosen, or null when none is declared yet.
     */
    private function chooseCircuit(InstallCommand $command): ?string
    {
        /** @var array<string, mixed> $services */
        $services = config('fuse.services', []);
        $names = array_map(strval(...), array_keys($services));

        if ($names === []) {
            $command->line('No circuits are declared in config/fuse.php yet.');

            return null;
        }

        // The global --no-interaction flag Symfony adds to every command. Checked
        // before asking anything at all: a choice() prompt would fall through to its
        // default with nobody able to answer it, but the .env write that follows a
        // real answer must not run unattended — an install run non-interactively (a
        // deploy script, a Dockerfile) must not write to a file it was never asked to
        // touch.
        if ((bool) $command->option('no-interaction')) {
            $chosen = $names[0];

            $command->line("Non-interactive: {$chosen} will be monitored. Set FILAMENT_FUSE_CIRCUIT=\"{$chosen}\" in .env to make it permanent.");

            return $chosen;
        }

        $answer = $command->choice('Which circuit should the dashboard monitor?', $names, 0);

        // choice() is typed to allow an array back, for a multiselect prompt this
        // never asks for; a plain string is the only shape it can actually return here.
        $chosen = is_string($answer) ? $answer : $names[0];

        $this->rememberChosenCircuit($command, $chosen);

        return $chosen;
    }

    /**
     * Offer to append FILAMENT_FUSE_CIRCUIT to the host's .env file.
     *
     * Append-only, and only after the operator confirms: the line is always printed
     * first, so a "no" or a failure still leaves a clear instruction behind. Nothing is
     * written when the key is already present, when there is no .env file, or when the
     * chosen name fails a conservative safe-name check.
     */
    private function rememberChosenCircuit(InstallCommand $command, string $chosen): void
    {
        $line = "FILAMENT_FUSE_CIRCUIT=\"{$chosen}\"";

        $command->newLine();
        $command->line("  {$line}");

        if (preg_match(self::SAFE_NAME, $chosen) !== 1) {
            $command->line('This name has a character .env cannot hold safely — add the line above yourself.');

            return;
        }

        $path = app()->environmentFilePath();

        if ($path === '' || ! file_exists($path)) {
            $command->line('No .env file was found — add the line above yourself.');

            return;
        }

        $contents = @file_get_contents($path);

        if ($contents !== false && str_contains($contents, 'FILAMENT_FUSE_CIRCUIT=')) {
            $command->line('.env already sets FILAMENT_FUSE_CIRCUIT — left untouched.');

            return;
        }

        if (! $command->confirm('Add it to .env?', true)) {
            return;
        }

        if (! is_writable($path)) {
            $command->line('.env is not writable — add the line above yourself.');

            return;
        }

        try {
            // "\n" rather than PHP_EOL: a .env file is a Unix-style text file wherever
            // it is deployed, whatever platform this command happens to run on.
            $written = @file_put_contents($path, "\n{$line}\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            $written = false;
        }

        if ($written === false) {
            $command->line('Could not write to .env — add the line above yourself.');

            return;
        }

        $command->info('Added to .env.');

        if (app()->configurationIsCached()) {
            $command->line('Your configuration is cached, so nothing changes until you run: php artisan config:clear');
        }
    }
}
