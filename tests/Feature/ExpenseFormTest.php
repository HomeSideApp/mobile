<?php

namespace Tests\Feature;

use App\NativeComponents\ExpenseForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * Alta, edición y borrado de movimientos.
 */
class ExpenseFormTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function fakeEconomy(array $overrides = []): void
    {
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => [
                'enabled_modules' => ['economy'],
            ]]),
            // La API pide la fila de miembro para `participants`.
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1',
                'name' => 'Casa',
                'members' => [
                    ['id' => 'member-1', 'user' => ['id' => 'user-1', 'name' => 'Ana']],
                ],
            ]]),
            '*/api/v1/households/household-1/economy/overview*' => Http::response(['data' => [
                'currency' => 'EUR',
                'current_month' => '2026-11',
                'totals' => [],
            ]]),
            '*/api/v1/households/household-1/economy/transactions' => Http::response(['data' => [
                'id' => 'transaction-1',
                ...$overrides,
            ]], 201),
            '*/api/v1/households/household-1/economy/transactions/*' => Http::response(['data' => [
                'id' => 'transaction-1',
                'type' => 'expense',
                'title' => 'Mercadona',
                'amount_minor' => 1234,
                'currency' => 'EUR',
                'occurred_at' => '2026-11-10T00:00:00+00:00',
                'place' => 'Centro',
                'notes' => 'Compra semanal',
            ]]),
        ]);
    }

    public function test_it_creates_an_expense_in_minor_units_with_an_idempotency_key(): void
    {
        $this->connect();
        $this->fakeEconomy();

        $screen = Native::test(ExpenseForm::class)
            ->set('title', 'Mercadona')
            ->set('amount', '12,34')
            ->set('place', 'Centro')
            ->set('occurredAt', '2026-11-10')
            ->call('save');

        $this->assertNull($screen->get('error'));

        $request = collect(Http::recorded())
            ->first(fn ($pair): bool => $pair[0]->method() === 'POST'
                && str_ends_with($pair[0]->url(), '/economy/transactions'))[0] ?? null;

        $this->assertNotNull($request, 'Se esperaba un POST de alta');

        // La API de economía trabaja en unidades menores: `amount_minor`, no `amount`.
        $this->assertSame(1234, $request['amount_minor']);
        $this->assertArrayNotHasKey('amount', $request->data());

        // Y un gasto compartido exige participantes: sin esto el servidor responde
        // 422 "The participants field is required".
        $this->assertSame(
            [['household_member_id' => 'member-1', 'split_type' => 'equal']],
            $request['participants'],
        );
        $this->assertSame('expense', $request['type']);
        $this->assertSame('shared', $request['scope']);

        // Sin clave de idempotencia, un doble toque crea dos gastos.
        $this->assertNotSame('', (string) $request->header('Idempotency-Key')[0] ?? '');
    }

    public function test_a_zero_amount_never_reaches_the_server(): void
    {
        $this->connect();
        $this->fakeEconomy();

        $screen = Native::test(ExpenseForm::class)
            ->set('title', 'Mercadona')
            ->set('amount', '0')
            ->call('save');

        $this->assertNotNull($screen->get('error'));
        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST');
    }

    public function test_it_loads_an_existing_movement_for_editing(): void
    {
        $this->connect();
        $this->fakeEconomy();

        $screen = Native::test(ExpenseForm::class, ['id' => 'transaction-1']);

        $this->assertSame('Mercadona', $screen->get('title'));
        $this->assertSame('12.34', $screen->get('amount'));
        $this->assertSame('2026-11-10', $screen->get('occurredAt'));
    }

    public function test_deleting_asks_for_confirmation_first(): void
    {
        $this->connect();
        $this->fakeEconomy();

        $screen = Native::test(ExpenseForm::class, ['id' => 'transaction-1'])
            ->call('askDelete');

        $this->assertTrue($screen->get('confirmDelete'));
        Http::assertNotSent(fn ($request): bool => $request->method() === 'DELETE');

        $screen->call('delete');

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/economy/transactions/transaction-1'));
    }
}
