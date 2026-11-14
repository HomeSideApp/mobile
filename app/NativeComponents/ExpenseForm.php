<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\EconomyService;
use App\Services\Homeside\HouseholdsService;
use App\Services\Media\GalleryPicker;
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

    public string $accountId = '';

    /** @var list<array{value: string, label: string}> */
    public array $accountOptions = [];

    /** @var list<array{id: string, name: string, selected: bool}> */
    public array $participants = [];

    public ?string $documentId = null;

    public ?string $documentName = null;

    public bool $uploading = false;

    /** @var list<string> */
    public array $attachments = [];

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

            $this->loadAccounts($economy);

            if ($householdId !== null) {
                $this->loadParticipants($householdId);
            }

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

            // Participantes guardados: se preseleccionan para no perder el reparto.
            $saved = is_array($transaction['participants'] ?? null) ? $transaction['participants'] : [];

            foreach ($saved as $participant) {
                $memberId = is_array($participant) && is_array($participant['household_member'] ?? null)
                    ? ($participant['household_member']['id'] ?? null)
                    : null;

                if (! is_string($memberId)) {
                    continue;
                }

                foreach ($this->participants as $index => $row) {
                    if ($row['id'] === $memberId) {
                        $this->participants[$index]['selected'] = true;
                    }
                }
            }

            $this->documentId = is_string($transaction['source_document_id'] ?? null)
                ? $transaction['source_document_id']
                : null;

            $attachments = is_array($transaction['attachments'] ?? null) ? $transaction['attachments'] : [];

            $this->attachments = array_values(array_filter(array_map(
                fn ($attachment): ?string => is_array($attachment) && is_string($attachment['original_filename'] ?? null)
                    ? $attachment['original_filename']
                    : null,
                $attachments,
            )));

            $account = is_array($transaction['account'] ?? null) ? $transaction['account'] : [];
            $this->accountId = is_string($account['id'] ?? null) ? $account['id'] : '';

            $occurred = $transaction['occurred_at'] ?? null;
            $this->occurredAt = is_string($occurred) ? substr($occurred, 0, 10) : $this->occurredAt;
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Miembros del hogar para repartir el gasto. Por defecto queda elegido uno
     * solo —quien carga—, que es el caso normal.
     */
    private function loadParticipants(string $householdId): void
    {
        $memberId = app(HouseholdsService::class)->myMemberId($householdId);
        $members = app(HouseholdsService::class)->find($householdId)['members'] ?? [];

        if (! is_array($members)) {
            return;
        }

        $this->participants = array_values(array_map(function (array $member) use ($memberId): array {
            $user = is_array($member['user'] ?? null) ? $member['user'] : [];
            $id = (string) ($member['id'] ?? '');

            return [
                'id' => $id,
                'name' => is_string($user['name'] ?? null) ? $user['name'] : '',
                'selected' => $memberId !== null && $id === $memberId,
            ];
        }, array_filter($members, 'is_array')));
    }

    public function toggleParticipant(string $memberId): void
    {
        foreach ($this->participants as $index => $participant) {
            if ($participant['id'] === $memberId) {
                $this->participants[$index]['selected'] = ! $participant['selected'];
            }
        }
    }

    public function attachTicket(): void
    {
        $this->error = null;

        if (! (new GalleryPicker)->pickImage()) {
            $this->error = (string) __('app.list_detail.photo_unavailable');
        }
    }

    /**
     * El documento se sube apenas se elige: el movimiento sólo guarda su id.
     */
    public function onMediaSelected(bool $success = false, array $files = []): void
    {
        if (! $success || $files === []) {
            return;
        }

        $first = $files[0] ?? null;
        $path = is_array($first) ? ($first['path'] ?? null) : $first;

        if (! is_string($path) || $path === '') {
            return;
        }

        $contents = @file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            $this->error = (string) __('app.list_detail.photo_failed');

            return;
        }

        $this->uploading = true;
        $economy = new EconomyService;
        $editing = $this->transactionId !== null && $this->transactionId !== '';

        try {
            if ($editing) {
                // Un movimiento ya existe: el ticket va como adjunto, que es lo
                // que hace la web (`POST transactions/{id}/attachments`).
                $attachment = $economy->attachToTransaction(
                    $this->householdId(),
                    (string) $this->transactionId,
                    $contents,
                    'ticket.jpg',
                );

                $this->attachments[] = is_string($attachment['original_filename'] ?? null)
                    ? $attachment['original_filename']
                    : (string) __('app.expense_form.ticket_attached');

                return;
            }

            $document = $economy->uploadDocument($this->householdId(), $contents, 'ticket.jpg');
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();

            return;
        } finally {
            $this->uploading = false;
        }

        $this->documentId = is_string($document['id'] ?? null) ? $document['id'] : null;
        $this->documentName = is_string($document['original_filename'] ?? null)
            ? $document['original_filename']
            : (string) __('app.expense_form.ticket_attached');
    }

    public function removeTicket(): void
    {
        $this->documentId = null;
        $this->documentName = null;
    }

    private function loadAccounts(EconomyService $economy): void
    {
        try {
            $accounts = $economy->accounts();
        } catch (HomesideException) {
            // Sin permiso de cuentas el campo simplemente no se muestra.
            return;
        }

        $this->accountOptions = array_values(array_map(fn (array $account): array => [
            'value' => (string) ($account['id'] ?? ''),
            'label' => (string) ($account['name'] ?? ''),
        ], array_filter($accounts, 'is_array')));
    }

    public function setAccount(?string $label = null): void
    {
        foreach ($this->accountOptions as $option) {
            if ($option['label'] === $label) {
                $this->accountId = $option['value'];

                return;
            }
        }

        $this->accountId = '';
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

        if ($this->accountId !== '') {
            $payload['account_id'] = $this->accountId;
        }

        if ($this->documentId !== null) {
            $payload['source_document_id'] = $this->documentId;
        }

        if ($householdId !== null) {
            $selected = array_values(array_filter($this->participants, fn (array $row): bool => $row['selected']));

            if ($selected === []) {
                $this->error = (string) __('app.expense_form.participants_required');

                return;
            }

            $payload['participants'] = array_map(fn (array $row): array => [
                'household_member_id' => $row['id'],
                'split_type' => 'equal',
            ], $selected);
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
