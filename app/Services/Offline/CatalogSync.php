<?php

namespace App\Services\Offline;

use App\Exceptions\HomesideException;
use App\Services\Homeside\HomesideClient;
use App\Services\Homeside\Session;

/**
 * Baja el catálogo que la app necesita para trabajar sin red.
 *
 * El delta de `/sync/changes` no trae catálogo: son las listas, sus ítems y los
 * movimientos. Sin esta descarga, sin red no hay categorías ni productos salvo
 * los que se hayan abierto a mano, que es justo lo que no sirve.
 *
 * Corre desde el motor de sync, con caducidad, y nunca interrumpe: si falla, la
 * app sigue funcionando con lo que ya tenía.
 */
final class CatalogSync
{
    /** Cuánto vale lo bajado antes de volver a pedirlo. */
    private const int MAX_AGE_MINUTES = 360;

    /** Tope de páginas por si el catálogo crece más de lo esperado. */
    private const int MAX_PAGES = 10;

    /**
     * El endpoint valida `perPage` entre 1 y 100: pedir más devuelve 422 y la
     * descarga no guarda nada.
     */
    private const int PER_PAGE = 100;

    public function __construct(
        private readonly LocalStore $store = new LocalStore,
        private readonly SyncState $state = new SyncState,
        private readonly Session $session = new Session,
    ) {}

    /**
     * Baja el catálogo si está viejo.
     *
     * @return bool true si descargó
     */
    public function refresh(bool $force = false): bool
    {
        if (! $force && ! $this->isStale()) {
            return false;
        }

        $client = $this->session->client();

        try {
            $categories = Session::data($client->get('/categories', ['perPage' => 100]));

            foreach ($categories as $category) {
                if (is_array($category)) {
                    $this->store->put(LocalStore::CATEGORIES, $category);
                }
            }

            $this->fetchProducts($client);

            $recent = Session::data($client->get('/products/recent', ['limit' => 24]));

            foreach ($recent as $product) {
                if (is_array($product)) {
                    $this->store->put(LocalStore::RECENT_PRODUCTS, $product);
                    $this->store->put(LocalStore::PRODUCTS, $product);
                }
            }
        } catch (HomesideException $exception) {
            // Sin red no hay nada que hacer: lo que había sigue sirviendo.
            throw $exception;
        }

        $this->state->setCatalogSyncedAt(now()->toIso8601String());

        return true;
    }

    public function isStale(): bool
    {
        $last = $this->state->catalogSyncedAt();

        if ($last === null) {
            return true;
        }

        $syncedAt = strtotime($last);

        if ($syncedAt === false) {
            return true;
        }

        return $syncedAt < now()->subMinutes(self::MAX_AGE_MINUTES)->getTimestamp();
    }

    /**
     * Todas las páginas del catálogo.
     *
     * El endpoint pagina y `Session::data()` descarta la meta, así que acá se
     * lee la respuesta completa para saber cuántas páginas quedan.
     */
    private function fetchProducts(HomesideClient $client): void
    {
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $payload = $client->get('/products', [
                'perPage' => self::PER_PAGE,
                'page' => $page,
            ]);

            $items = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            foreach ($items as $product) {
                if (is_array($product)) {
                    $this->store->put(LocalStore::PRODUCTS, $product);
                }
            }

            $lastPage = $payload['meta']['last_page'] ?? 1;

            if (! is_numeric($lastPage) || $page >= (int) $lastPage) {
                return;
            }
        }
    }
}
