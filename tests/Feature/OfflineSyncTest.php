<?php

namespace Tests\Feature;

use App\Exceptions\HomesideException;
use App\Models\OutboxEntry;
use App\Services\Offline\LocalStore;
use App\Services\Offline\Outbox;
use App\Services\Offline\SyncEngine;
use App\Services\Offline\SyncState;
use App\Services\Offline\WriteThrough;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
