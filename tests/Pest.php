<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Harris21\Fuse\Enums\CircuitState;
use Illuminate\Support\Facades\Blade;
use KonspyracyLabs\FilamentFuse\Data\CircuitSnapshot;
use KonspyracyLabs\FilamentFuse\FilamentFusePlugin;
use KonspyracyLabs\FilamentFuse\FilamentFuseServiceProvider;
use KonspyracyLabs\FilamentFuse\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Render a component tag with the given data.
 *
 * Components are exercised through their Blade tag rather than by constructing the
 * class, so the tag name, the container resolution and the template are all covered
 * along with the logic.
 *
 * @param  array<string, mixed>  $data
 */
function render(string $template, array $data = []): string
{
    return Blade::render($template, $data);
}

/**
 * Register a second panel carrying the given plugin and make it the current one, so
 * that FilamentFusePlugin::resolve() — and everything built on it — reads from that
 * plugin rather than from the default test panel.
 */
function panelWith(FilamentFusePlugin $plugin): Panel
{
    $panel = Panel::make()->id('configured')->path('configured')->plugin($plugin);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    return $panel;
}

/**
 * A circuit snapshot with sensible defaults, for the many tests that care about only
 * one or two of its fields.
 */
function snapshot(CircuitState $state = CircuitState::Closed, mixed ...$overrides): CircuitSnapshot
{
    // One spread rather than named arguments followed by one: PHP forbids unpacking
    // after a named argument.
    return new CircuitSnapshot(...['service' => 'stripe', 'state' => $state, ...$overrides]);
}

/**
 * Run the provider's testing-circuit registration against whatever config is set
 * right now.
 *
 * A real request does this once, during boot, before anything else runs. A test that
 * changes filament-fuse.testing_circuit.* after the application has already booted —
 * which is every test, since config()->set() in a test body runs after setUp() — needs
 * a way to make that config take effect without rebooting the whole application.
 */
function registerTestingCircuit(): void
{
    (new FilamentFuseServiceProvider(app()))->registerTestingCircuit();
}
