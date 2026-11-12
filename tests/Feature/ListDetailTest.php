<?php

namespace Tests\Feature;

use App\NativeComponents\ListDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

class ListDetailTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function listPayload(): array
    {
        return [
            'data' => [
                'id' => 'list-1',
                'name' => 'Compra semanal',
                'household_id' => null,
                'items' => [
                    [
                        'id' => 'item-1',
                        'list_id' => 'list-1',
                        'custom_name' => null,
                        'quantity' => 2,
                        'unit' => 'un',
                        'is_checked' => false,
                        'notes' => 'Sin sal',
                        'icon' => null,
                        'image_url' => null,
                        'store_id' => null,
                        'category_id' => 'cat-1',
                        'product' => [
                            'id' => 'product-1',
                            'name' => 'Leche',
                            'icon' => 'milk.webp',
                            'category' => ['id' => 'cat-1', 'name' => 'Lácteos', 'color' => '#42A5F5'],
                        ],
                        'category' => null,
                        'updated_at' => '2026-11-12T10:00:00Z',
                    ],
                    [
                        'id' => 'item-2',
                        'list_id' => 'list-1',
                        'custom_name' => 'Servilletas',
                        'quantity' => 1,
                        'unit' => null,
                        'is_checked' => true,
                        'notes' => null,
                        'icon' => null,
                        'image_url' => null,
                        'store_id' => null,
                        'category_id' => null,
                        'product' => null,
                        'category' => null,
                        'updated_at' => '2026-11-12T10:00:00Z',
                    ],
                ],
            ],
        ];
    }

    private function fakeScreen(): void
    {
        Http::fake([
            '*/api/v1/lists/list-1' => Http::response($this->listPayload()),
            '*/api/v1/categories*' => Http::response(['data' => [
                ['id' => 'cat-1', 'name' => 'Lácteos', 'color' => '#42A5F5'],
                ['id' => 'cat-2', 'name' => 'Verdulería', 'color' => '#7CCBA2'],
            ]]),
            '*/api/v1/products/recent*' => Http::response(['data' => [
                ['id' => 'product-9', 'name' => 'Manzana', 'icon' => 'apple.webp', 'category' => ['id' => 'cat-2', 'name' => 'Verdulería', 'color' => '#7CCBA2']],
            ]]),
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => null])]),
        ]);
    }

    public function test_it_loads_the_list_the_way_the_web_screen_does(): void
    {
        $this->connect();
        $this->fakeScreen();

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->assertSet('name', 'Compra semanal')
            ->assertSee('Leche')
            ->assertSet('categories.0.name', 'Lácteos')
            ->assertSee('Manzana')
            ->assertSee('Lácteos');
    }

    public function test_checked_items_are_not_shown_like_the_web(): void
    {
        $this->connect();
        $this->fakeScreen();

        $component = Native::test(ListDetail::class, ['list' => 'list-1']);

        $pending = $component->get('pending');

        $this->assertCount(1, $pending);
        $this->assertSame('item-1', $pending[0]['id']);
    }

    public function test_pending_cards_keep_the_web_colour(): void
    {
        $this->connect();
        $this->fakeScreen();

        $pending = Native::test(ListDetail::class, ['list' => 'list-1'])->get('pending');

        $this->assertSame('#B42318', $pending[0]['color']);
        $this->assertSame('#FFFFFF', $pending[0]['color_text']);
        $this->assertSame('https://homeside.cloud/icons/webp/milk.webp', $pending[0]['icon_url']);
    }

    public function test_toggling_an_item_flips_it_on_the_server(): void
    {
        $this->connect();
        $this->fakeScreen();
        Http::fake(['*/api/v1/lists/list-1/items/item-1' => Http::response(['data' => ['id' => 'item-1']])]);

        Native::test(ListDetail::class, ['list' => 'list-1'])->call('toggle', 'item-1');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/lists/list-1/items/item-1')
            && $request->method() === 'PUT'
            && $request['is_checked'] === true);
    }

    public function test_pressing_a_rendered_card_toggles_it(): void
    {
        $this->connect();
        $this->fakeScreen();
        Http::fake(['*/api/v1/lists/list-1/items/item-1' => Http::response(['data' => ['id' => 'item-1']])]);

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->press('toggle("item-1")');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/lists/list-1/items/item-1')
            && $request['is_checked'] === true);
    }

    public function test_searching_the_catalog_returns_coloured_results(): void
    {
        $this->connect();
        $this->fakeScreen();
        Http::fake(['*/api/v1/products/search*' => Http::response(['data' => [
            ['id' => 'product-3', 'name' => 'Queso', 'icon' => 'cheese.webp', 'category' => ['id' => 'cat-1', 'name' => 'Lácteos', 'color' => '#42A5F5']],
        ]])]);

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->set('search', 'queso')
            ->call('onSearch')
            ->assertSet('searchDone', true)
            ->assertSee('Queso')
            ->assertSet('results.0.color', '#42A5F5');
    }

    public function test_adding_a_product_from_the_results(): void
    {
        $this->connect();
        $this->fakeScreen();
        Http::fake(['*/api/v1/lists/list-1/items' => Http::response(['data' => ['id' => 'item-8']], 201)]);

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->call('addProduct', 'product-3');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/lists/list-1/items')
            && $request['product_id'] === 'product-3');
    }

    public function test_creating_a_missing_product_from_the_search(): void
    {
        $this->connect();
        $this->fakeScreen();
        Http::fake(['*/api/v1/lists/list-1/items/quick-create' => Http::response(['data' => ['id' => 'item-9']], 201)]);

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->set('search', 'papel film')
            ->call('createAndAdd');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/items/quick-create')
            && $request['product']['name'] === 'papel film');
    }

    public function test_expanding_a_category_loads_only_its_products(): void
    {
        $this->connect();
        $this->fakeScreen();
        Http::fake(['*/api/v1/products?*category_id=cat-1*' => Http::response(['data' => [
            ['id' => 'product-1', 'name' => 'Leche', 'icon' => 'milk.webp', 'category' => ['id' => 'cat-1', 'name' => 'Lácteos', 'color' => '#42A5F5']],
        ]])]);

        $component = Native::test(ListDetail::class, ['list' => 'list-1'])
            ->call('toggleCategory', 'cat-1')
            ->assertSet('categories.0.open', true)
            ->assertSet('categories.0.loaded', true);

        $this->assertSame('Leche', $component->get('categories.0.products.0.name'));
    }

    public function test_renaming_the_list_puts_the_name(): void
    {
        $this->connect();
        $this->fakeScreen();
        Http::fake(['*/api/v1/lists/list-1' => Http::response(['data' => ['id' => 'list-1', 'name' => 'Súper']])]);

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->set('renameDraft', 'Súper')
            ->call('rename')
            ->assertSet('name', 'Súper');
    }

    public function test_the_household_route_uses_the_household_prefix(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/households/household-1/lists/list-1' => Http::response($this->listPayload()),
            '*/api/v1/categories*' => Http::response(['data' => []]),
            '*/api/v1/products/recent*' => Http::response(['data' => []]),
            '*/api/v1/households/household-1/stores*' => Http::response(['data' => []]),
        ]);

        Native::test(ListDetail::class, ['household' => 'household-1', 'list' => 'list-1'])
            ->assertSet('name', 'Compra semanal')
            ->assertSee('Leche');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://homeside.cloud/api/v1/households/household-1/lists/list-1');
    }

    public function test_it_reports_an_expired_session(): void
    {
        $this->connect();
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->assertSet('needsLogin', true);
    }

    public function test_deleting_a_list_goes_back(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/lists/list-1' => Http::sequence()->push($this->listPayload())->push([], 204),
            '*/api/v1/categories*' => Http::response(['data' => []]),
            '*/api/v1/products/recent*' => Http::response(['data' => []]),
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => null])]),
        ]);

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->call('deleteList')
            ->assertWentBack();
    }
}
