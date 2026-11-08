<?php

namespace App\Services\Homeside;

/**
 * Catálogo de productos y categorías para armar la pantalla de agregar ítems.
 *
 * Los colores de las cards salen de acá (`category.color`), no del tema: son
 * datos del catálogo de cada instalación.
 */
final class CatalogService
{
    public function __construct(
        private readonly Session $session = new Session,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(int $perPage = 100): array
    {
        return array_values(Session::data(
            $this->session->client()->get('/categories', ['perPage' => $perPage]),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?string $categoryId = null, int $perPage = 30): array
    {
        $term = trim($query);
        $hasTerm = $term !== '';

        $params = ['perPage' => $perPage];

        if ($hasTerm) {
            $params['q'] = $term;
        }

        if ($categoryId !== null && $categoryId !== '') {
            $params['category_id'] = $categoryId;
        }

        return array_values(Session::data(
            $this->session->client()->get($hasTerm ? '/products/search' : '/products', $params),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 20, ?string $householdId = null): array
    {
        $params = ['limit' => $limit];

        if ($householdId !== null && $householdId !== '') {
            $params['household_id'] = $householdId;
        }

        return array_values(Session::data(
            $this->session->client()->get('/products/recent', $params),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function quickCreate(string $name): array
    {
        return Session::data($this->session->client()->post('/products/quick-create', [
            'name' => trim($name),
        ]));
    }
}
