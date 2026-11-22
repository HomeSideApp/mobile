<?php

namespace Tests\Feature;

use App\Exceptions\HomesideException;
use App\Models\MirrorEntity;
use App\Models\OutboxEntry;
use App\Services\Offline\LocalStore;
use App\Services\Offline\Outbox;
use App\Services\Offline\PendingImports;
use App\Services\Offline\SyncEngine;
use App\Services\Offline\SyncState;
use App\Services\Offline\WriteThrough;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * Motor offline: delta con cursor, tombstones y outbox.
 */
class OfflineSyncTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function page(array $overrides = []): array
    {
        return ['data' => [
            'cursor' => 'cursor-1',
            'has_more' => false,
            'changes' => [
                'lists' => [
                    ['id' => 'list-1', 'name' => 'Compra', 'household_id' => null, 'updated_at' => '2026-11-20T10:00:00Z'],
                ],
            ],
            'deleted' => ['lists' => [], 'list_items' => [], 'economy_transactions' => []],
            ...$overrides,
        ]];
    }

    public function test_pull_stores_the_page_and_the_cursor(): void
    {
        $this->connect();
        Http::fake(['*/api/v1/sync/changes*' => Http::response($this->page())]);

        $applied = app(SyncEngine::class)->pull();

        $this->assertSame(1, $applied);
        $this->assertSame('Compra', app(LocalStore::class)->find(LocalStore::LISTS, 'list-1')['name'] ?? null);
        $this->assertSame('cursor-1', app(SyncState::class)->cursor());
    }

    public function test_pull_applies_tombstones(): void
    {
        $this->connect();

        app(LocalStore::class)->put(LocalStore::LISTS, [
            'id' => 'list-1', 'name' => 'Compra', 'updated_at' => '2026-11-20T10:00:00Z',
        ]);

        Http::fake(['*/api/v1/sync/changes*' => Http::response($this->page([
            'changes' => ['lists' => []],
            'deleted' => ['lists' => ['list-1'], 'list_items' => [], 'economy_transactions' => []],
        ]))]);

        app(SyncEngine::class)->pull();

        // El borrado gana: la fila sigue en la base pero no se lista.
        $this->assertNull(app(LocalStore::class)->find(LocalStore::LISTS, 'list-1'));
        $this->assertSame([], app(LocalStore::class)->all(LocalStore::LISTS));
    }

    public function test_pull_follows_the_cursor_across_pages(): void
    {
        $this->connect();

        Http::fake([
            '*/api/v1/sync/changes?limit=500' => Http::response($this->page([
                'cursor' => 'cursor-1',
                'has_more' => true,
            ])),
            '*/api/v1/sync/changes?since=cursor-1&limit=500' => Http::response(['data' => [
                'cursor' => 'cursor-2',
                'has_more' => false,
                'changes' => ['lists' => [
                    ['id' => 'list-2', 'name' => 'Limpieza', 'household_id' => null, 'updated_at' => '2026-11-20T11:00:00Z'],
                ]],
                'deleted' => [],
            ]]),
        ]);

        app(SyncEngine::class)->pull();

        $this->assertNotNull(app(LocalStore::class)->find(LocalStore::LISTS, 'list-1'));
        $this->assertNotNull(app(LocalStore::class)->find(LocalStore::LISTS, 'list-2'));
        $this->assertSame('cursor-2', app(SyncState::class)->cursor());
    }

    public function test_push_drains_the_outbox_in_order_and_clears_applied_entries(): void
    {
        $this->connect();

        $outbox = app(Outbox::class);
        $first = $outbox->enqueue('put', '/lists/list-1/items/item-1', ['is_checked' => true], LocalStore::LIST_ITEMS, 'item-1');
        $second = $outbox->enqueue('delete', '/lists/list-1/items/item-2', [], LocalStore::LIST_ITEMS, 'item-2');

        Http::fake(['*/api/v1/sync/push' => Http::response(['data' => ['results' => [
            ['op_id' => (string) $first->id, 'status' => 'applied', 'status_code' => 200, 'body' => [
                'data' => ['id' => 'item-1', 'list_id' => 'list-1', 'is_checked' => true],
            ]],
            ['op_id' => (string) $second->id, 'status' => 'applied', 'status_code' => 204, 'body' => []],
        ]]])]);

        $result = app(SyncEngine::class)->push();

        $this->assertSame(2, $result['applied']);
        $this->assertSame(0, OutboxEntry::query()->count());

        // El resultado se refleja en el espejo sin esperar al delta.
        $this->assertTrue((bool) (app(LocalStore::class)->find(LocalStore::LIST_ITEMS, 'item-1')['is_checked'] ?? false));
    }

    public function test_a_rejected_operation_is_kept_and_not_retried_in_a_loop(): void
    {
        $this->connect();

        $entry = app(Outbox::class)->enqueue('put', '/lists/l/items/i', ['is_checked' => true], LocalStore::LIST_ITEMS, 'i');

        Http::fake(['*/api/v1/sync/push' => Http::response(['data' => ['results' => [
            ['op_id' => (string) $entry->id, 'status' => 'rejected', 'status_code' => 422, 'body' => ['message' => 'no']],
        ]]])]);

        app(SyncEngine::class)->push();

        $entry->refresh();

        $this->assertSame('failed', $entry->state);
        // Un rechazo es del usuario/servidor, no de la red: no se reintenta solo.
        $this->assertSame(0, app(Outbox::class)->pending());
    }

    public function test_a_ticket_captured_offline_is_queued_and_flushed_later(): void
    {
        $this->connect();

        $imports = app(PendingImports::class);
        $imports->enqueue(str_repeat('x', 256), 'ticket.jpg', 'household-1');

        $this->assertSame(1, $imports->count());

        // Un solo fake controlado por bandera: `Http::fake()` acumula stubs y el
        // primero que matchea gana, así que dos llamadas no cambian el escenario.
        $offline = true;

        Http::fake(function () use (&$offline) {
            if ($offline) {
                throw new ConnectionException('sin red');
            }

            return Http::response(['data' => ['id' => 'import-1', 'status' => 'queued']], 201);
        });

        // Sin red: se queda en la cola y no se reintenta en vano.
        $this->assertSame(0, $imports->flush());
        $this->assertSame(1, $imports->count());

        // Con red: se sube y la foto se borra del disco.
        $offline = false;

        $this->assertSame(1, $imports->flush());
        $this->assertSame(0, $imports->count());
    }

    public function test_old_tombstones_are_pruned_but_live_rows_are_kept(): void
    {
        $this->connect();

        app(LocalStore::class)->put(LocalStore::LISTS, ['id' => 'viva', 'name' => 'Compra']);

        MirrorEntity::query()->create([
            'entity' => LocalStore::LISTS,
            'id' => 'vieja',
            'payload' => '{"id":"vieja"}',
            'updated_at' => null,
            'deleted_at' => now()->subDays(60)->toIso8601String(),
            'fetched_at' => now()->toIso8601String(),
        ]);

        Http::fake(['*/api/v1/sync/changes*' => Http::response($this->page([
            'changes' => ['lists' => []],
        ]))]);

        app(SyncEngine::class)->run();

        // El tombstone viejo se va; la copia viva se queda: es lo único que el
        // usuario tiene si no hay red.
        $this->assertFalse(MirrorEntity::query()->where('id', 'vieja')->exists());
        $this->assertTrue(MirrorEntity::query()->where('id', 'viva')->exists());
    }

    public function test_a_failed_push_does_not_block_the_pull(): void
    {
        $this->connect();

        // Un pendiente trabado hacía que `run()` cortara antes de bajar cambios.
        // Resultado: una lista borrada en el servidor seguía en el espejo.
        app(Outbox::class)->enqueue('put', '/lists/list-1', ['name' => 'x']);

        $calls = 0;

        Http::fake(function () use (&$calls) {
            $calls++;

            // Primero el push: se cae la conexión.
            if ($calls === 1) {
                throw new ConnectionException('sin red');
            }

            return Http::response($this->page([
                'changes' => ['lists' => []],
                'deleted' => ['lists' => ['list-1'], 'list_items' => [], 'economy_transactions' => []],
            ]));
        });

        app(LocalStore::class)->put(LocalStore::LISTS, ['id' => 'list-1', 'name' => 'Compra']);

        $result = app(SyncEngine::class)->run();

        $this->assertTrue($result['offline'], 'El push falló, así que seguimos sin red.');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sync/changes'));

        $this->assertNull(
            app(LocalStore::class)->find(LocalStore::LISTS, 'list-1'),
            'El borrado del servidor tiene que llegar al espejo aunque el push haya fallado.',
        );
    }

    public function test_the_cleanup_migration_drops_only_the_legacy_paths(): void
    {
        $outbox = app(Outbox::class);

        // Antes de la normalización se guardaba la ruta del cliente HTTP.
        OutboxEntry::query()->create([
            'op' => 'put',
            'path' => '/lists/list-1/items/item-1',
            'payload' => '{}',
            'idempotency_key' => 'legacy',
            'entity' => LocalStore::LIST_ITEMS,
            'entity_id' => 'item-1',
            'attempts' => 4,
            'last_error' => 'route not found',
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
        ]);

        $outbox->enqueue('put', '/lists/list-1/items/item-2', []);

        (require database_path('migrations/2026_11_22_090000_discard_outbox_entries_with_client_relative_paths.php'))->up();

        $this->assertSame(['/v1/lists/list-1/items/item-2'], OutboxEntry::query()->pluck('path')->all());
    }

    public function test_write_through_enqueues_only_on_transport_failure(): void
    {
        $this->connect();

        $enqueued = app(WriteThrough::class)->run(
            'put',
            '/lists/l/items/i',
            ['is_checked' => true],
            fn () => throw HomesideException::network('sin red'),
            LocalStore::LIST_ITEMS,
            'i',
        );

        $this->assertFalse($enqueued);
        $this->assertSame(1, app(Outbox::class)->pending());

        $this->expectException(HomesideException::class);

        app(WriteThrough::class)->run(
            'put',
            '/lists/l/items/i',
            ['is_checked' => true],
            fn () => throw HomesideException::validation(['is_checked' => ['inválido']]),
        );
    }
}
