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

    public function test_it_offers_the_users_accounts_and_sends_the_chosen_one(): void
    {
        $this->connect();
        $this->fakeEconomy();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [['id' => 'household-1', 'name' => 'Casa']]]),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1', 'name' => 'Casa',
                'members' => [['id' => 'member-1', 'user' => ['id' => 'user-1', 'name' => 'Ana']]],
            ]]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => ['enabled_modules' => ['economy']]]),
            '*/api/v1/households/household-1/economy/overview*' => Http::response(['data' => ['currency' => 'EUR']]),
            '*/api/v1/economy/me/accounts' => Http::response(['data' => [
                ['id' => 'account-1', 'name' => 'Cuenta corriente'],
            ]]),
            '*/api/v1/households/household-1/economy/transactions' => Http::response(['data' => ['id' => 't-1']], 201),
        ]);

        $screen = Native::test(ExpenseForm::class)
            ->set('title', 'Mercadona')
            ->set('amount', '9.99')
            ->call('setAccount', 'Cuenta corriente')
            ->call('save');

        $this->assertNull($screen->get('error'));
        $this->assertSame('account-1', $screen->get('accountId'));

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request['account_id'] === 'account-1');
    }

    public function test_participants_can_be_added_and_are_sent(): void
    {
        $this->connect();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [['id' => 'household-1', 'name' => 'Casa']]]),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1', 'name' => 'Casa',
                'members' => [
                    ['id' => 'member-1', 'user' => ['id' => 'user-1', 'name' => 'Ana']],
                    ['id' => 'member-2', 'user' => ['id' => 'user-2', 'name' => 'Beto']],
                ],
            ]]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => ['enabled_modules' => ['economy']]]),
            '*/api/v1/households/household-1/economy/overview*' => Http::response(['data' => ['currency' => 'EUR']]),
            '*/api/v1/economy/me/accounts' => Http::response(['data' => []]),
            '*/api/v1/households/household-1/economy/transactions' => Http::response(['data' => ['id' => 't-1']], 201),
        ]);

        $screen = Native::test(ExpenseForm::class)
            ->set('title', 'Cena')
            ->set('amount', '40.00')
            ->call('toggleParticipant', 'member-2')
            ->call('save');

        $this->assertNull($screen->get('error'));

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && array_column($request['participants'], 'household_member_id') === ['member-1', 'member-2']);
    }

    public function test_a_shared_expense_needs_at_least_one_participant_selected(): void
    {
        $this->connect();
        $this->fakeEconomy();

        $screen = Native::test(ExpenseForm::class)
            ->set('title', 'Cena')
            ->set('amount', '40.00')
            ->call('toggleParticipant', 'member-1')
            ->call('save');

        $this->assertNotNull($screen->get('error'));
        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST');
    }

    public function test_an_attached_ticket_is_sent_as_source_document(): void
    {
        $this->connect();
        $this->fakeEconomy();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [['id' => 'household-1', 'name' => 'Casa']]]),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1', 'name' => 'Casa',
                'members' => [['id' => 'member-1', 'user' => ['id' => 'user-1', 'name' => 'Ana']]],
            ]]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => ['enabled_modules' => ['economy']]]),
            '*/api/v1/households/household-1/economy/overview*' => Http::response(['data' => ['currency' => 'EUR']]),
            '*/api/v1/economy/me/accounts' => Http::response(['data' => []]),
            '*/api/v1/households/household-1/economy/documents' => Http::response(['data' => [
                'id' => 'document-1',
                'original_filename' => 'ticket.jpg',
            ]], 201),
            '*/api/v1/households/household-1/economy/transactions' => Http::response(['data' => ['id' => 't-1']], 201),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'doc').'.jpg';
        file_put_contents($path, str_repeat('x', 512));

        $screen = Native::test(ExpenseForm::class)
            ->set('title', 'Mercadona')
            ->set('amount', '9.99')
            ->call('onMediaSelected', true, [['path' => $path]]);

        $this->assertSame('document-1', $screen->get('documentId'));

        $screen->call('save');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && ($request['source_document_id'] ?? null) === 'document-1');
    }

    public function test_editing_attaches_the_ticket_to_the_movement(): void
    {
        $this->connect();

        // Un solo fake: acumular stubs deja el comodín anterior matcheando primero.
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [['id' => 'household-1', 'name' => 'Casa']]]),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1', 'name' => 'Casa',
                'members' => [['id' => 'member-1', 'user' => ['id' => 'user-1', 'name' => 'Ana']]],
            ]]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => ['enabled_modules' => ['economy']]]),
            '*/api/v1/households/household-1/economy/overview*' => Http::response(['data' => ['currency' => 'EUR']]),
            '*/api/v1/economy/me/accounts' => Http::response(['data' => []]),
            '*/api/v1/households/household-1/economy/transactions/transaction-1/attachments' => Http::response(['data' => [
                'id' => 'attachment-1',
                'original_filename' => 'ticket.jpg',
            ]], 201),
            '*/api/v1/households/household-1/economy/transactions/*' => Http::response(['data' => [
                'id' => 'transaction-1',
                'type' => 'expense',
                'title' => 'Mercadona',
                'amount_minor' => 1234,
                'currency' => 'EUR',
                'attachments' => [],
            ]]),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'doc').'.jpg';
        file_put_contents($path, str_repeat('x', 512));

        // Editando: el ticket va como adjunto del movimiento, no como documento
        // de origen (ése sólo tiene sentido al crear).
        $screen = Native::test(ExpenseForm::class, ['id' => 'transaction-1'])
            ->call('onMediaSelected', true, [['path' => $path]]);

        $this->assertContains('ticket.jpg', $screen->get('attachments'));

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/transactions/transaction-1/attachments'));
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
