<?php

namespace Tests\Feature;

use App\NativeComponents\Expenses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * Gastos: quinta pestaña. Totales del mes del hogar activo, o personales si no
 * hay hogar.
 */
class ExpensesScreenTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function fakeOverview(?string $path = null, array $totals = []): void
    {
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
            '*/api/v1/households/household-1/economy/overview*' => Http::response(['data' => [
                'currency' => 'EUR',
                'current_month' => '2026-11',
                'totals' => [
                    'expenses_minor' => 123456,
                    'income_minor' => 200000,
                    'balance_minor' => 76544,
                    ...$totals,
                ],
                'by_place' => [
                    ['place' => 'Mercadona', 'total_minor' => 98765],
                    ['place' => '', 'total_minor' => 1000],
                ],
            ]]),
            '*/api/v1/economy/me/overview*' => Http::response(['data' => [
                'currency' => 'EUR',
                'current_month' => '2026-11',
                'totals' => ['expenses_minor' => 5000, 'income_minor' => 0, 'balance_minor' => -5000],
                'by_place' => [],
            ]]),
        ]);
    }

    public function test_it_shows_the_household_totals_formatted(): void
    {
        $this->connect();
        $this->fakeOverview();

        $screen = Native::test(Expenses::class);

        // La API manda unidades menores: 123456 → 1.234,56 (es-ES).
        $this->assertSame('1.234,56 EUR', $screen->get('expenses'));
        $this->assertSame('2.000,00 EUR', $screen->get('income'));
        $this->assertSame('765,44 EUR', $screen->get('balance'));
        $this->assertTrue($screen->get('householdScope'));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/households/household-1/economy/overview'));
    }

    public function test_it_lists_where_the_money_went(): void
    {
        $this->connect();
        $this->fakeOverview();

        $places = Native::test(Expenses::class)->get('places');

        $this->assertSame('Mercadona', $places[0]['place']);
        $this->assertSame('987,65 EUR', $places[0]['total']);
        // Un ticket sin local no se muestra vacío.
        $this->assertSame(__('app.expenses.no_place'), $places[1]['place']);
    }

    public function test_without_a_household_it_falls_back_to_personal(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => null])]),
            '*/api/v1/households' => Http::response(['data' => []]),
            '*/api/v1/economy/me/overview*' => Http::response(['data' => [
                'currency' => 'EUR',
                'current_month' => '2026-11',
                'totals' => ['expenses_minor' => 5000, 'income_minor' => 0, 'balance_minor' => -5000],
                'by_place' => [],
            ]]),
        ]);

        $screen = Native::test(Expenses::class);

        $this->assertFalse($screen->get('householdScope'));
        $this->assertSame('-50,00 EUR', $screen->get('balance'));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/economy/me/overview'));
    }

    public function test_a_disabled_module_is_not_shown_as_an_error(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
            '*/api/v1/households/household-1/economy/overview*' => Http::response([
                'message' => 'This module is not enabled for this household',
            ], 403),
        ]);

        $screen = Native::test(Expenses::class);

        $this->assertTrue($screen->get('moduleDisabled'));
        $this->assertNull($screen->get('error'));
    }

    public function test_without_a_session_it_asks_to_sign_in(): void
    {
        $this->connect(token: null);
        Http::fake();

        Native::test(Expenses::class)->assertSet('needsLogin', true);

        Http::assertNothingSent();
    }
}
