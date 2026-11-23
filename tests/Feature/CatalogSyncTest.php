<?php

namespace Tests\Feature;

use App\Exceptions\HomesideException;
use App\Services\Offline\CatalogSync;
use App\Services\Offline\LocalStore;
use App\Services\Offline\SyncState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * La descarga del catálogo que hace posible trabajar sin red.
 *
 * `/sync/changes` no trae catálogo, así que sin esto las categorías quedan
 * vacías salvo las que se abrieron a mano.
 */
class CatalogSyncTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function fakeCatalog(): void
    {
        Http::fake([
            '*/api/v1/categories*' => Http::response(['data' => [
                ['id' => 'cat-1', 'name' => 'Lácteos', 'color' => '#42A5F5'],
            ]]),
            '*/api/v1/products/recent*' => Http::response(['data' => [
                ['id' => 'p-1', 'name' => 'Leche', 'category' => ['id' => 'cat-1', 'name' => 'Lácteos']],
            ]]),
            // Dos páginas: el endpoint pagina y la app tiene que recorrerlas.
            '*/api/v1/products?perPage=100&page=1*' => Http::response([
                'data' => [['id' => 'p-1', 'name' => 'Leche', 'category' => ['id' => 'cat-1', 'name' => 'Lácteos']]],
                'meta' => ['last_page' => 2],
            ]),
            '*/api/v1/products?perPage=100&page=2*' => Http::response([
                'data' => [['id' => 'p-2', 'name' => 'Yogur', 'category' => ['id' => 'cat-1', 'name' => 'Lácteos']]],
                'meta' => ['last_page' => 2],
            ]),
        ]);
    }

    public function test_it_downloads_categories_and_every_page_of_products(): void
    {
        $this->connect();
        $this->fakeCatalog();

        $this->assertTrue(app(CatalogSync::class)->refresh());

        $store = app(LocalStore::class);

        $this->assertSame('Lácteos', $store->find(LocalStore::CATEGORIES, 'cat-1')['name'] ?? null);

        // Las dos páginas, no sólo la primera.
        $this->assertNotNull($store->find(LocalStore::PRODUCTS, 'p-1'));
        $this->assertNotNull($store->find(LocalStore::PRODUCTS, 'p-2'), 'Tiene que recorrer todas las páginas.');

        // Y los recientes van a su propia bolsa.
        $this->assertNotNull($store->find(LocalStore::RECENT_PRODUCTS, 'p-1'));
    }

    public function test_a_rejected_page_size_saves_nothing_and_reports_it(): void
    {
        $this->connect();

        // Si el servidor rechaza la página (por ejemplo, `perPage` fuera de
        // rango), el refresco tiene que fallar en vez de dejar el catálogo a
        // medias sin que nadie se entere.
        Http::fake([
            '*/api/v1/categories*' => Http::response(['data' => [['id' => 'cat-1', 'name' => 'Lácteos']]]),
            '*/api/v1/products*' => Http::response(['message' => 'The per page field must be between 1 and 100.'], 422),
        ]);

        try {
            app(CatalogSync::class)->refresh();
            $this->fail('Tenía que fallar.');
        } catch (HomesideException) {
            $this->assertTrue(true);
        }

        $this->assertNull(
            app(SyncState::class)->catalogSyncedAt(),
            'Si no se bajó todo, no se marca como sincronizado.',
        );
    }

    public function test_it_does_not_download_again_before_it_goes_stale(): void
    {
        $this->connect();
        $this->fakeCatalog();

        $sync = app(CatalogSync::class);

        $this->assertTrue($sync->refresh());
        $this->assertFalse($sync->refresh(), 'La segunda vez no tiene que pedir nada.');

        $this->assertFalse($sync->isStale());
    }

    public function test_a_forced_refresh_runs_even_when_it_is_fresh(): void
    {
        $this->connect();
        $this->fakeCatalog();

        $sync = app(CatalogSync::class);

        $sync->refresh();

        $this->assertTrue($sync->refresh(force: true));
    }
}
