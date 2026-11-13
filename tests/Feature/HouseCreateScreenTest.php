<?php

namespace Tests\Feature;

use App\NativeComponents\HouseCreate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * Crear hogar. La API pide sólo el nombre, acepta color y módulos, y **cambia el
 * hogar activo sola**. Puede responder 402 si el plan no permite más hogares, y
 * eso necesita un mensaje propio: "algo salió mal" ahí es un bug de producto.
 */
class HouseCreateScreenTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    public function test_the_household_needs_a_name(): void
    {
        $this->connect();
        Http::fake();

        $screen = Native::test(HouseCreate::class)->call('create');

        $this->assertNotNull($screen->get('error'));
        Http::assertNothingSent();
    }

    public function test_it_creates_the_household_with_every_module_on_by_default(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/households' => Http::response(['data' => [
                'id' => 'household-9',
                'name' => 'Casa nueva',
            ]], 201),
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
        ]);

        $screen = Native::test(HouseCreate::class)
            ->set('name', 'Casa nueva')
            ->call('create');

        // Un hogar sin módulos no sirve para nada: la primera pantalla respondería
        // "This module is not enabled for this household".
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v1/households')
            && $request['name'] === 'Casa nueva'
            && $request['modules'] === [
                'shopping_lists' => true,
                'recipes' => true,
                'economy' => true,
            ]);

        $this->assertNull($screen->get('error'));
    }

    public function test_a_module_can_be_turned_off_before_creating(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/households' => Http::response(['data' => ['id' => 'household-9', 'name' => 'Casa']], 201),
        ]);

        Native::test(HouseCreate::class)
            ->set('name', 'Casa')
            ->call('toggleModule', 'economy')
            ->call('create');

        Http::assertSent(fn ($request): bool => $request['modules']['economy'] === false
            && $request['modules']['shopping_lists'] === true);
    }

    public function test_a_plan_without_room_gets_its_own_message(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/households' => Http::response([
                'message' => 'quota exceeded',
                'code' => 'quota_exceeded',
            ], 402),
        ]);

        $screen = Native::test(HouseCreate::class)
            ->set('name', 'Casa nueva')
            ->call('create');

        $message = (string) $screen->get('error');

        $this->assertStringContainsString('plan', $message);
        $this->assertSame('quota', $screen->get('errorReason'));
    }

    public function test_the_color_is_sent_as_hex(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/households' => Http::response(['data' => ['id' => 'household-9', 'name' => 'Casa']], 201),
        ]);

        Native::test(HouseCreate::class)
            ->set('name', 'Casa')
            ->set('color', '#B45309')
            ->call('create');

        Http::assertSent(fn ($request): bool => $request['color'] === '#B45309');
    }
}
