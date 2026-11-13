<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\EconomyService;
use App\Services\Homeside\HouseholdsService;
use App\Support\Money;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Alta y edición de un movimiento.
 *
 * El dinero se escribe en decimal (`12,50` o `12.50`) y se convierte a unidades
 * menores antes de salir: la app nunca manda floats.
 *
 * El alta lleva `idempotency key` para que un doble toque no cree dos gastos.
 */
final class ExpenseForm extends NativeComponent
{
    public ?string $transactionId = null;

    public string $type = 'expense';

    public string $title = '';

    public string $amount = '';

    public string $occurredAt = '';

    public string $place = '';

    public string $notes = '';

    public string $currency = 'EUR';

    public string $scope = 'home';

    public bool $loading = true;

    public bool $saving = false;

    public bool $confirmDelete = false;

    public ?string $error = null;

    public function mount(): void
    {
        $this->transactionId = $this->param('id');
        $this->scope = (string) ($this->data('scope', 'home') ?? 'home');
        $this->occurredAt = now()->toDateString();

        $this->load();
    }

    private function householdId(): ?string
    {
        if ($this->scope !== 'home') {
            return null;
        }

        return app(HouseholdsService::class)->activeId();
    }

    private function load(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            $economy = new EconomyService;
            $householdId = $this->householdId();

            $overview = $economy->overview($householdId);
            $this->currency = is_string($overview['currency'] ?? null) ? $overview['currency'] : 'EUR';

            if ($this->transactionId === null || $this->transactionId === '') {
                return;
            }

            $transaction = $economy->transaction($householdId, $this->transactionId);

            $this->type = is_string($transaction['type'] ?? null) ? $transaction['type'] : 'expense';
            $this->title = is_string($transaction['title'] ?? null) ? $transaction['title'] : '';
            $this->place = is_string($transaction['place'] ?? null) ? $transaction['place'] : '';
            $this->notes = is_string($transaction['notes'] ?? null) ? $transaction['notes'] : '';
            $this->currency = is_string($transaction['currency'] ?? null) ? $transaction['currency'] : $this->currency;

            $minor = (int) ($transaction['amount_minor'] ?? 0);
            $this->amount = number_format($minor / 100, 2, '.', '');

            $occurred = $transaction['occurred_at'] ?? null;
            $this->occurredAt = is_string($occurred) ? substr($occurred, 0, 10) : $this->occurredAt;
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function setType(string $type): void
    {
        if (in_array($type, ['expense', 'income'], true)) {
            $this->type = $type;
        }
    }

    public function save(): void
    {
        $this->error = null;

        $title = trim($this->title);

        if ($title === '') {
            $this->error = (string) __('app.expense_form.title_required');

            return;
        }

        $minor = Money::toMinor($this->amount);

        if ($minor === null || $minor <= 0) {
            $this->error = (string) __('app.expense_form.amount_invalid');

            return;
        }

        $householdId = $this->householdId();

        // La API de economía trabaja en **unidades menores** (`amount_minor`) y,
        // en un gasto compartido, exige `participants`. Yo mandaba `amount` y sin
        // participantes, así que el servidor respondía 422.
        $payload = [
            'type' => $this->type,
            'scope' => $householdId !== null ? 'shared' : 'personal',
            'currency' => $this->currency,
            'title' => $title,
            'amount_minor' => $minor,
            'occurred_at' => $this->occurredAt !== '' ? $this->occurredAt : null,
            'place' => trim($this->place) !== '' ? trim($this->place) : null,
            'notes' => trim($this->notes) !== '' ? trim($this->notes) : null,
        ];

        if ($householdId !== null) {
            $memberId = app(HouseholdsService::class)->myMemberId($householdId);

            if ($memberId === null) {
                $this->error = (string) __('app.expense_form.member_missing');

                return;
            }

            // Un solo participante con reparto igual: el importe entero cae en
            // quien lo cargó, que es lo que significa "lo pagué yo".
            $payload['participants'] = [
                ['household_member_id' => $memberId, 'split_type' => 'equal'],
            ];
        }

        $this->saving = true;

        try {
            $economy = new EconomyService;

            if ($this->transactionId === null || $this->transactionId === '') {
                // La clave sale del contenido + la fecha: dos toques del mismo
                // formulario colapsan en un solo movimiento.
                $key = 'expense:'.md5($householdId.'|'.$title.'|'.$minor.'|'.$this->occurredAt.'|'.$this->type);

                $economy->store($householdId, $payload, $key);
            } else {
                $economy->update($householdId, $this->transactionId, $payload);
            }
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();

            return;
        } finally {
            $this->saving = false;
        }

        $this->back();
    }

    public function askDelete(): void
    {
        $this->confirmDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmDelete = false;
    }

    public function delete(): void
    {
        if ($this->transactionId === null || $this->transactionId === '') {
            return;
        }

        $this->error = null;

        try {
            (new EconomyService)->destroy($this->householdId(), $this->transactionId);
        } catch (HomesideException $exception) {
            $this->confirmDelete = false;
            $this->error = $exception->getMessage();

            return;
        }

        $this->confirmDelete = false;
        $this->back();
    }

    public function navTitle(): string
    {
        return $this->transactionId === null
            ? (string) __('app.expense_form.create_title')
            : (string) __('app.expense_form.edit_title');
    }

    public function render(): View
    {
        return view('native.expense-form');
    }
}
