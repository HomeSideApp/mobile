<?php

namespace Tests\Feature;

use App\NativeComponents\Lists;
use App\Services\Homeside\AppUpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

class ListsScreenTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    public function test_without_a_session_it_offers_to_sign_in(): void
    {
        $this->connect(token: null);
        Http::fake();

        Native::test(Lists::class)
            ->assertSet('needsLogin', true)
            ->assertSee('Necesitás iniciar sesión');

        Http::assertNothingSent();
    }

    public function test_it_lists_personal_and_household_lists(): void
    {
        $this->connect();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
            '*/api/v1/lists*' => Http::response(['data' => [
                ['id' => 'list-1', 'name' => 'Compra semanal', 'household_id' => null, 'household' => null],
            ]]),
            '*/api/v1/households/household-1/lists*' => Http::response(['data' => [
                ['id' => 'list-2', 'name' => 'Limpieza', 'household_id' => 'household-1', 'household' => ['id' => 'household-1', 'name' => 'Casa de Ana']],
            ]]),
        ]);

        Native::test(Lists::class)
            ->assertSet('needsLogin', false)
            ->assertSee('Compra semanal')
            ->assertSee('Limpieza')
            ->assertSee('Casa de Ana');
    }

    public function test_it_warns_when_the_session_expired(): void
    {
        $this->connect();

        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        Native::test(Lists::class)
            ->assertSet('needsLogin', true)
            ->assertSee('Necesitás iniciar sesión');
    }

    public function test_the_search_filter_travels_to_the_api(): void
    {
        $this->connect();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => null])]),
            '*/api/v1/households' => Http::response(['data' => []]),
            '*/api/v1/lists*' => Http::response(['data' => []]),
        ]);

        Native::test(Lists::class)
            ->set('search', 'farmacia')
            ->call('onSearch')
            ->assertSee('No encontramos listas');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/v1/lists')
            && str_contains($request->url(), 'search=farmacia'));
    }

    public function test_it_banners_a_known_update_without_hitting_the_network(): void
    {
        $this->connect();
        config(['nativephp.version_code' => 10]);

        Http::fake([
            '*/releases/*/latest.json' => Http::response([
                'channel' => 'dev',
                'version' => '0.1.50-dev',
                'version_code' => 50,
                'apk_url' => 'https://example.test/homeside-dev.apk',
            ]),
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => null])]),
            '*/api/v1/households' => Http::response(['data' => []]),
            '*/api/v1/lists*' => Http::response(['data' => []]),
        ]);

        // Un chequeo previo (en Perfil o en un arranque anterior) deja el resultado cacheado.
        (new AppUpdateChecker)->status();

        Native::test(Lists::class)
            ->assertSet('updateAvailable', true)
            ->assertSee('Hay una versión nueva: 0.1.50-dev');
    }

    public function test_without_a_previous_check_there_is_no_banner(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => null])]),
            '*/api/v1/households' => Http::response(['data' => []]),
            '*/api/v1/lists*' => Http::response(['data' => []]),
        ]);

        Native::test(Lists::class)
            ->assertSet('updateAvailable', false);
    }

    public function test_it_navigates_to_a_personal_list(): void
    {
        $this->connect();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => null])]),
            '*/api/v1/households' => Http::response(['data' => []]),
            '*/api/v1/lists*' => Http::response(['data' => [
                ['id' => 'list-1', 'name' => 'Compra semanal', 'household_id' => null],
            ]]),
        ]);

        Native::test(Lists::class)
            ->call('openList', 'list-1')
            ->assertNavigatedTo('/lists/list-1');
    }

    public function test_it_navigates_to_a_household_list(): void
    {
        $this->connect();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [['id' => 'household-1', 'name' => 'Casa de Ana']]]),
            '*/api/v1/lists*' => Http::response(['data' => []]),
            '*/api/v1/households/household-1/lists*' => Http::response(['data' => [
                ['id' => 'list-2', 'name' => 'Limpieza', 'household_id' => 'household-1'],
            ]]),
        ]);

        Native::test(Lists::class)
            ->call('openHouseholdList', 'list-2')
            ->assertNavigatedTo('/households/household-1/lists/list-2');
    }
}
