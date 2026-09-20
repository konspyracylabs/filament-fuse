<?php

use Harris21\Fuse\CircuitBreaker;
use Harris21\Fuse\Enums\CircuitState;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Cache;
use KonspyracyLabs\FilamentFuse\Filament\Pages\Circuits;
use KonspyracyLabs\FilamentFuse\Support\CircuitInspector;
use Livewire\Livewire;

/**
 * Fuse keeps circuit state in the cache, and this package reads nothing else. A circuit
 * trips precisely when infrastructure is unhappy, so the cache being unreachable is not
 * an edge case here — it is the scenario the package exists for. Every read must degrade
 * to an honest answer rather than an exception.
 *
 * The store below is a real Store the real Repository wraps; only the driver is broken.
 */
beforeEach(function (): void {
    config()->set('filament-fuse.enabled', true);
    config()->set('fuse.services', ['stripe' => []]);

    Cache::extend('broken', fn (): Repository => Cache::repository(new class implements Store
    {
        private function down(): never
        {
            throw new RuntimeException('cache unreachable');
        }

        public function get($key): mixed
        {
            $this->down();
        }

        public function many(array $keys): array
        {
            $this->down();
        }

        public function put($key, $value, $seconds): bool
        {
            $this->down();
        }

        public function putMany(array $values, $seconds): bool
        {
            $this->down();
        }

        public function increment($key, $value = 1): int|bool
        {
            $this->down();
        }

        public function decrement($key, $value = 1): int|bool
        {
            $this->down();
        }

        public function forever($key, $value): bool
        {
            $this->down();
        }

        public function touch($key, $seconds): bool
        {
            $this->down();
        }

        public function forget($key): bool
        {
            $this->down();
        }

        public function flush(): bool
        {
            $this->down();
        }

        public function getPrefix(): string
        {
            return '';
        }
    }));

    config()->set('cache.stores.broken', ['driver' => 'broken']);
    config()->set('cache.default', 'broken');
});

it('reports a circuit as unreadable rather than healthy', function (): void {
    // A monitoring tool that renders an unreachable cache as green has told the operator
    // a lie at exactly the wrong moment.
    $snapshot = app(CircuitInspector::class)->snapshot('stripe');

    expect($snapshot->isUnknown())->toBeTrue()
        ->and($snapshot->isClosed())->toBeFalse()
        ->and($snapshot->stateKey())->toBe('unknown');
});

it('sorts unreadable circuits above healthy ones and counts none of them as down', function (): void {
    $inspector = app(CircuitInspector::class);

    expect($inspector->all()->every(fn ($s): bool => $s->isUnknown()))->toBeTrue()
        // Unknown is not an outage. The indicator must not go red for a cache blip.
        ->and($inspector->downCount())->toBe(0);
});

it('shows the operator that it cannot see, on the dashboard', function (): void {
    Livewire::test(Circuits::class)
        ->assertOk()
        ->assertSee(__('filament-fuse::filament-fuse.error.unavailable.heading'))
        ->assertSee('fuse-dot--unknown', escape: false)
        // Unreadable is not healthy: it sits in the top section, and "nothing is down"
        // is not claimed.
        ->assertDontSee(__('filament-fuse::filament-fuse.dashboard.none_open'));
});

it('is the cache and not this package that is broken', function (): void {
    // Sanity: the store really does throw, so the tests above prove degradation and not
    // a store that quietly returns null.
    expect(fn (): CircuitState => (new CircuitBreaker('stripe'))->getState())->toThrow(RuntimeException::class);
});

it('reports a broken cache once per circuit rather than once per read', function (): void {
    // The dashboard reads a circuit's stats and, separately, its state — and with the
    // testing circuit on there are two distinct circuits here. Without the memo, one
    // broken cache produces four reports for a poll that changed nothing; a real
    // outage would flood the tracker with duplicates of the same failure.
    config()->set('filament-fuse.testing_circuit.enabled', true);

    $handler = new class implements ExceptionHandler
    {
        /** @var list<Throwable> */
        public array $reported = [];

        public function report(Throwable $e): void
        {
            $this->reported[] = $e;
        }

        public function shouldReport(Throwable $e): bool
        {
            return true;
        }

        public function render($request, Throwable $e): void {}

        public function renderForConsole($output, Throwable $e): void {}
    };

    app()->instance(ExceptionHandler::class, $handler);

    $inspector = app(CircuitInspector::class);
    $inspector->all();
    $inspector->downCount();

    expect($handler->reported)->toHaveCount(2);
});
