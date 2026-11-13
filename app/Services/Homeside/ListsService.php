<?php

namespace App\Services\Homeside;

/**
 * Listas de la compra: personales (`/lists`) y de hogar
 * (`/households/{household}/lists`).
 *
 * La API usa dos árboles de rutas para el mismo recurso, así que el prefijo se
 * resuelve en un solo lugar y el resto del código pasa `$householdId` opcional.
 */
final class ListsService
{
    public function __construct(
        private readonly Session $session = new Session,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function personal(array $query = []): array
    {
        return array_values(Session::data(
            $this->session->client()->get('/lists', $query),
        ));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function household(string $householdId, array $query = []): array
    {
        return array_values(Session::data(
            $this->session->client()->get($this->path($householdId), $query),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $listId, ?string $householdId = null): array
    {
        return Session::data($this->session->client()->get($this->path($householdId, $listId)));
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $name, ?string $householdId = null): array
    {
        return Session::data($this->session->client()->post($this->path($householdId), [
            'name' => trim($name),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function rename(string $listId, string $name, ?string $householdId = null): array
    {
        return Session::data($this->session->client()->put($this->path($householdId, $listId), [
            'name' => trim($name),
        ]));
    }

    public function delete(string $listId, ?string $householdId = null): void
    {
        $this->session->client()->delete($this->path($householdId, $listId));
    }

    /**
     * Alta de un ítem. `$payload` acepta `product_id`, `custom_name`,
     * `quantity`, `unit`, `notes`, `category_id`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function addItem(string $listId, array $payload, ?string $householdId = null): array
    {
        return Session::data($this->session->client()->post(
            $this->path($householdId, $listId).'/items',
            $payload,
        ));
    }

    /**
     * Crea producto e ítem en un solo request (buscador sin resultados).
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function quickCreateItem(
        string $listId,
        array $product,
        array $item = ['quantity' => 1],
        ?string $householdId = null,
    ): array {
        return Session::data($this->session->client()->post(
            $this->path($householdId, $listId).'/items/quick-create',
            ['product' => $product, 'item' => $item],
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateItem(
        string $listId,
        string $itemId,
        array $payload,
        ?string $householdId = null,
    ): array {
        return Session::data($this->session->client()->put(
            $this->path($householdId, $listId).'/items/'.$itemId,
            $payload,
        ));
    }

    /**
     * Catálogo de locales. El endpoint vive bajo un hogar porque valida
     * pertenencia, pero devuelve el catálogo global de tiendas: sirve igual
     * para una lista personal mientras el usuario tenga hogar activo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stores(string $householdId, int $perPage = 100): array
    {
        return array_values(Session::data(
            $this->session->client()->get('/households/'.$householdId.'/stores', ['perPage' => $perPage]),
        ));
    }

    /**
     * Sube la foto de un ítem (multipart).
     *
     * @return array<string, mixed>
     */
    public function uploadItemImage(
        string $listId,
        string $itemId,
        string $contents,
        string $filename,
        ?string $householdId = null,
    ): array {
        return Session::data($this->session->client()->upload(
            $this->path($householdId, $listId).'/items/'.$itemId,
            $contents,
            $filename,
        ));
    }

    /**
     * Ruta autenticada de la imagen del ítem (la app la descarga con token).
     */
    public function itemImagePath(string $listId, string $itemId, ?string $householdId = null): string
    {
        return $this->path($householdId, $listId).'/items/'.$itemId.'/image';
    }

    public function deleteItem(string $listId, string $itemId, ?string $householdId = null): void
    {
        $this->session->client()->delete($this->path($householdId, $listId).'/items/'.$itemId);
    }

    private function path(?string $householdId = null, ?string $listId = null): string
    {
        $base = $householdId !== null && $householdId !== ''
            ? '/households/'.$householdId.'/lists'
            : '/lists';

        return $listId !== null && $listId !== '' ? $base.'/'.$listId : $base;
    }
}
