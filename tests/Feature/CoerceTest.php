<?php

use KonspyracyLabs\FilamentFuse\Support\Coerce;

/**
 * The one place a config value or a Fuse stats entry becomes a typed scalar. A blind
 * cast made `FILAMENT_FUSE_POLL=abc` mean "polling off"; these pin down that garbage
 * means "the default" instead, and that every valid shape still comes through intact.
 */
it('reads integers from anything numeric and falls back otherwise', function (mixed $in, int $out): void {
    expect(Coerce::int($in, 5))->toBe($out);
})->with([
    'int' => [12, 12],
    'numeric string' => ['12', 12],
    'float, truncated' => [7.9, 7],
    'negative string' => ['-3', -3],
    'not a number' => ['abc', 5],
    'empty string' => ['', 5],
    'null' => [null, 5],
    'bool' => [true, 5],
    'array' => [[12], 5],
]);

it('reads floats the same way', function (mixed $in, float $out): void {
    expect(Coerce::float($in, 0.5))->toBe($out);
})->with([
    'float' => [71.5, 71.5],
    'int' => [71, 71.0],
    'numeric string' => ['71.5', 71.5],
    'not a number' => ['high', 0.5],
    'null' => [null, 0.5],
]);

it('reads strings, accepting numbers and Stringables, and falls back otherwise', function (mixed $in, string $out): void {
    expect(Coerce::string($in, 'default'))->toBe($out);
})->with([
    'string' => ['monitoring', 'monitoring'],
    'int' => [12, '12'],
    'stringable' => [new class implements Stringable
    {
        public function __toString(): string
        {
            return 'from-object';
        }
    }, 'from-object'],
    'empty string' => ['', 'default'],
    'null' => [null, 'default'],
    'array' => [['monitoring'], 'default'],
    'bool' => [true, 'default'],
]);

it('treats an empty or absent string as null', function (): void {
    expect(Coerce::stringOrNull('redis'))->toBe('redis')
        ->and(Coerce::stringOrNull(''))->toBeNull()
        ->and(Coerce::stringOrNull(null))->toBeNull()
        ->and(Coerce::stringOrNull(false))->toBeNull();
});
