<?php

namespace Tests\Feature;

use App\NativeComponents\ListCreate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * Elegir si la lista es propia o del hogar.
 *
 * La prueba aprieta la tarjeta **como la dibuja el blade**: si el `@press` queda
 * mal compilado, el callback llega sin argumentos y el contenedor responde
 * "Unable to resolve dependency" en vez de cambiar el ámbito.
 */
class ListCreateScopeTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function fakeScreen(array $me = []): void
    {
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me($me)]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa', 'role' => 'member'],
            ]]),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1', 'name' => 'Casa', 'role' => 'member',
                'enabled_modules' => ['shopping_lists'],
            ]]),
            '*/api/v1/*' => Http::response(['data' => []]),
        ]);
    }

    public function test_the_active_household_is_the_default_scope(): void
    {
        $this->connect();
        $this->fakeScreen(['active_household_id' => 'household-1']);

        Native::test(ListCreate::class, platform: 'android')
            ->assertSet('scope', 'household')
            ->assertSet('householdName', 'Casa');
    }

    public function test_pressing_the_personal_card_switches_the_scope(): void
    {
        $this->connect();
        $this->fakeScreen(['active_household_id' => 'household-1']);

        Native::test(ListCreate::class, platform: 'android')
            ->press('chooseScope("personal")')
            ->assertSet('scope', 'personal')
            ->press('chooseScope("household")')
            ->assertSet('scope', 'household');
    }

    public function test_without_a_household_only_the_personal_card_exists(): void
    {
        $this->connect();
        $this->fakeScreen(['active_household_id' => null]);

        $component = Native::test(ListCreate::class, platform: 'android');

        $component->assertSet('scope', 'personal')->assertSet('householdId', null);
    }
}
