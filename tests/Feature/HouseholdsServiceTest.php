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

    public function test_it_creates_a_household_with_the_given_attributes(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/households' => Http::response(['data' => [
                'id' => 'household-9',
                'name' => 'Casa nueva',
            ]], 201),
        ]);

        $created = (new HouseholdsService)->create([
            'name' => 'Casa nueva',
            'color' => '#1E6B63',
            'modules' => ['shopping_lists' => true],
        ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v1/households')
            && $request['name'] === 'Casa nueva'
            && $request['color'] === '#1E6B63');

        $this->assertSame('household-9', $created['id']);
    }

    public function test_a_quota_refusal_is_reported_as_quota(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/households' => Http::response(['code' => 'quota_exceeded'], 402),
        ]);

        try {
            (new HouseholdsService)->create(['name' => 'Casa nueva']);
            $this->fail('Se esperaba HomesideException');
        } catch (HomesideException $exception) {
            // 402 caía en "unexpected": la pantalla no podía distinguir un plan
            // sin cupo de un error cualquiera.
            $this->assertSame(HomesideException::QUOTA, $exception->reason);
            $this->assertSame(402, $exception->status);
        }
    }

    public function test_it_lists_the_pending_invitations(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/households/invitations' => Http::response(['data' => [
                [
                    'id' => 'invitation-1',
                    'status' => 'pending',
                    'expires_at' => '2026-12-01T10:00:00+00:00',
                    'household' => ['id' => 'household-7', 'name' => 'Casa de Beto'],
                    'inviter' => ['id' => 'user-2', 'name' => 'Beto'],
                ],
            ]]),
        ]);

        $invitations = (new HouseholdsService)->invitations();

        $this->assertCount(1, $invitations);
        $this->assertSame('Casa de Beto', $invitations[0]['household']['name'] ?? null);
    }

    public function test_it_accepts_and_declines_invitations(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/households/invitations/*' => Http::response(['data' => ['id' => 'invitation-1']]),
        ]);

        $service = new HouseholdsService;
        $service->acceptInvitation('invitation-1');
        $service->declineInvitation('invitation-2');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v1/households/invitations/invitation-1/accept'));

        // Rechazar es un DELETE: el invitado puede cancelar su propia invitación
        // (el servidor autoriza por email), no hace falta un endpoint aparte.
        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/v1/households/invitations/invitation-2'));
    }

    public function test_it_reports_a_missing_session(): void
    {
        $this->connect(token: null);
        Http::fake();

        $this->expectException(HomesideException::class);

        (new HouseholdsService)->activeId();
    }
}
