<?php

use Filament\Contracts\Plugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;
use KonspyracyLabs\FilamentFuse\FilamentFuseServiceProvider;
use Spatie\LaravelPackageTools\PackageServiceProvider;

arch('it does not leave debug statements behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('all source classes live in the package namespace')
    ->expect('KonspyracyLabs\FilamentFuse')
    ->toOnlyBeUsedIn('KonspyracyLabs\FilamentFuse');

arch('the plugin implements the Filament plugin contract')
    ->expect(FilamentFusePlugin::class)
    ->toImplement(Plugin::class);

arch('the service provider extends the Spatie package service provider')
    ->expect(FilamentFuseServiceProvider::class)
    ->toExtend(PackageServiceProvider::class);

/**
 * This package keeps no database of its own: no migrations, no Eloquent models, no
 * schema access anywhere in src/. A class that reached for any of these would be
 * building the very thing this package deliberately leaves out.
 */
arch('nothing under src/ touches a database')
    ->expect('KonspyracyLabs\FilamentFuse')
    ->not->toUse([
        'Illuminate\Database',
        Schema::class,
        DB::class,
    ]);

/**
 * The rule above stops a class reaching for a database. This one stops the shape that
 * comes before it: a class named after a record this package would have to keep — an
 * incident, a saved setting, a stored probe result. It keeps none, so no such name
 * should exist.
 */
it('names no class after a record it would have to store', function (): void {
    $root = dirname(__DIR__, 2).'/src';
    $offenders = [];

    /** @var iterable<SplFileInfo> $found */
    $found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($found as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        if (preg_match('/\b(Incident\w*|\w*Setting\w*|Probe\w*)\b/', $contents, $matches) === 1) {
            $offenders[] = basename($file->getPathname()).': '.$matches[0];
        }
    }

    expect($offenders)->toBe([]);
});

/**
 * Every file directly under a directory, relative to it, sorted.
 *
 * @return list<string>
 */
function filesUnder(string $directory): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $files = [];

    /** @var iterable<SplFileInfo> $found */
    $found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($found as $file) {
        if ($file->isFile()) {
            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
        }
    }

    sort($files);

    return $files;
}

/**
 * The component classes and their templates are meant to pair up one for one — see
 * CONTRIBUTING.md — so a class with no template, or a template nothing renders,
 * usually means a component was half added or half removed. Pinned against a literal
 * list rather than checked for symmetry, so an intentional exception still has to be
 * written down here rather than silently tolerated.
 */
it('ships exactly the component classes and templates this test names', function (): void {
    $root = dirname(__DIR__, 2);

    expect(filesUnder($root.'/src/View/Components'))->toBe([
        'CircuitHero.php',
        'Concerns/DescribesSnapshot.php',
        'EmptyState.php',
        'ErrorState.php',
        'FuseComponent.php',
        'Indicator.php',
        'LiveStrip.php',
        'Metric.php',
        'Orb.php',
        'Tile.php',
    ]);

    expect(filesUnder($root.'/resources/views/components'))->toBe([
        'circuit-hero.blade.php',
        'empty-state.blade.php',
        'error-state.blade.php',
        'indicator.blade.php',
        'live-strip.blade.php',
        'metric.blade.php',
        'orb.blade.php',
        'tile.blade.php',
    ]);
});

/**
 * Every PHP file this package ships, including Blade templates.
 *
 * @return list<string>
 */
function shippedPhpFiles(): array
{
    $root = dirname(__DIR__, 2);
    $files = [];

    foreach (['src', 'config', 'resources'] as $directory) {
        $path = $root.DIRECTORY_SEPARATOR.$directory;

        if (! is_dir($path)) {
            continue;
        }

        /** @var iterable<SplFileInfo> $found */
        $found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

        foreach ($found as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    return $files;
}

it('ships no file that would print bytes before the response', function (): void {
    // A byte order mark is emitted verbatim before anything else the file produces, so
    // one in a published config corrupts every response the host sends — including the
    // panel's JavaScript, which then dies on a syntax error naming Livewire rather than
    // the file at fault. The config and the translations are both published into host
    // applications, so a mark committed here breaks every install that publishes them,
    // and does it a long way from the cause.
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach (shippedPhpFiles() as $file) {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            continue;
        }

        $head = (string) fread($handle, 3);
        fclose($handle);

        if ($head === "\xEF\xBB\xBF") {
            $offenders[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file);
        }
    }

    expect($offenders)->toBe([]);
});

it('opens every source file with a php tag and nothing before it', function (): void {
    // The same hazard as the mark above, in its other form: a stray blank line or space
    // ahead of the opening tag is output too. Blade templates are exempt because they
    // legitimately begin with markup.
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach (shippedPhpFiles() as $file) {
        if (str_ends_with($file, '.blade.php')) {
            continue;
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            continue;
        }

        $head = (string) fread($handle, 5);
        fclose($handle);

        if ($head !== '<?php') {
            $offenders[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file);
        }
    }

    expect($offenders)->toBe([]);
});
