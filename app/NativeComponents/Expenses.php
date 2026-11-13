<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\EconomyService;
use App\Services\Homeside\HouseholdsService;
use App\Support\Money;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Gastos: quinta pestaña. Totales del mes y en qué se fue la plata.
 *
 * Usa la economía del **hogar activo**; sin hogar cae a la personal
 * (`/economy/me`). El módulo `economy` puede estar apagado en el hogar, y en ese
 * caso la API responde 403: se muestra un estado explicativo, no un error.
 */
final class Expenses extends NativeComponent
{
    public bool $loading = true;

    public bool $needsLogin = false;

    public ?string $error = null;

    public bool $moduleDisabled = false;

    public string $currency = 'EUR';

    public ?string $month = null;

    public string $expenses = '—';

    public string $income = '—';

    public string $balance = '—';

    /** @var list<array{place: string, total: string}> */
    public array $places = [];

    public bool $householdScope = false;

    public function mount(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $this->loading = true;
        $this->error = null;
        $this->moduleDisabled = false;

        try {
            $householdId = app(HouseholdsService::class)->activeId();
            $this->householdScope = $householdId !== null;

            $overview = (new EconomyService)->overview($householdId);

            $this->currency = is_string($overview['currency'] ?? null) ? $overview['currency'] : 'EUR';
            $this->month = is_string($overview['current_month'] ?? null) ? $overview['current_month'] : null;

            $totals = is_array($overview['totals'] ?? null) ? $overview['totals'] : [];

            $this->expenses = Money::format((int) ($totals['expenses_minor'] ?? 0), $this->currency);
            $this->income = Money::format((int) ($totals['income_minor'] ?? 0), $this->currency);
            $this->balance = Money::format((int) ($totals['balance_minor'] ?? 0), $this->currency);

            $places = is_array($overview['by_place'] ?? null) ? $overview['by_place'] : [];

            $this->places = array_map(fn (array $place): array => [
                'place' => is_string($place['place'] ?? null) && $place['place'] !== ''
                    ? $place['place']
                    : (string) __('app.expenses.no_place'),
                'total' => Money::format((int) ($place['total_minor'] ?? 0), $this->currency),
            ], array_slice(array_values(array_filter($places, 'is_array')), 0, 5));
        } catch (HomesideException $exception) {
            $this->needsLogin = $exception->requiresLogin();

            // El módulo apagado en el hogar llega como 403 con ese texto; no es un
            // fallo de la app y merece su propio estado.
            $this->moduleDisabled = $exception->status === 403;
            $this->error = $this->moduleDisabled ? null : $exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function goToLogin(): void
    {
        $this->navigate('/login');
    }

    public function navTitle(): string
    {
        return (string) __('app.tabs.economy');
    }

    public function render(): View
    {
        return view('native.expenses');
    }
}
