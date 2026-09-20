<?php

/**
 * Every string the package renders.
 *
 * No English is baked into a layout: views reference keys from this file only, so a
 * translator can ship a locale without touching Blade. Counts use Laravel's
 * pluralisation, and durations are formatted server-side and passed in as :duration —
 * translators are never asked to assemble a sentence out of fragments.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Navigation and page titles
    |--------------------------------------------------------------------------
    */

    'nav' => [
        'group' => 'Monitoring',
        'circuits' => 'Fuse Monitor',
        'circuits_parent' => 'Circuit breakers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard sections
    |--------------------------------------------------------------------------
    */

    'dashboard' => [
        // Fuse's own words. A breaker is open (tripped), closed (passing traffic) or
        // half-open (one probe out); the dashboard uses no other vocabulary.
        'open' => 'Open',
        'closed' => 'Closed',
        'none_open' => 'Every breaker is closed.',
        'none_closed' => 'No breaker is closed.',
        'about' => 'What the states mean',
    ],

    /*
    |--------------------------------------------------------------------------
    | Glossary — the (i) beside the dashboard title
    |--------------------------------------------------------------------------
    */

    'glossary' => [
        'heading' => 'Circuit breaker states',
        'closed' => 'Closed',
        'closed_body' => 'The normal state. Calls to the service go through. Failures are counted in a rolling window; when the failure rate crosses the threshold, the breaker opens.',
        'open' => 'Open',
        'open_body' => 'Tripped. Nothing is sent to the service; jobs for it are held on the queue and released back a little later, so they are not lost. After the cooldown the breaker becomes half-open.',
        'half_open' => 'Half-open',
        'half_open_body' => 'A test. The next job for the service is let through as a probe while everything else stays held. If it succeeds the breaker closes and the backlog drains; if it fails the breaker opens again and the cooldown restarts. This lasts as long as the probe job takes to run.',
        'unknown' => 'Unreadable',
        'unknown_body' => 'The breaker\'s state could not be read from the cache. It is shown apart from the closed ones on purpose: an unreachable cache is not good news.',
        'close' => 'Close',
    ],

    'pages' => [
        'circuits' => [
            'title' => 'Fuse Monitor',
            'subheading_only' => 'Monitoring :service, the only circuit declared in config/fuse.php.',
            'subheading_of_many' => 'Monitoring :service. :count other circuit is declared in config/fuse.php; this package monitors one.|Monitoring :service. :count other circuits are declared in config/fuse.php; this package monitors one.',
            'subheading_fallback' => 'Monitoring :service. FILAMENT_FUSE_CIRCUIT is set to ":configured", which is not declared in config/fuse.php.',
        ],
        'circuit' => [
            'title' => 'Circuit :service',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit state
    |--------------------------------------------------------------------------
    */

    'state' => [
        'closed' => 'Closed',
        'open' => 'Open',
        'half_open' => 'Half-open',
        'unknown' => 'Unknown',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard tiles
    |--------------------------------------------------------------------------
    */

    'tile' => [
        'open_for' => 'Open for :duration',
        'half_open_for' => 'Half-open for :duration',
        'state_unknown' => 'State unreadable',
        'last_good' => 'Last worked',
        'last_good_now' => 'now',
        'last_good_never' => 'never',
        'next_probe' => 'Next probe',
        'next_probe_in' => 'in :duration',
        // The cooldown passed at :time. Fuse admits the next job for the circuit as the probe.
        'next_probe_due' => 'due :time',
        'next_probe_due_untimed' => 'due',
        'probe_in_flight' => 'in flight',
        'none' => '—',
    ],

    /*
    |--------------------------------------------------------------------------
    | Live strip — "live, updated 14:06:11, every 5s"
    |--------------------------------------------------------------------------
    */

    'live' => [
        'live' => 'Live',
        'polling' => 'every :seconds s',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metric cards
    |--------------------------------------------------------------------------
    */

    'metric' => [
        'attempts' => 'Attempts',
        'attempts_caption' => 'in current window',
        'failures' => 'Failures',
        'failures_caption' => 'in current window',
        'failure_rate' => 'Failure rate',
        'failure_rate_caption' => 'threshold: :value%',
        'min_requests' => 'Min requests',
        'min_requests_caption' => 'before evaluation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Topbar indicator
    |--------------------------------------------------------------------------
    */

    'indicator' => [
        'ok' => 'All closed',
        'down' => ':count open',
        'aria' => 'Circuit breaker status',
    ],

    /*
    |--------------------------------------------------------------------------
    | Empty and error states
    |--------------------------------------------------------------------------
    */

    'empty' => [
        'circuits' => [
            'heading' => 'No circuits are being monitored',
            'body' => 'Circuits come from the services array in config/fuse.php.',
        ],
    ],

    'error' => [
        'unavailable' => [
            'heading' => 'Circuit state could not be read',
            'body' => 'Fuse stores state in the cache. Check the cache connection, then retry.',
        ],
    ],

    'updated_at' => 'Updated :time',

    /*
    |--------------------------------------------------------------------------
    | Duration units
    |--------------------------------------------------------------------------
    |
    | Durations are assembled server-side and handed to the views as a finished
    | :duration string, so a translator changes these suffixes rather than being
    | asked to reassemble a sentence from parts.
    |
    */

    'duration' => [
        'days' => ':valued',
        'hours' => ':valueh',
        'minutes' => ':valuem',
        'seconds' => ':values',
    ],

];
