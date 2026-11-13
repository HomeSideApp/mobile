<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\EconomyService;
use App\Services\Homeside\HouseholdsService;
use App\Services\Media\GalleryPicker;
use App\Support\Money;
use Illuminate\View\View;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;

/**
 * Ticket con OCR: elegir la foto, subirla, esperar el reconocimiento y confirmar.
 *
 * El servidor manda `poll_after_ms` mientras está `queued`/`processing`, así que
 * se sondea con `#[Poll]` y se corta solo cuando llega a `review_required`.
 * Nada se crea hasta confirmar: el borrador se descarta sin dejar rastro.
 */
final class ExpenseTicket extends NativeComponent
{
    public ?string $importId = null;

    public string $status = 'idle';

    public int $progress = 0;

    public bool $busy = false;

    public ?string $error = null;

    public string $scope = 'home';

    public string $currency = 'EUR';

    public int $amountMinor = 0;

    public string $total = '—';

    public ?string $place = null;

    public ?string $date = null;

    public ?string $title = null;

    /** @var list<string> */
    public array $warnings = [];

    public function mount(): void
    {
        $this->scope = (string) ($this->data('scope', 'home') ?? 'home');
    }

    private function householdId(): ?string
    {
        return $this->scope === 'home' ? app(HouseholdsService::class)->activeId() : null;
    }

    public function pickTicket(): void
    {
        $this->error = null;

        if (! (new GalleryPicker)->pickImage()) {
            $this->error = (string) __('app.list_detail.photo_unavailable');
        }
    }

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

        $this->busy = true;
        $this->status = 'processing';

        try {
            $import = (new EconomyService)->createImport($this->householdId(), $contents, 'ticket.jpg');
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->status = 'idle';

            return;
        } finally {
            $this->busy = false;
        }

        $this->importId = is_string($import['id'] ?? null) ? $import['id'] : null;
        $this->applyImport($import);
    }

    /**
     * Sondeo mientras el servidor procesa. Es barato: si ya hay borrador o hubo
     * error, vuelve enseguida.
     */
    #[Poll(1500)]
    public function refreshImport(): void
    {
        if ($this->importId === null || ! in_array($this->status, ['queued', 'processing'], true)) {
            return;
        }

        try {
            $this->applyImport((new EconomyService)->importStatus($this->householdId(), $this->importId));
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    /**
     * @param  array<string, mixed>  $import
     */
    private function applyImport(array $import): void
    {
        $this->status = is_string($import['status'] ?? null) ? $import['status'] : 'processing';
        $this->progress = (int) ($import['progress'] ?? 0);
        $this->warnings = is_array($import['warnings'] ?? null)
            ? array_values(array_filter($import['warnings'], 'is_string'))
            : [];

        $draft = is_array($import['draft'] ?? null) ? $import['draft'] : [];

        if ($draft === []) {
            return;
        }

        $this->currency = is_string($draft['currency'] ?? null) ? $draft['currency'] : $this->currency;
        $minor = (int) ($draft['amount_minor'] ?? 0);
        $this->amountMinor = $minor;
        $this->total = $minor > 0 ? Money::format($minor, $this->currency) : '—';
        $this->place = is_string($draft['place'] ?? null) ? $draft['place'] : null;
        $this->date = is_string($draft['occurred_at'] ?? null) ? substr($draft['occurred_at'], 0, 10) : null;
        $this->title = is_string($draft['title'] ?? null) ? $draft['title'] : null;
    }

    public function confirm(): void
    {
        if ($this->importId === null) {
            return;
        }

        $this->error = null;
        $this->busy = true;

        $householdId = $this->householdId();

        $payload = [
            'type' => 'expense',
            'scope' => $householdId !== null ? 'shared' : 'personal',
            'title' => $this->title ?? (string) __('app.ticket.default_title'),
            'place' => $this->place,
            'occurred_at' => $this->date,
            'currency' => $this->currency,
            'amount_minor' => $this->amountMinor,
        ];

        if ($householdId !== null) {
            $memberId = app(HouseholdsService::class)->myMemberId($householdId);

            if ($memberId === null) {
                $this->error = (string) __('app.expense_form.member_missing');
                $this->busy = false;

                return;
            }

            $payload['participants'] = [
                ['household_member_id' => $memberId, 'split_type' => 'equal'],
            ];
        }

        try {
            (new EconomyService)->confirmImport(
                $householdId,
                $this->importId,
                $payload,
                'import-confirm:'.$this->importId,
            );
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();

            return;
        } finally {
            $this->busy = false;
        }

        $this->navigate('/expenses');
    }

    public function discard(): void
    {
        if ($this->importId !== null) {
            try {
                (new EconomyService)->discardImport($this->householdId(), $this->importId);
            } catch (HomesideException) {
                // Descartar es best-effort: si falla, el servidor lo limpia solo.
            }
        }

        $this->importId = null;
        $this->status = 'idle';
        $this->total = '—';
        $this->place = null;
        $this->date = null;
        $this->title = null;
        $this->warnings = [];
    }

    public function navTitle(): string
    {
        return (string) __('app.ticket.title');
    }

    public function render(): View
    {
        return view('native.expense-ticket');
    }
}
