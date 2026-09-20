<?php

/**
 * `filament-fuse:install` asks which circuit to monitor and offers to remember it in
 * .env. The value offered is always one of the keys already in config('fuse.services'),
 * chosen from a list, so nothing attacker-shaped ever reaches the file — but the write
 * itself is still append-only, guarded by a safe-name check, and only ever attempted
 * after the operator has been shown the exact line and has said yes.
 *
 * Every test here points the application at a temporary .env file rather than
 * testbench's own, so the suite can never corrupt the file it runs under.
 */
function tempEnvDir(): string
{
    $dir = sys_get_temp_dir().'/filament-fuse-install-test-'.uniqid();
    mkdir($dir);

    return $dir;
}

/**
 * publishConfigFile() is the install command's first action, and every test in this
 * file runs it. Spatie's package tools resolve config_path() once, at boot — before
 * useEnvironmentPath() above would even take effect for the env path — so it always
 * publishes into the real config_path(): testbench's shared vendor/orchestra copy of
 * the Laravel skeleton, not a path this test controls.
 *
 * Left alone, the first local run of this file writes config/filament-fuse.php there
 * for real, and from then on every test about config DEFAULTS anywhere in the suite
 * reads that stale published copy instead of the package's own shipped file — flipping
 * a default here no longer fails anything, because nothing is reading the file that
 * changed. Deleting it before and after every test in this file is what keeps that
 * copy from ever existing for another test to find.
 */
function deletePublishedConfigCopy(): void
{
    $path = config_path('filament-fuse.php');

    if (file_exists($path)) {
        unlink($path);
    }
}

beforeEach(function (): void {
    config()->set('fuse.services', ['stripe' => [], 'sendgrid' => []]);
    deletePublishedConfigCopy();
});

afterEach(function (): void {
    deletePublishedConfigCopy();
});

it('points the harness at a temporary .env file, never at testbench\'s own', function (): void {
    $dir = tempEnvDir();
    $this->app->useEnvironmentPath($dir);

    // Compared with slashes normalised on both sides: environmentFilePath() joins
    // with DIRECTORY_SEPARATOR, which is a backslash on Windows, and sys_get_temp_dir()
    // itself can come back with either separator depending on the platform.
    $normalize = fn (string $path): string => str_replace('\\', '/', $path);

    expect($normalize(app()->environmentFilePath()))->toBe($normalize($dir).'/.env')
        ->and(app()->environmentFilePath())->not->toContain('workbench');
});

it('lists every real circuit and returns the one chosen', function (): void {
    $dir = tempEnvDir();
    $this->app->useEnvironmentPath($dir);

    $this->artisan('filament-fuse:install')
        ->expectsQuestion('Which circuit should the dashboard monitor?', 'sendgrid')
        ->expectsOutputToContain('FILAMENT_FUSE_CIRCUIT="sendgrid"')
        ->assertSuccessful();
});

it('picks the first circuit and writes nothing when run non-interactively', function (): void {
    $dir = tempEnvDir();
    $this->app->useEnvironmentPath($dir);
    file_put_contents($dir.'/.env', '');

    $this->artisan('filament-fuse:install', ['--no-interaction' => true])
        ->expectsOutputToContain('Non-interactive: stripe will be monitored')
        ->assertSuccessful();

    expect(file_get_contents($dir.'/.env'))->toBe('');
});

it('says so and touches nothing when no circuits are declared', function (): void {
    config()->set('fuse.services', []);

    $dir = tempEnvDir();
    $this->app->useEnvironmentPath($dir);

    $this->artisan('filament-fuse:install')
        ->expectsOutputToContain('No circuits are declared in config/fuse.php yet.')
        ->assertSuccessful();

    expect(is_dir($dir) && count(scandir($dir)) === 2)->toBeTrue(); // only . and ..
});

it('leaves .env byte-identical when the key is already set', function (): void {
    $dir = tempEnvDir();
    $this->app->useEnvironmentPath($dir);

    $original = "APP_NAME=Test\nFILAMENT_FUSE_CIRCUIT=\"stripe\"\n";
    file_put_contents($dir.'/.env', $original);

    $this->artisan('filament-fuse:install')
        ->expectsQuestion('Which circuit should the dashboard monitor?', 'sendgrid')
        ->expectsOutputToContain('.env already sets FILAMENT_FUSE_CIRCUIT')
        ->assertSuccessful();

    expect(file_get_contents($dir.'/.env'))->toBe($original);
});

it('prints the line and touches nothing when there is no .env file', function (): void {
    $dir = tempEnvDir();
    $this->app->useEnvironmentPath($dir);

    $this->artisan('filament-fuse:install')
        ->expectsQuestion('Which circuit should the dashboard monitor?', 'stripe')
        ->expectsOutputToContain('No .env file was found')
        ->assertSuccessful();

    expect(file_exists($dir.'/.env'))->toBeFalse();
});

it('confirms before writing, and writes only on yes', function (): void {
    $dir = tempEnvDir();
    $this->app->useEnvironmentPath($dir);
    file_put_contents($dir.'/.env', "APP_NAME=Test\n");

    $this->artisan('filament-fuse:install')
        ->expectsQuestion('Which circuit should the dashboard monitor?', 'stripe')
        ->expectsConfirmation('Add it to .env?', 'no')
        ->assertSuccessful();

    expect(file_get_contents($dir.'/.env'))->toBe("APP_NAME=Test\n");
});

it('appends the line with a trailing newline once confirmed', function (): void {
    $dir = tempEnvDir();
    $this->app->useEnvironmentPath($dir);
    file_put_contents($dir.'/.env', "APP_NAME=Test\n");

    $this->artisan('filament-fuse:install')
        ->expectsQuestion('Which circuit should the dashboard monitor?', 'stripe')
        ->expectsConfirmation('Add it to .env?', 'yes')
        ->expectsOutputToContain('Added to .env.')
        ->assertSuccessful();

    expect(str_replace("\r\n", "\n", (string) file_get_contents($dir.'/.env')))
        ->toBe("APP_NAME=Test\n\nFILAMENT_FUSE_CIRCUIT=\"stripe\"\n");
});

it('never writes a name that could corrupt the file', function (): void {
    config()->set('fuse.services', ['bad "name"' => []]);

    $dir = tempEnvDir();
    $this->app->useEnvironmentPath($dir);
    file_put_contents($dir.'/.env', '');

    $this->artisan('filament-fuse:install')
        ->expectsQuestion('Which circuit should the dashboard monitor?', 'bad "name"')
        ->expectsOutputToContain('.env cannot hold safely')
        ->assertSuccessful();

    expect(file_get_contents($dir.'/.env'))->toBe('');
});

it('prints rather than throws when the target cannot be written to', function (): void {
    $dir = tempEnvDir();
    $this->app->useEnvironmentPath($dir);

    // A directory where the file should be: is_writable() is true for it (it is a
    // writable directory), but no file can actually be written over it — the failure
    // mode a permissions problem on a real host produces, without depending on this
    // platform's own file permission semantics.
    mkdir($dir.'/.env');

    $this->artisan('filament-fuse:install')
        ->expectsQuestion('Which circuit should the dashboard monitor?', 'stripe')
        ->expectsConfirmation('Add it to .env?', 'yes')
        ->expectsOutputToContain('Could not write to .env')
        ->assertSuccessful();
});
