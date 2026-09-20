<?php

/**
 * Keeps `resources/lang/en/filament-fuse.php` and the package's actual use of it in
 * sync, in both directions.
 *
 * A key referenced from `src/` or `resources/views/` that is missing from the lang
 * file renders as the raw `filament-fuse::filament-fuse....` string on screen — that
 * is exactly how `tile.last_good_never` went missing and nobody noticed until an
 * unreadable circuit rendered it. A key nobody references any more is the other half
 * of the same mistake: a leftover from a removed sentence that a future rename can
 * silently stop covering.
 *
 * Most keys are literal strings at their call site and are found by scanning the
 * source. A handful are assembled from a variable — Orb's state icon, the two tile
 * durations, the duration units — and are listed by hand in dynamicLangKeys() rather
 * than skipped, so a new enum case or a new unit still has to be added here too.
 */
function langFile(): array
{
    return require dirname(__DIR__, 2).'/resources/lang/en/filament-fuse.php';
}

/**
 * Flatten the nested lang array into dotted keys, e.g. ['tile' => ['none' => '—']]
 * becomes ['tile.none'].
 *
 * @param  array<string, mixed>  $array
 * @return list<string>
 */
function flattenLangKeys(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $keys = [...$keys, ...flattenLangKeys($value, $path)];

            continue;
        }

        $keys[] = $path;
    }

    return $keys;
}

/**
 * Every .php file (including .blade.php) under the given directory.
 *
 * @return list<string>
 */
function phpFilesUnder(string $directory): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $files = [];

    /** @var iterable<SplFileInfo> $found */
    $found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($found as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/**
 * Every literal translation key this package's PHP and Blade source references, via
 * $this->label(), $this->choice(), __() or trans_choice() against this package's own
 * translation namespace.
 *
 * @return list<string>
 */
function referencedLangKeys(): array
{
    $root = dirname(__DIR__, 2);
    $keys = [];

    $patterns = [
        // $this->label('tile.none') / $this->choice('indicator.down', ...)
        '/\$this->(?:label|choice)\(\s*\'([a-z0-9_.]+)\'/',
        // __('filament-fuse::filament-fuse.nav.circuits') / trans_choice('filament-fuse::filament-fuse....', ...)
        '/(?:__|trans_choice)\(\s*\'filament-fuse::filament-fuse\.([a-z0-9_.]+)\'/',
    ];

    foreach ([$root.'/src', $root.'/resources/views'] as $directory) {
        foreach (phpFilesUnder($directory) as $file) {
            $contents = (string) file_get_contents($file);

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $contents, $matches) > 0) {
                    $keys = [...$keys, ...$matches[1]];
                }
            }
        }
    }

    return array_values(array_unique($keys));
}

/**
 * Keys built from a variable rather than written out at the call site, so the scan
 * above cannot see them. Each one is tied to a closed set this test can enumerate.
 *
 * @return list<string>
 */
function dynamicLangKeys(): array
{
    return [
        // Orb::__construct() — $this->label("state.{$key}") where $key is
        // CircuitSnapshot::stateKey(): one of Fuse's three states, or 'unknown'
        // when the cache could not be read.
        'state.closed',
        'state.open',
        'state.half_open',
        'state.unknown',

        // DescribesSnapshot::describeDuration() — $this->label($key, ...) where
        // $key comes out of a match() over the snapshot's state.
        'tile.open_for',
        'tile.half_open_for',

        // Duration::unit() — __("filament-fuse::filament-fuse.duration.{$key}", ...)
        // where $key is one of the four units humanize() can produce.
        'duration.days',
        'duration.hours',
        'duration.minutes',
        'duration.seconds',
    ];
}

it('has a lang entry for every translation key the package can ask for', function (): void {
    $available = flattenLangKeys(langFile());
    $wanted = [...referencedLangKeys(), ...dynamicLangKeys()];

    expect(array_values(array_diff($wanted, $available)))->toBe([]);
});

it('has no lang entry that nothing in the package ever asks for', function (): void {
    $available = flattenLangKeys(langFile());
    $wanted = [...referencedLangKeys(), ...dynamicLangKeys()];

    expect(array_values(array_diff($available, $wanted)))->toBe([]);
});
