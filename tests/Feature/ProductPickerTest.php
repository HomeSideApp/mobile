<?php

namespace Tests\Feature;

use App\NativeComponents\ProductPicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

class ProductPickerTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function fakeCatalog(): void
    {
        Http::fake([
            '*/api/v1/categories*' => Http::response(['data' => [
                ['id' => 'cat-1', 'name' => 'Lácteos', 'color' => '#42A5F5'],
                ['id' => 'cat-2', 'name' => 'Verdulería', 'color' => '#7CCBA2'],
            ]]),
            '*/api/v1/products/recent*' => Http::response(['data' => [
                ['id' => 'product-1', 'name' => 'Leche', 'icon' => 'milk.webp', 'category' => ['id' => 'cat-1', 'name' => 'Lácteos', 'color' => '#42A5F5']],
            ]]),
            '*/api/v1/products/search*' => Http::response(['data' => [
                ['id' => 'product-9', 'name' => 'Manzana', 'icon' => 'apple.webp', 'category' => ['id' => 'cat-2', 'name' => 'Verdulería', 'color' => '#7CCBA2']],
            ]]),
            '*/api/v1/lists/list-1/items/quick-create' => Http::response(['data' => ['id' => 'item-9']], 201),
            '*/api/v1/lists/list-1/items' => Http::response(['data' => ['id' => 'item-8']], 201),
        ]);
    }

    public function test_it_loads_categories_and_recent_products_with_category_colors(): void
    {
        $this->connect();
        $this->fakeCatalog();

        $picker = Native::test(ProductPicker::class, ['list' => 'list-1'])
            ->assertSee('Lácteos')
            ->assertSee('Verdulería')
            ->assertSee('Leche')
            ->assertSee('Usados hace poco');

        $this->assertSame('#42A5F5', $picker->get('categories.0.color'));
        // Azul medio: gana el texto oscuro por contraste.
        $this->assertSame('#153C3A', $picker->get('categories.0.color_text'));
        $this->assertSame('#7CCBA2', $picker->get('categories.1.color'));
        // Verde claro: el texto legible es oscuro.
        $this->assertSame('#153C3A', $picker->get('categories.1.color_text'));
    }

    public function test_searching_hits_the_search_endpoint(): void
    {
        $this->connect();
        $this->fakeCatalog();

        Native::test(ProductPicker::class, ['list' => 'list-1'])
            ->set('query', 'manzana')
            ->call('onQuery')
            ->assertSee('Manzana');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/products/search')
            && str_contains($request->url(), 'q=manzana'));
    }

    public function test_adding_a_product_posts_the_item_and_returns(): void
    {
        $this->connect();
        $this->fakeCatalog();

        Native::test(ProductPicker::class, ['list' => 'list-1'])
            ->call('addProduct', 'product-1')
            ->assertWentBack();

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/lists/list-1/items')
            && $request['product_id'] === 'product-1'
            && $request['quantity'] === 1);
    }

    public function test_creating_a_missing_product_uses_quick_create(): void
    {
        $this->connect();
        $this->fakeCatalog();

        Native::test(ProductPicker::class, ['list' => 'list-1'])
            ->set('query', 'papel film')
            ->call('createAndAdd')
            ->assertWentBack();

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/items/quick-create')
            && $request['product']['name'] === 'papel film');
    }

    public function test_a_household_list_posts_through_the_household_prefix(): void
    {
        $this->connect();
        $this->fakeCatalog();

        Native::test(ProductPicker::class, ['household' => 'household-1', 'list' => 'list-1'])
            ->call('addProduct', 'product-1');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/households/household-1/lists/list-1/items'));
    }

    public function test_the_selected_category_filters_the_search(): void
    {
        $this->connect();
        $this->fakeCatalog();

        Native::test(ProductPicker::class, ['list' => 'list-1'])
            ->call('selectCategory', 'cat-2');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/products')
            && str_contains($request->url(), 'category_id=cat-2'));
    }
}
