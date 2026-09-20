<?php

namespace KonspyracyLabs\FilamentFuse\Filament\Pages;

use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;
use KonspyracyLabs\FilamentFuse\FilamentFuseServiceProvider;
use KonspyracyLabs\FilamentFuse\Support\CircuitInspector;
use RuntimeException;

/**
 * The dashboard: the monitored circuit, live, in two sections.
 *
 * What is down sits at the top under its own heading, everything healthy below a rule.
 * The split is the whole answer to "is anything wrong?" — a heading with a count, read
 * from across the room — so there is no banner restating it.
 *
 * A page rather than a resource, because circuits are not records. The registry is
 * `config('fuse.services')` read live and the state behind each name lives in Fuse's
 * cache, so there is no model to attach a resource to and nothing to paginate.
 */
class Circuits extends FusePage
{
    protected string $view = 'filament-fuse::pages.circuits';

    public static function getNavigationLabel(): string
    {
        return __('filament-fuse::filament-fuse.nav.circuits');
    }

    public function getTitle(): string
    {
        return __('filament-fuse::filament-fuse.pages.circuits.title');
    }

    /**
     * One line naming the circuit this dashboard shows, and — quietly — how many others
     * config/fuse.php declares that this package is not showing.
     */
    public function getSubheading(): ?string
    {
        $fuse = $this->fuse();
        $service = $fuse->chosenCircuit();

        if ($service === null) {
            // The empty state already says everything there is to say.
            return null;
        }

        $missing = $fuse->missingCircuit();

        if ($missing !== null) {
            return __('filament-fuse::filament-fuse.pages.circuits.subheading_fallback', [
                'service' => $service,
                'configured' => $missing,
            ]);
        }

        $others = $fuse->unmonitoredCount();

        return $others === 0
            ? __('filament-fuse::filament-fuse.pages.circuits.subheading_only', ['service' => $service])
            : trans_choice('filament-fuse::filament-fuse.pages.circuits.subheading_of_many', $others, [
                'service' => $service,
                'count' => $others,
            ]);
    }

    /**
     * A badge on the navigation item, so an outage is visible without opening the page.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! static::plugin()->hasBadge()) {
            return null;
        }

        $down = app(CircuitInspector::class)->downCount();

        return $down > 0 ? (string) $down : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * The (i) beside the title: what open, closed and half-open mean.
     *
     * Fuse's vocabulary is the only one this panel uses, and it is not self-explanatory
     * to someone who has never read about circuit breakers. One click, four sentences.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('about')
                ->label(__('filament-fuse::filament-fuse.dashboard.about'))
                ->icon('heroicon-o-information-circle')
                ->iconButton()
                ->color('gray')
                ->modalHeading(__('filament-fuse::filament-fuse.glossary.heading'))
                ->modalContent(fn (): View => $this->glossary())
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament-fuse::filament-fuse.glossary.close')),
        ];
    }

    /**
     * The glossary shown behind the header's information button.
     *
     * The name is built from the package's view namespace rather than written out, and
     * checked before it is rendered: a static analyser only accepts a view name it can
     * prove exists, and a missing template should say so plainly rather than fail from
     * somewhere inside the view factory.
     */
    private function glossary(): View
    {
        $view = FilamentFuseServiceProvider::$viewNamespace.'::glossary';

        if (! view()->exists($view)) {
            throw new RuntimeException("View [{$view}] not found.");
        }

        return view()->make($view);
    }

    /**
     * Everything the template renders, resolved here rather than in Blade.
     *
     * Recomputed on each poll rather than cached: the point of the page is that it is
     * current, and a stale dashboard is worse than no dashboard.
     *
     * @return array{configured: bool, down: Collection<int, CircuitSnapshot>, healthy: Collection<int, CircuitSnapshot>, urls: array<string, string>, poll: string|null, hasUnreadable: bool}
     */
    protected function getViewData(): array
    {
        $circuits = $this->getCircuits();

        // An unreadable circuit is not an outage, but it is not reassurance either. It
        // belongs with the things that need looking at, not in the healthy section.
        $needsAttention = fn (CircuitSnapshot $c): bool => $c->isDown() || $c->isUnknown();

        return [
            'configured' => $circuits->isNotEmpty(),
            'down' => $circuits->filter($needsAttention)->values(),
            'healthy' => $circuits->reject($needsAttention)->values(),
            'urls' => $this->getDetailUrls($circuits),
            'poll' => $this->getPollingInterval(),
            'hasUnreadable' => $circuits->contains(fn (CircuitSnapshot $c): bool => $c->isUnknown()),
        ];
    }

    /**
     * Detail-page links, keyed by service name.
     *
     * @param  Collection<int, CircuitSnapshot>  $circuits
     * @return array<string, string>
     */
    protected function getDetailUrls(Collection $circuits): array
    {
        return $circuits
            ->mapWithKeys(fn (CircuitSnapshot $c): array => [
                $c->service => CircuitDetail::getUrl(['service' => $c->service]),
            ])
            ->all();
    }

    /**
     * Every monitored circuit, worst first.
     *
     * @return Collection<int, CircuitSnapshot>
     */
    public function getCircuits(): Collection
    {
        return $this->inspector()->all();
    }
}
