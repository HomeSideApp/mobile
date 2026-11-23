<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AuthService;
use App\Services\Homeside\EconomyService;
use App\Services\Homeside\HouseholdsService;
use App\Services\Media\GalleryPicker;
use App\Services\Offline\PendingImports;
use App\Support\Money;
use Illuminate\Support\Str;
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

    /**
     * El ticket no se pudo leer con IA porque el proveedor no corre en el
     * servidor del usuario y todavía no dio permiso. No es un error: es una
     * pregunta, y el import queda esperando la respuesta.
     */
    public bool $consentRequired = false;

    /** Nombre del proveedor, para que la pregunta sea concreta. */
    public ?string $consentProvider = null;

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
            $this->busy = false;

            // Sin red el ticket no se pierde: la foto queda en la cola y se sube
            // cuando vuelva la conexión. `imports` no viaja por /sync/push.
            if ($exception->reason === HomesideException::NETWORK) {
                app(PendingImports::class)->enqueue($contents, 'ticket.jpg', $this->householdId());
                $this->status = 'offline';
                $this->error = null;

                return;
            }

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

        if ($this->consentRequired) {
            return;
        }

        try {
            $this->applyImport((new EconomyService)->importStatus($this->householdId(), $this->importId));
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    /**
     * Permite que la IA lea este ticket fuera del servidor y relanza el análisis.
     *
     * El ticket quedó intacto esperando esta decisión: sin el permiso nunca salió
     * del servidor. Se guarda en el perfil, así que vale para los próximos y se
     * puede revocar desde ahí.
     */
    public function allowCloudAi(): void
    {
        if ($this->importId === null || $this->busy) {
            return;
        }

        $this->busy = true;
        $this->error = null;

        try {
            (new AuthService)->allowCloudAi(true);

            $import = (new EconomyService)->retryImport(
                $this->householdId(),
                $this->importId,
                (string) Str::uuid(),
            );

            $this->consentRequired = false;
            $this->status = 'processing';

            if ($import !== []) {
                $this->applyImport($import);
            }
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
        } finally {
            $this->busy = false;
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

        $error = is_array($import['error'] ?? null) ? $import['error'] : [];
        $code = is_string($error['code'] ?? null) ? $error['code'] : null;

        $this->consentRequired = $code === 'ai_consent_required';

        if ($this->consentRequired) {
            // Se resuelve con una pregunta, no con un mensaje de error rojo.
            $this->consentProvider = is_string($error['provider'] ?? null) ? $error['provider'] : null;
            $this->error = null;
        } elseif (is_string($error['message'] ?? null)) {
            $this->error = $error['message'];
        }

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
