<?php

namespace KonspyracyLabs\FilamentFuse\View\Components;

use Illuminate\Support\Carbon;

/**
 * The "live, updated 14:06:11, every 5s" strip above the dashboard.
 *
 * Says out loud how fresh the numbers are. A monitoring page that looks identical
 * whether it is polling or has quietly stopped is worse than one that never claimed to
 * be live in the first place.
 */
class LiveStrip extends FuseComponent
{
    public string $live;

    public string $updated;

    public ?string $cadence;

    /**
     * @param  string|null  $poll  Livewire interval such as "5s", or null when not polling.
     */
    public function __construct(?string $poll = null)
    {
        $this->live = $this->label('live.live');
        $this->updated = $this->label('updated_at', ['time' => $this->time(Carbon::now())]);

        $this->cadence = $poll === null
            ? null
            : $this->label('live.polling', ['seconds' => (int) $poll]);
    }
}
