<?php

namespace Tests\Feature;

use App\Models\OutboxEntry;
use App\NativeComponents\ListDetail;
use App\Services\Offline\LocalStore;
use App\Services\Offline\Outbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * La pantalla de una lista sin señal.
 *
 * El bug que motivó estos tests: con el tilde encolado la pantalla no cambiaba
 * nada, porque `load()` seguía yendo a la red y el push mandaba rutas que el
 * servidor no reconocía.
 */
class ListDetailOfflineTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    /**
     * Sin red de verdad: cualquier request falla como falla un teléfono sin
     * cobertura.
     */
    private function noSignal(): void
    {
        Http::fake(fn () => throw new ConnectionException('sin red'));
    }

    private function seedMirror(bool $withItem = true): void
    {
        $store = app(LocalStore::class);

        $store->put(LocalStore::LISTS, [
            'id' => 'list-1',
            'name' => 'Compra semanal',
            'household_id' => null,
            'updated_at' => '2026-11-20T10:00:00Z',
        ]);

        if (! $withItem) {
            return;
        }

        $store->put(LocalStore::LIST_ITEMS, [
            'id' => 'item-1',
            'list_id' => 'list-1',
            'product_id' => 'product-1',
            'custom_name' => null,
            'quantity' => 2,
            'unit' => 'un',
            'is_checked' => false,
            'notes' => null,
            'icon' => 'milk',
            'image_url' => null,
            'store_id' => null,
            'category_id' => null,
            'updated_at' => '2026-11-20T10:00:00Z',
            'product' => ['id' => 'product-1', 'name' => 'Leche', 'icon' => 'milk'],
        ]);
    }

    public function test_without_signal_the_list_comes_from_the_mirror(): void
    {
        $this->connect();
        $this->seedMirror();
        $this->noSignal();

        $component = Native::test(ListDetail::class, ['list' => 'list-1']);

        $component->assertSet('name', 'Compra semanal')
            ->assertSet('offline', true)
            ->assertSet('error', null)
            ->assertSee('Leche');

        $pending = $component->get('pending');

        $this->assertCount(1, $pending);
        $this->assertSame('item-1', $pending[0]['id']);
        $this->assertSame('Leche', $pending[0]['name']);
    }

    public function test_adding_a_product_without_signal_shows_it_and_queues_it(): void
    {
        $this->connect();
        $this->seedMirror(withItem: false);
        $this->noSignal();

        $component = Native::test(ListDetail::class, ['list' => 'list-1']);

        $component->call('addProduct', 'product-1', 'Leche', 'milk');

        // Se ve ya, con su nombre, sin esperar al servidor.
        $pending = $component->get('pending');

        $this->assertCount(1, $pending);
        $this->assertSame('Leche', $pending[0]['name']);
        $this->assertFalse($pending[0]['checked']);
        $component->assertSet('offline', true);

        // Y queda encolado para cuando vuelva la señal.
        $this->assertSame(1, app(Outbox::class)->pending());
    }

    public function test_the_queued_add_carries_a_client_id_and_an_api_relative_path(): void
    {
        $this->connect();
        $this->seedMirror(withItem: false);
        $this->noSignal();

        Native::test(ListDetail::class, ['list' => 'list-1'])->call('addProduct', 'product-1', 'Leche', 'milk');

        $row = OutboxEntry::query()->firstOrFail();

        $this->assertSame('post', $row->op);
        $this->assertSame('/v1/lists/list-1/items', $row->path);
        $this->assertSame($row->entity_id, $row->body()['id'] ?? null);
        $this->assertSame('product-1', $row->body()['product_id'] ?? null);
    }

    public function test_a_quick_create_without_signal_is_queued_instead_of_failing(): void
    {
        $this->connect();
        $this->seedMirror(withItem: false);
        $this->noSignal();

        $component = Native::test(ListDetail::class, ['list' => 'list-1']);

        $component->set('search', 'Yerba');
        $component->call('createAndAdd');

        $row = OutboxEntry::query()->firstOrFail();

        $this->assertSame('/v1/lists/list-1/items/quick-create', $row->path);
        $this->assertSame('Yerba', $row->body()['product']['name'] ?? null);

        // `quick-create` no acepta id de cliente: no hay fila optimista, y la
        // pantalla no muestra un error por eso.
        $component->assertSet('offline', true)->assertSet('error', null);
    }

    public function test_with_signal_nothing_is_queued(): void
    {
        $this->connect();

        Http::fake([
            '*/api/v1/sync/changes*' => Http::response(['data' => [
                'cursor' => 'c1', 'has_more' => false,
                'changes' => ['lists' => [], 'list_items' => []],
                'deleted' => ['lists' => [], 'list_items' => [], 'economy_transactions' => []],
            ]]),
            '*/api/v1/lists/list-1/items' => Http::response(['data' => [
                'id' => 'srv-1', 'list_id' => 'list-1', 'product_id' => 'product-1',
                'quantity' => 1, 'is_checked' => false,
            ]], 201),
            '*/api/v1/lists/list-1' => Http::response(['data' => [
                'id' => 'list-1', 'name' => 'Compra semanal', 'household_id' => null, 'items' => [],
            ]]),
            '*/api/v1/*' => Http::response(['data' => []]),
        ]);

        Native::test(ListDetail::class, ['list' => 'list-1'])->call('addProduct', 'product-1', 'Leche', 'milk');

        $this->assertSame(0, app(Outbox::class)->pending());
    }

    public function test_the_outbox_always_speaks_the_push_contract(): void
    {
        $outbox = app(Outbox::class);

        // Las pantallas encolan rutas relativas al cliente (`.../api/v1` + path),
        // pero el push espera `/v1/...` y el servidor le antepone `/api`.
        $outbox->enqueue('put', '/lists/l1/items/i1', []);
        $outbox->enqueue('put', '/v1/lists/l1/items/i2', []);

        $paths = OutboxEntry::query()->orderBy('id')->pluck('path')->all();

        $this->assertSame(['/v1/lists/l1/items/i1', '/v1/lists/l1/items/i2'], $paths);
    }
}
