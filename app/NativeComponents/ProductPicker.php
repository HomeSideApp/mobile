<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\CatalogService;
use App\Services\Homeside\ListsService;
use App\Services\Settings\LocaleService;
use App\Support\CardColor;
use App\Support\ProductIcon;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Buscador de productos para agregar a una lista.
 *
 * Mantiene el lenguaje de color de la web: cada resultado se pinta con el
 * color de su categoría, y las categorías son chips del mismo color.
 */
final class ProductPicker extends NativeComponent
{
    public ?string $listId = null;

    public ?string $householdId = null;

    public string $query = '';

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    /** @var array<int, array<string, mixed>> */
    public array $recent = [];

    /** @var array<int, array<string, mixed>> */
    public array $categories = [];

    public ?string $categoryId = null;

    public bool $loading = false;

    public bool $added = false;

    public ?string $error = null;

    public function mount(): void
    {
        (new LocaleService)->apply();

        $this->listId = $this->param('list');
        $this->householdId = $this->param('household');

        $this->loadCatalog();
    }

    public function navTitle(): string
    {
        return __('app.picker.title');
    }

    public function onQuery(?string $value = null): void
    {
        if (is_string($value)) {
            $this->query = $value;
        }

        $this->search();
    }

    public function selectCategory(string $categoryId): void
    {
        $this->categoryId = $this->categoryId === $categoryId ? null : $categoryId;
        $this->search();
    }

    public function clearCategory(): void
    {
        $this->categoryId = null;
        $this->search();
    }

    /**
     * Agrega un producto del catálogo a la lista y vuelve.
     */
    public function addProduct(string $productId): void
    {
        if ($this->listId === null || $this->loading) {
            return;
        }

        $this->loading = true;
        $this->error = null;

        try {
            (new ListsService)->addItem(
                $this->listId,
                ['product_id' => $productId, 'quantity' => 1],
                $this->householdId,
            );
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->loading = false;

            return;
        }

        $this->loading = false;
        $this->added = true;

        $this->back();
    }

    /**
     * Sin resultados: crea producto + ítem en un solo request.
     */
    public function createAndAdd(): void
    {
        if ($this->listId === null || trim($this->query) === '' || $this->loading) {
            return;
        }

        $this->loading = true;
        $this->error = null;

        try {
            (new ListsService)->quickCreateItem(
                $this->listId,
                ['name' => trim($this->query)],
                ['quantity' => 1],
                $this->householdId,
            );
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->loading = false;

            return;
        }

        $this->loading = false;
        $this->added = true;

        $this->back();
    }

    private function search(): void
    {
        $this->error = null;
        $this->loading = true;

        try {
            $products = (new CatalogService)->search($this->query, $this->categoryId);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->results = [];
            $this->loading = false;

            return;
        }

        $this->results = $this->normalizeProducts($products);
        $this->loading = false;
    }

    private function loadCatalog(): void
    {
        $catalog = new CatalogService;

        try {
            $this->categories = array_map(fn (array $category): array => [
                'id' => (string) ($category['id'] ?? ''),
                'name' => (string) ($category['name'] ?? ''),
                'color' => CardColor::normalize(
                    is_string($category['color'] ?? null) ? $category['color'] : null,
                ),
                'color_text' => CardColor::foreground(CardColor::normalize(
                    is_string($category['color'] ?? null) ? $category['color'] : null,
                )),
            ], $catalog->categories());

            $this->recent = $this->normalizeProducts(
                $catalog->recent(24, $this->householdId),
            );
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProducts(array $products): array
    {
        return array_values(array_map(function (array $product): array {
            $category = is_array($product['category'] ?? null) ? $product['category'] : null;
            $color = CardColor::normalize(
                is_string($category['color'] ?? null) ? $category['color'] : null,
            );

            return [
                'id' => (string) ($product['id'] ?? ''),
                'name' => (string) ($product['name'] ?? ''),
                'icon_url' => ProductIcon::url(
                    is_string($product['icon'] ?? null) ? $product['icon'] : null,
                ),
                'category_name' => is_string($category['name'] ?? null) ? $category['name'] : null,
                'color' => $color,
                'color_text' => CardColor::foreground($color),
            ];
        }, $products));
    }

    public function render(): View
    {
        return view('native.product-picker');
    }
}
