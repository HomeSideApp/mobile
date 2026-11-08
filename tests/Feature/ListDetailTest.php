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
    private function payload(): array
    {
        return [
            'data' => [
                'id' => 'list-1',
                'name' => 'Compra semanal',
                'household_id' => null,
                'items' => [
                    [
                        'id' => 'item-1',
                        'custom_name' => null,
                        'quantity' => 2,
                        'unit' => 'un',
                        'is_checked' => false,
                        'notes' => null,
                        'icon' => null,
                        'product' => [
                            'id' => 'product-1',
                            'name' => 'Leche',
                            'icon' => 'milk.webp',
                            'category' => ['id' => 'cat-1', 'name' => 'Lácteos', 'color' => '#42A5F5'],
                        ],
                        'category' => null,
                    ],
                    [
                        'id' => 'item-2',
                        'custom_name' => 'Servilletas',
                        'quantity' => 1,
                        'unit' => null,
                        'is_checked' => true,
                        'notes' => 'Las de siempre',
                        'icon' => null,
                        'product' => null,
                        'category' => null,
                    ],
                ],
            ],
        ];
    }

    public function test_it_splits_pending_and_checked_items_with_their_colors(): void
    {
        $this->connect();
        Http::fake(['*/api/v1/lists/list-1' => Http::response($this->payload())]);

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->assertSet('name', 'Compra semanal')
            ->assertSee('Leche')
            ->assertSee('Servilletas')
            ->assertSee('Pendientes (1)')
            ->assertSee('Comprados (1)');
    }

    public function test_pending_cards_use_the_destructive_color_and_checked_ones_the_success_color(): void
    {
        $this->connect();
        Http::fake(['*/api/v1/lists/list-1' => Http::response($this->payload())]);

        $component = Native::test(ListDetail::class, ['list' => 'list-1']);

        $pending = $component->get('pending');
        $checked = $component->get('checked');

        $this->assertSame('#B42318', $pending[0]['color']);
        $this->assertSame('#FFFFFF', $pending[0]['color_text']);
        $this->assertSame('#1E6B63', $checked[0]['color']);
        $this->assertSame('#FFFFFF', $checked[0]['color_text']);
    }

    public function test_the_product_icon_is_resolved_against_the_configured_server(): void
    {
        $this->connect();
        Http::fake(['*/api/v1/lists/list-1' => Http::response($this->payload())]);

        $pending = Native::test(ListDetail::class, ['list' => 'list-1'])->get('pending');

        $this->assertSame(
            'https://homeside.cloud/icons/webp/milk.webp',
            $pending[0]['icon_url'],
        );
    }

    public function test_toggling_an_item_flips_it_on_the_server(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/lists/list-1/items/item-1' => Http::response(['data' => ['id' => 'item-1']]),
            '*/api/v1/lists/list-1' => Http::response($this->payload()),
        ]);

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->call('toggle', 'item-1');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/lists/list-1/items/item-1')
            && $request->method() === 'PUT'
            && $request['is_checked'] === true);
    }

    public function test_the_household_route_uses_the_household_prefix(): void
    {
        $this->connect();
        Http::fake(['*/api/v1/households/household-1/lists/list-1' => Http::response($this->payload())]);

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
            '*/api/v1/lists/list-1' => Http::sequence()
                ->push($this->payload())
                ->push([], 204),
        ]);

        Native::test(ListDetail::class, ['list' => 'list-1'])
            ->call('deleteList')
            ->assertWentBack();
    }
}
