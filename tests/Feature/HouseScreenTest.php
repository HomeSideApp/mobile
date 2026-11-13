<?php

namespace Tests\Feature;

use App\NativeComponents\House;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * Pantalla Casa: quién vive en el hogar activo y qué módulos están habilitados.
 * Es de lectura; cambiar de hogar, invitar y crear llegan en tareas aparte.
 */
class HouseScreenTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function fakeHouse(array $overrides = []): void
    {
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => [
                'pending_items_count' => 7,
                'active_lists_count' => 3,
                'household_recipes_count' => 12,
                'enabled_modules' => ['economy', 'recipes'],
            ]]),
            '*/api/v1/households/invitations' => Http::response(['data' => []]),
            '*/api/v1/households/configuration' => Http::response(['data' => [
                'modules' => [
                    ['key' => 'recipes', 'label' => 'Recetas', 'description' => 'Recetario compartido'],
                    ['key' => 'economy', 'label' => 'Economía', 'description' => 'Gastos de la casa'],
                    ['key' => 'contacts', 'label' => 'Contactos', 'description' => 'Agenda del hogar'],
                ],
            ]]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1',
                'name' => 'Casa de Ana',
                'description' => 'La de todos los días',
                'color' => '#1E6B63',
                'members_count' => 3,
                'can_manage' => true,
                'created_at' => '2025-03-14T10:00:00+00:00',
                'members' => [
                    [
                        'id' => 'member-1',
                        'role' => 'admin',
                        'joined_at' => '2025-03-14T10:00:00+00:00',
                        'user' => ['id' => 'user-1', 'name' => 'Ana', 'email' => 'ana@example.com'],
                    ],
                    [
                        'id' => 'member-2',
                        'role' => 'member',
                        'joined_at' => '2025-04-01T09:30:00+00:00',
                        'user' => ['id' => 'user-2', 'name' => 'Beto', 'email' => 'beto@example.com'],
                    ],
                ],
                ...$overrides,
            ]]),
        ]);
    }

    public function test_it_shows_the_active_household_with_its_members(): void
    {
        $this->connect();
        $this->fakeHouse();

        Native::test(House::class)
            ->assertSet('householdName', 'Casa de Ana')
            ->assertSee('Casa de Ana')
            ->assertSee('Ana')
            ->assertSee('Beto')
            ->assertSee('Administrador')
            ->assertSee('Miembro');
    }

    public function test_it_shows_the_household_metrics(): void
    {
        $this->connect();
        $this->fakeHouse();

        Native::test(House::class)
            ->assertSet('pendingCount', 7)
            ->assertSet('listsCount', 3)
            ->assertSet('recipesCount', 12)
            ->assertSee('7')
            ->assertSee('12');
    }

    public function test_enabled_modules_come_from_the_dashboard_and_labels_from_the_catalog(): void
    {
        $this->connect();
        $this->fakeHouse();

        $modules = Native::test(House::class)->get('modules');

        // `contacts` existe en el catálogo pero no está habilitado en el hogar.
        $estados = collect($modules)->mapWithKeys(fn (array $m): array => [$m['key'] => $m['enabled']])->all();

        $this->assertSame(['recipes' => true, 'economy' => true, 'contacts' => false], $estados);
        $this->assertSame('Economía', collect($modules)->firstWhere('key', 'economy')['label']);
    }

    public function test_a_raw_translation_key_from_the_server_becomes_a_label(): void
    {
        $this->connect();

        // Visto en el dispositivo: cuando el `__()` del servidor no resuelve, el
        // catálogo manda la clave cruda y la pantalla mostraba
        // "households.modules.recipes_label".
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => [
                'enabled_modules' => [],
            ]]),
            '*/api/v1/households/invitations' => Http::response(['data' => []]),
            '*/api/v1/households/configuration' => Http::response(['data' => [
                'modules' => [
                    ['key' => 'recipes', 'label' => 'households.modules.recipes_label'],
                    ['key' => 'mystery_module', 'label' => 'households.modules.mystery_module_label'],
                ],
            ]]),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1',
                'name' => 'Casa de Ana',
                'members' => [],
            ]]),
        ]);

        $modules = Native::test(House::class)->get('modules');

        $this->assertSame('Recetas', $modules[0]['label']);
        // Un módulo que la app no conoce igual se lee como texto humano.
        $this->assertSame('Mystery Module', $modules[1]['label']);
    }

    public function test_it_lists_pending_invitations_and_can_accept_them(): void
    {
        $this->connect();

        // Un solo `Http::fake()`: llamarlo dos veces acumula stubs y el primero
        // que matchea gana, así que el segundo conjunto nunca se usaba.
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
            '*/api/v1/households/configuration' => Http::response(['data' => ['modules' => []]]),
            '*/api/v1/households/invitations*' => Http::response(['data' => [
                [
                    'id' => 'invitation-1',
                    'status' => 'pending',
                    'expires_at' => '2026-12-01T10:00:00+00:00',
                    'household' => ['id' => 'household-7', 'name' => 'Casa de Beto'],
                    'inviter' => ['id' => 'user-2', 'name' => 'Beto'],
                ],
            ]]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => [
                'enabled_modules' => [],
            ]]),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1', 'name' => 'Casa de Ana', 'members' => [],
            ]]),
        ]);

        $screen = Native::test(House::class)
            ->assertSee('Casa de Beto')
            ->assertSet('invitationCount', 1);

        $invitations = $screen->get('invitations');
        $this->assertSame('Beto', $invitations[0]['inviter'] ?? null);

        $screen->call('acceptInvitation', 'invitation-1');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/households/invitations/invitation-1/accept'));
    }

    public function test_a_declined_invitation_is_removed_from_the_list(): void
    {
        $this->connect();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
            '*/api/v1/households/configuration' => Http::response(['data' => ['modules' => []]]),
            '*/api/v1/households/invitations/*' => Http::response(['data' => ['id' => 'invitation-1']]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => ['enabled_modules' => []]]),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1', 'name' => 'Casa de Ana', 'members' => [],
            ]]),
        ]);

        $screen = Native::test(House::class)->call('declineInvitation', 'invitation-1');

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/households/invitations/invitation-1'));

        $this->assertSame(0, $screen->get('invitationCount'));
    }

    public function test_without_a_session_it_asks_to_sign_in(): void
    {
        $this->connect(token: null);
        Http::fake();

        Native::test(House::class)->assertSet('needsLogin', true);

        Http::assertNothingSent();
    }

    public function test_a_household_without_the_economy_module_is_reported_as_such(): void
    {
        $this->connect();

        // `Http::fake()` acumula stubs: si acá se llamara al helper, el hogar
        // "Casa de Ana" seguiría matcheando primero.
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households/invitations' => Http::response(['data' => []]),
            '*/api/v1/households/configuration' => Http::response(['data' => [
                'modules' => [['key' => 'economy', 'label' => 'Economía']],
            ]]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => [
                'pending_items_count' => 0,
                'active_lists_count' => 0,
                'household_recipes_count' => 0,
                'enabled_modules' => [],
            ]]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa sin economía'],
            ]]),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1',
                'name' => 'Casa sin economía',
                'members' => [],
            ]]),
        ]);

        Native::test(House::class)
            ->assertSet('householdName', 'Casa sin economía')
            ->assertSee('Economía');
    }
}
