<?php

use Harris21\Fuse\CircuitBreaker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use KonspyracyLabs\FilamentFuse\Filament\Pages\CircuitDetail;
use KonspyracyLabs\FilamentFuse\Filament\Pages\Circuits;
use KonspyracyLabs\FilamentFuse\Support\CircuitInspector;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('filament-fuse.enabled', true);
    config()->set('fuse.services', ['stripe' => [], 'sendgrid' => []]);
});

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

it('opens to any panel user when the host defines no gate', function (): void {
    // Filament has already required an authenticated panel user by this point, and
    // these pages only read live circuit state — a second authorisation layer nobody
    // asked for would just be in the way.
    expect(Circuits::canAccess())->toBeTrue();
});

it('hands the decision to the host gate once one exists', function (): void {
    Gate::define(Circuits::GATE, fn (?object $user): bool => false);

    expect(Circuits::canAccess())->toBeFalse()
        ->and(Circuits::shouldRegisterNavigation())->toBeFalse();
});

it('stays shut while the package is disabled', function (): void {
    config()->set('filament-fuse.enabled', false);

    expect(Circuits::canAccess())->toBeFalse()
        // No menu item either: one that 403s is worse than none at all.
        ->and(Circuits::shouldRegisterNavigation())->toBeFalse();
});

it('never puts the circuit detail page in the navigation', function (): void {
    // Filament builds a URL for every navigation item, and this route has no
    // meaningful one without a service name — it would throw while rendering.
    expect(CircuitDetail::shouldRegisterNavigation())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

it('renders the monitored circuit', function (): void {
    Livewire::test(Circuits::class)
        ->assertOk()
        ->assertSee('stripe')
        ->assertDontSee('sendgrid')
        // The tile itself, not just the name: a component that silently renders
        // nothing still leaves the surrounding page looking fine.
        ->assertSee('fuse-tile', escape: false)
        ->assertSee(__('filament-fuse::filament-fuse.tile.last_good'));
});

it('renders the live strip and the two sections', function (): void {
    Livewire::test(Circuits::class)
        ->assertOk()
        ->assertSee('fuse-dot--live', escape: false)
        ->assertSee(__('filament-fuse::filament-fuse.dashboard.open'))
        ->assertSee(__('filament-fuse::filament-fuse.dashboard.closed'))
        ->assertSee('fuse-divider', escape: false);
});

it('explains the three states behind the (i)', function (): void {
    // Fuse's words are the only ones the panel uses, and they are not self-explanatory.
    // Filament v5 renders a mounted modal as a Livewire partial, not in the page HTML
    // the usual assertSee() reads, so the assertion looks at the partial itself.
    $page = Livewire::test(Circuits::class)
        ->assertActionExists('about')
        ->mountAction('about');

    $modal = $page->effects['partials']['action-modals'] ?? '';

    expect($modal)
        ->toContain(e(__('filament-fuse::filament-fuse.glossary.heading')))
        ->toContain(e(__('filament-fuse::filament-fuse.glossary.half_open_body')))
        ->toContain('fuse-glossary__term--half-open');
});

it('speaks Fuse, not its own dialect', function (): void {
    // A breaker is open, closed or half-open — never "down", "healthy" or "probing".
    (new CircuitBreaker('stripe'))->forceOpen();

    // The duration itself depends on Fuse's clock; the word in front of it is the point.
    Livewire::test(Circuits::class)
        ->assertSee(Str::before(__('filament-fuse::filament-fuse.tile.open_for'), ':duration'))
        ->assertDontSee('Down for')
        ->assertDontSee('Healthy for')
        ->assertDontSee('Probing for');
});

it('says so plainly when nothing is configured', function (): void {
    config()->set('fuse.services', []);

    Livewire::test(Circuits::class)
        ->assertOk()
        ->assertSee(__('filament-fuse::filament-fuse.empty.circuits.heading'))
        // The empty state has to teach the one thing the user needs to do next.
        ->assertSee('config/fuse.php');
});

it('moves the circuit into the top section while it is down', function (): void {
    (new CircuitBreaker('stripe'))->forceOpen();

    $page = Livewire::test(Circuits::class)->assertOk();

    expect($page->viewData('down')->pluck('service')->all())->toBe(['stripe'])
        ->and($page->viewData('healthy'))->toBeEmpty();

    $page->assertDontSee(__('filament-fuse::filament-fuse.dashboard.none_open'));
});

it('says plainly that nothing is down while the circuit is healthy', function (): void {
    $page = Livewire::test(Circuits::class)
        ->assertOk()
        ->assertSee(__('filament-fuse::filament-fuse.dashboard.none_open'));

    expect($page->viewData('down'))->toBeEmpty()
        ->and($page->viewData('healthy')->count())->toBe(1);
});

it('badges the navigation only while something is down', function (): void {
    expect(Circuits::getNavigationBadge())->toBeNull();

    (new CircuitBreaker('stripe'))->forceOpen();

    // The count is memoised for the request; this is the next request.
    app(CircuitInspector::class)->flush();

    expect(Circuits::getNavigationBadge())->toBe('1');
});

/*
|--------------------------------------------------------------------------
| Subheading
|--------------------------------------------------------------------------
*/

it('names the only circuit declared', function (): void {
    config()->set('fuse.services', ['stripe' => []]);

    Livewire::test(Circuits::class)
        ->assertSee(__('filament-fuse::filament-fuse.pages.circuits.subheading_only', ['service' => 'stripe']));
});

it('quietly mentions how many other circuits config/fuse.php declares', function (): void {
    Livewire::test(Circuits::class)
        ->assertSee(trans_choice(
            'filament-fuse::filament-fuse.pages.circuits.subheading_of_many',
            1,
            ['service' => 'stripe', 'count' => 1],
        ));
});

it('says when the configured circuit is not declared, and still shows the fallback', function (): void {
    config()->set('filament-fuse.circuit', 'not-a-circuit');

    Livewire::test(Circuits::class)
        ->assertSee(__('filament-fuse::filament-fuse.pages.circuits.subheading_fallback', [
            'service' => 'stripe',
            'configured' => 'not-a-circuit',
        ]));
});

it('shows no subheading when nothing is configured at all', function (): void {
    config()->set('fuse.services', []);

    expect((new Circuits)->getSubheading())->toBeNull();
});

it('keeps the subheading when the testing circuit\'s name collides with a real one', function (): void {
    // A real circuit named the same as the testing circuit must go on being treated
    // as an ordinary circuit — including keeping its subheading, which disappeared
    // when it was mistaken for the testing one instead.
    config()->set('fuse.services', ['stripe' => []]);
    config()->set('filament-fuse.testing_circuit.name', 'stripe');
    registerTestingCircuit();

    Livewire::test(Circuits::class)
        ->assertSee(__('filament-fuse::filament-fuse.pages.circuits.subheading_only', ['service' => 'stripe']));
});

/*
|--------------------------------------------------------------------------
| Circuit detail
|--------------------------------------------------------------------------
*/

it('refuses a circuit that is not monitored', function (): void {
    // The route parameter is attacker-controlled and flows into a cache key; refusing
    // anything not already monitored keeps that surface closed.
    Livewire::test(CircuitDetail::class, ['service' => 'not-a-circuit'])
        ->assertStatus(404);
});

it('refuses a circuit that is declared but not the one monitored', function (): void {
    // 'sendgrid' is real, but this package watches one circuit only.
    Livewire::test(CircuitDetail::class, ['service' => 'sendgrid'])
        ->assertStatus(404);
});

it('accepts the route parameter but locks $service against a client set', function (): void {
    // mount()'s isMonitored() guard runs once. Without #[Locked], a client could then
    // `$set('service', …)` past it to any string — monitored or not — and every
    // downstream cache key this page builds would follow.
    $c = Livewire::test(CircuitDetail::class, ['service' => 'stripe']);

    expect($c->get('service'))->toBe('stripe');
    $c->assertOk();

    expect(fn () => $c->set('service', 'not-a-circuit'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('renders the live hero with the four metric cards', function (): void {
    // A component that renders nothing at all leaves a page that still looks
    // plausible, because everything around it is unaffected.
    Livewire::test(CircuitDetail::class, ['service' => 'stripe'])
        ->assertOk()
        ->assertSee('fuse-orb', escape: false)
        ->assertSee(__('filament-fuse::filament-fuse.metric.attempts'))
        ->assertSee(__('filament-fuse::filament-fuse.metric.failures'))
        ->assertSee(__('filament-fuse::filament-fuse.metric.failure_rate'))
        ->assertSee(__('filament-fuse::filament-fuse.metric.min_requests'));
});

it('renders no table on the detail page', function (): void {
    // The detail page is the hero and its metrics. Nothing is stored behind it and
    // nothing paginates, so a table here would mean something had started being kept.
    Livewire::test(CircuitDetail::class, ['service' => 'stripe'])
        ->assertOk()
        ->assertDontSeeHtml('<table');
});
