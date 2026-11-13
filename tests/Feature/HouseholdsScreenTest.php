<?php

namespace Tests\Feature;

use App\NativeComponents\Households;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * Selector de hogar: la lista que abre la acción de la barra superior.
 */
class HouseholdsScreenTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function fakeHouseholds(): void
    {
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana', 'members_count' => 3, 'color' => '#1E6B63'],
                ['id' => 'household-2', 'name' => 'Casa de la playa', 'members_count' => 1],
            ]]),
            '*/api/v1/households/switch/*' => Http::response(['data' => ['ok' => true]]),
        ]);
    }

    public function test_it_lists_the_households_and_marks_the_active_one(): void
    {
        $this->connect();
        $this->fakeHouseholds();

        $screen = Native::test(Households::class)
            ->assertSet('activeId', 'household-1')
            ->assertSee('Casa de Ana')
            ->assertSee('Casa de la playa');

        $households = $screen->get('households');

        $this->assertSame([true, false], array_column($households, 'active'));
        $this->assertSame([3, 1], array_column($households, 'members'));
    }

    public function test_choosing_another_household_switches_it_on_the_server(): void
    {
        $this->connect();
        $this->fakeHouseholds();

        Native::test(Households::class)->call('choose', 'household-2');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v1/households/switch/household-2'));
    }

    public function test_choosing_the_active_household_does_not_switch(): void
    {
        $this->connect();
        $this->fakeHouseholds();

        Native::test(Households::class)->call('choose', 'household-1');

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/households/switch/'));
    }

    public function test_a_failed_switch_keeps_the_user_on_the_screen_with_an_error(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana', 'members_count' => 3],
                ['id' => 'household-2', 'name' => 'Casa de la playa', 'members_count' => 1],
            ]]),
            '*/api/v1/households/switch/household-2' => Http::response([
                'message' => 'No podés cambiar a ese hogar.',
            ], 403),
        ]);

        $screen = Native::test(Households::class)->call('choose', 'household-2');

        // Sin esto el usuario vuelve a Listas creyendo que cambió de hogar.
        $this->assertNotNull($screen->get('error'));
    }

    public function test_without_a_session_it_asks_to_sign_in(): void
    {
        $this->connect(token: null);
        Http::fake();

        Native::test(Households::class)->assertSet('needsLogin', true);

        Http::assertNothingSent();
    }
}
