<?php

namespace Tests\Feature;

use App\Models\OutboxEntry;
use App\NativeComponents\ListDetail;
use App\Services\Offline\LocalStore;
use App\Services\Offline\Outbox;
use App\Services\Offline\SyncEngine;
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

    public function test_checking_an_item_without_signal_shows_it_and_queues_one_write(): void
    {
        $this->connect();
        $this->seedMirror();
        $this->noSignal();

        $component = Native::test(ListDetail::class, ['list' => 'list-1']);

        $component->call('toggle', 'item-1');

        // Se ve ya: el espejo quedó tildado, así que el ítem sale de pendientes.
        $this->assertTrue((bool) (app(LocalStore::class)->find(LocalStore::LIST_ITEMS, 'item-1')['is_checked'] ?? false));
        $this->assertSame([], $component->get('pending'));

        // Y queda una sola operación, con la versión del servidor intacta para
        // que el push pueda detectar escrituras viejas.
        $rows = OutboxEntry::query()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('/v1/lists/list-1/items/item-1', $rows[0]->path);
        $this->assertSame('2026-11-20T10:00:00Z', $rows[0]->body()['updated_at'] ?? null);
    }

    public function test_toggling_the_same_item_twice_offline_leaves_one_operation(): void
    {
        $this->connect();
        $this->seedMirror();
        $this->noSignal();

        $component = Native::test(ListDetail::class, ['list' => 'list-1']);

        $component->call('toggle', 'item-1');
        $component->call('toggle', 'item-1');

        $this->assertSame(1, app(Outbox::class)->pending(), 'Dos toques no pueden encolar dos escrituras.');
    }

    public function test_deleting_the_list_without_signal_removes_it_and_queues_the_delete(): void
    {
        $this->connect();
        $this->seedMirror(withItem: false);
        $this->noSignal();

        $component = Native::test(ListDetail::class, ['list' => 'list-1']);

        $component->call('requestDelete')->call('deleteList');

        // Se va del espejo al instante: la pantalla no puede seguir mostrando
        // una lista que el usuario ya confirmó borrar.
        $this->assertNull(app(LocalStore::class)->find(LocalStore::LISTS, 'list-1'));

        $row = OutboxEntry::query()->firstOrFail();

        $this->assertSame('delete', $row->op);
        $this->assertSame('/v1/lists/list-1', $row->path);
        $component->assertSet('listError', null);
    }

    public function test_a_rejected_delete_says_why_where_the_user_is_looking(): void
    {
        $this->connect();
        $this->seedMirror(withItem: false);

        Http::fake([
            '*/api/v1/sync/changes*' => Http::response(['data' => [
                'cursor' => 'c1', 'has_more' => false,
                'changes' => ['lists' => []], 'deleted' => ['lists' => []],
            ]]),
            '*/api/v1/lists/list-1' => Http::response(['message' => 'No podés borrar esta lista.'], 403),
        ]);

        $component = Native::test(ListDetail::class, ['list' => 'list-1']);

        $component->call('requestDelete')->call('deleteList');

        // El mensaje va en la hoja: el `error` de la pantalla vive al final del
        // scroll y desde la confirmación no se veía.
        $this->assertNotEmpty($component->get('listError'));
        $this->assertSame(0, app(Outbox::class)->pending(), 'Un rechazo no se encola.');
        $this->assertNotNull(app(LocalStore::class)->find(LocalStore::LISTS, 'list-1'));
    }

    public function test_the_catalog_is_usable_without_signal(): void
    {
        $this->connect();
        $this->seedMirror(withItem: false);

        $online = true;

        Http::fake(function ($request) use (&$online) {
            if (! $online) {
                throw new ConnectionException('sin red');
            }

            $url = $request->url();

            if (str_contains($url, '/categories')) {
                return Http::response(['data' => [
                    ['id' => 'cat-1', 'name' => 'Lácteos', 'color' => '#42A5F5'],
                ]]);
            }

            if (str_contains($url, '/products/recent')) {
                return Http::response(['data' => [
                    ['id' => 'product-1', 'name' => 'Leche', 'icon' => 'milk', 'category' => ['id' => 'cat-1', 'name' => 'Lácteos']],
                ]]);
            }

            if (str_contains($url, '/products')) {
                return Http::response(['data' => [
                    ['id' => 'product-1', 'name' => 'Leche', 'icon' => 'milk', 'category' => ['id' => 'cat-1', 'name' => 'Lácteos']],
                ]]);
            }

            return Http::response(['data' => []]);
        });

        // Primero con red: se cachea el catálogo.
        $component = Native::test(ListDetail::class, ['list' => 'list-1']);
        $component->assertSet('categories.0.name', 'Lácteos');

        // Y ahora sin red, en la misma pantalla y en una nueva.
        $online = false;

        $offline = Native::test(ListDetail::class, ['list' => 'list-1']);

        $offline->assertSet('categories.0.name', 'Lácteos')
            ->assertSet('error', null)
            ->call('toggleCategory', 'cat-1')
            ->assertSet('categories.0.loaded', true);

        $products = $offline->get('categories')[0]['products'] ?? [];

        $this->assertCount(1, $products);
        $this->assertSame('Leche', $products[0]['name']);
    }

    public function test_the_search_finds_products_from_the_mirror_without_signal(): void
    {
        $this->connect();
        $this->seedMirror(withItem: false);

        $online = true;

        Http::fake(function ($request) use (&$online) {
            if (! $online) {
                throw new ConnectionException('sin red');
            }

            if (str_contains($request->url(), '/products')) {
                return Http::response(['data' => [
                    ['id' => 'p-1', 'name' => 'Leche entera', 'icon' => 'milk', 'category' => ['id' => 'cat-1', 'name' => 'Lácteos']],
                    ['id' => 'p-2', 'name' => 'Pan de molde', 'icon' => 'bread', 'category' => ['id' => 'cat-2', 'name' => 'Panadería']],
                ]]);
            }

            return Http::response(['data' => []]);
        });

        // Con red: la búsqueda cachea lo que encuentra.
        $component = Native::test(ListDetail::class, ['list' => 'list-1']);
        $component->set('search', 'leche');
        $component->call('onSearch');

        // Sin red: se busca en lo guardado, sin error.
        $online = false;

        $offline = Native::test(ListDetail::class, ['list' => 'list-1']);
        $offline->set('search', 'leche');
        $offline->call('onSearch');

        $offline->assertSet('error', null)->assertSet('offline', true);

        $results = $offline->get('results');

        $this->assertCount(1, $results);
        $this->assertSame('Leche entera', $results[0]['name']);

        // Y una búsqueda sin coincidencias no rompe: queda vacía.
        $offline->set('search', 'zzz');
        $offline->call('onSearch');

        $this->assertSame([], $offline->get('results'));
    }

    public function test_a_new_product_offline_shows_up_and_is_replaced_by_the_real_one(): void
    {
        $this->connect();
        $this->seedMirror(withItem: false);

        // Un solo fake con bandera: `Http::fake()` acumula y la primera regla
        // (la que simula la caída) taparía a la del delta.
        $syncing = false;

        Http::fake(function ($request) use (&$syncing) {
            if (! $syncing) {
                throw new ConnectionException('sin red');
            }

            // El push necesita sus resultados; si no, el outbox nunca drena.
            if (str_contains($request->url(), '/sync/push')) {
                $operations = $request->data()['operations'] ?? [];

                return Http::response(['data' => ['results' => array_map(
                    fn (array $operation): array => [
                        'op_id' => $operation['op_id'],
                        'status' => 'applied',
                        'body' => [],
                    ],
                    $operations,
                )]]);
            }

            return Http::response(['data' => [
                'cursor' => 'c2',
                'has_more' => false,
                'changes' => ['list_items' => [[
                    'id' => 'real-1',
                    'list_id' => 'list-1',
                    'custom_name' => 'Yerba',
                    'quantity' => 1,
                    'is_checked' => false,
                    'updated_at' => '2026-11-23T10:00:00Z',
                    'product' => ['id' => 'p-1', 'name' => 'Yerba'],
                ]]],
                'deleted' => ['lists' => [], 'list_items' => [], 'economy_transactions' => []],
            ]]);
        });

        // Sin red: se escribe "Yerba", que no está en el catálogo.
        $component = Native::test(ListDetail::class, ['list' => 'list-1']);
        $component->set('search', 'Yerba');
        $component->call('createAndAdd');

        $pending = $component->get('pending');

        $this->assertCount(1, $pending, 'El ítem tiene que verse ya, sin esperar al servidor.');
        $this->assertSame('Yerba', $pending[0]['name']);

        // El servidor confirma: el delta trae el ítem real con el mismo nombre.
        $syncing = true;

        app(SyncEngine::class)->run();

        $rows = app(LocalStore::class)->itemsOf('list-1');

        $this->assertCount(1, $rows, 'La fila optimista se retira cuando llega la real.');
        $this->assertSame('real-1', $rows[0]['id']);
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
