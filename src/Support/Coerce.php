<?php

namespace KonspyracyLabs\FilamentFuse\Support;

use Stringable;

/**
 * Turns values from outside the package into the scalar the code needs, or a default.
 *
 * Two things arrive as `mixed`: configuration, which anyone can set to anything in a
 * .env file, and Fuse's stats array, which a pre-1.0 dependency is entitled to reshape.
 * A blind cast quietly turns `FILAMENT_FUSE_POLL=abc` into 0 — polling off, no error —
 * where the documented default was the honest answer. These read the value if it is
 * what it claims to be and fall back otherwise.
 */
final class Coerce
{
    public static function int(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function float(mixed $value, float $default): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        return is_numeric($value) ? (float) $value : $default;
    }

    public static function string(mixed $value, string $default): string
    {
        return self::stringOrNull($value) ?? $default;
    }

    /**
     * A non-empty string, or null. Numbers and Stringables are accepted as strings.
     */
    public static function stringOrNull(mixed $value): ?string
    {
        if ($value instanceof Stringable || is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
