<?php

namespace Tests\Feature;

use App\Exceptions\HomesideException;
use App\Services\Homeside\HouseholdsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * El hogar activo es el eje de toda la app: define qué listas, recetas y
 * gastos se ven. Vive en un solo servicio para que no existan dos verdades.
 */
class HouseholdsServiceTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function fakeHouseholds(): void
    {
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana', 'member_count' => 3],
                ['id' => 'household-2', 'name' => 'Casa de la playa', 'member_count' => 1],
            ]]),
        ]);
    }

    public function test_it_lists_the_households_of_the_user(): void
    {
        $this->connect();
        $this->fakeHouseholds();

        $households = (new HouseholdsService)->all();

        $this->assertCount(2, $households);
        $this->assertSame('Casa de Ana', $households[0]['name']);
    }

    public function test_the_active_household_id_comes_from_me(): void
    {
        $this->connect();
        $this->fakeHouseholds();

        $this->assertSame('household-1', (new HouseholdsService)->activeId());
    }

    public function test_without_an_active_household_it_returns_null(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => null])]),
            '*/api/v1/households' => Http::response(['data' => []]),
        ]);

        $service = new HouseholdsService;

        $this->assertNull($service->activeId());
        $this->assertNull($service->active());
    }

    public function test_the_active_household_is_resolved_from_the_list(): void
    {
        $this->connect();
        $this->fakeHouseholds();

        $active = (new HouseholdsService)->active();

        $this->assertSame('household-1', $active['id'] ?? null);
        $this->assertSame(3, $active['member_count'] ?? null);
    }

    public function test_an_active_id_that_is_not_in_the_list_resolves_to_null(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => 'household-9'])]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
        ]);

        // Sin esto, la app mostraría un hogar que ya no existe (o del que lo
        // sacaron) y todas las pantallas pedirían datos a un id muerto.
        $this->assertNull((new HouseholdsService)->active());
        $this->assertNull((new HouseholdsService)->name('household-9'));
    }

    public function test_the_name_is_resolved_from_the_list(): void
    {
        $this->connect();
        $this->fakeHouseholds();

        $service = new HouseholdsService;

        $this->assertSame('Casa de la playa', $service->name('household-2'));
        $this->assertNull($service->name(null));
    }

    public function test_the_household_list_is_fetched_once_per_instance(): void
    {
        $this->connect();
        $this->fakeHouseholds();

        $service = new HouseholdsService;
        $service->active();
        $service->name('household-2');

        // `/me` + `/households`, y el segundo `all()` sale del memo. Sin memo
        // serían tres requests por render para el mismo dato.
        Http::assertSentCount(2);
    }

    public function test_it_reports_a_missing_session(): void
    {
        $this->connect(token: null);
        Http::fake();

        $this->expectException(HomesideException::class);

        (new HouseholdsService)->activeId();
    }
}
