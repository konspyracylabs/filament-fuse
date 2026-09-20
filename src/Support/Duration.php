<?php

namespace KonspyracyLabs\FilamentFuse\Support;

/**
 * Formats the two kinds of elapsed time this package shows.
 *
 * Durations are built here rather than in Blade so that a translator is handed one
 * finished `:duration` string instead of being asked to reassemble a sentence from
 * a number and a unit — an arrangement that breaks the moment a language puts them
 * in the other order.
 */
class Duration
{
    /**
     * A coarse, glanceable duration: the two largest units that carry information.
     *
     * "6d 4h", "3h 12m", "4m 12s", "8s". The second unit is dropped when it is zero,
     * so a circuit that has been up for exactly nine days reads "9d" rather than
     * "9d 0h".
     */
    public static function humanize(int $seconds): string
    {
        $seconds = max(0, $seconds);

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return match (true) {
            $days > 0 => self::join(self::unit('days', $days), $hours > 0 ? self::unit('hours', $hours) : null),
            $hours > 0 => self::join(self::unit('hours', $hours), $minutes > 0 ? self::unit('minutes', $minutes) : null),
            $minutes > 0 => self::join(self::unit('minutes', $minutes), $rest > 0 ? self::unit('seconds', $rest) : null),
            default => self::unit('seconds', $rest),
        };
    }

    /**
     * A ticking countdown, in minutes and zero-padded seconds.
     *
     * Deliberately not translated: "2:11" is a clock reading, not a sentence, and it
     * is the same in every locale this package is likely to see.
     */
    public static function countdown(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Render one value with its translated unit suffix.
     */
    private static function unit(string $key, int $value): string
    {
        return __("filament-fuse::filament-fuse.duration.{$key}", ['value' => $value]);
    }

    private static function join(string $first, ?string $second): string
    {
        return $second === null ? $first : "{$first} {$second}";
    }
}
