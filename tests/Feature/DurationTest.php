<?php

use KonspyracyLabs\FilamentFuse\Support\Duration;

/**
 * Durations are assembled server-side and handed to views finished, so this is the
 * only place the wording is decided.
 */
it('shows the two largest units that carry information', function (int $seconds, string $expected): void {
    expect(Duration::humanize($seconds))->toBe($expected);
})->with([
    'seconds only' => [8, '8s'],
    'minutes and seconds' => [252, '4m 12s'],
    'hours and minutes' => [11_520, '3h 12m'],
    'days and hours' => [532_800, '6d 4h'],
    'exact hour drops the minutes' => [3600, '1h'],
    'exact day drops the hours' => [86_400, '1d'],
    'nine days exactly' => [777_600, '9d'],
    'zero' => [0, '0s'],
]);

it('never renders a negative duration', function (): void {
    // Clock skew between web servers is real, and "-3s ago" reads as a bug.
    expect(Duration::humanize(-90))->toBe('0s');
});

it('renders a countdown as a clock reading', function (int $seconds, string $expected): void {
    expect(Duration::countdown($seconds))->toBe($expected);
})->with([
    'under a minute pads the seconds' => [48, '0:48'],
    'minutes and seconds' => [131, '2:11'],
    'exact minute' => [120, '2:00'],
    'over an hour keeps counting minutes' => [3661, '61:01'],
    'zero' => [0, '0:00'],
    'negative floors at zero' => [-5, '0:00'],
]);
