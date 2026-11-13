<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\EconomyService;
use App\Services\Homeside\HouseholdsService;
use App\Support\Concerns\HasHouseholdSwitcher;
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
    use HasHouseholdSwitcher;

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

    /** Ámbito elegido: `home` sigue al hogar activo, `personal` usa /economy/me. */
    public string $scope = 'home';

    /** @var list<array<string, mixed>> */
    public array $movements = [];

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
            $households = app(HouseholdsService::class);
            $activeId = $households->activeId();

            // Sin hogar no hay nada que elegir: el ámbito personal es el único.
            if ($activeId === null) {
                $this->scope = 'personal';
            }

            $householdId = $this->scope === 'home' ? $activeId : null;
            $this->householdScope = $householdId !== null;

            // El módulo apagado responde 404 (`EnsureModuleEnabled` hace
            // `abort(404, ...)`), indistinguible de "el hogar no existe". Por eso
            // se pregunta antes: el dashboard ya dice qué módulos están prendidos.
            if ($householdId !== null) {
                $enabled = $households->dashboard($householdId)['enabled_modules'] ?? [];

                if (is_array($enabled) && ! in_array('economy', $enabled, true)) {
                    $this->moduleDisabled = true;

                    return;
                }
            }

            $economy = new EconomyService;
            $overview = $economy->overview($householdId);

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

            $movements = $economy->transactions($householdId, ['perPage' => 20]);

            $this->movements = array_map(fn (array $movement): array => [
                'id' => (string) ($movement['id'] ?? ''),
                'title' => is_string($movement['title'] ?? null) && $movement['title'] !== ''
                    ? $movement['title']
                    : (string) __('app.expenses.no_title'),
                'amount' => Money::format(
                    (int) ($movement['amount_minor'] ?? 0),
                    is_string($movement['currency'] ?? null) ? $movement['currency'] : $this->currency,
                ),
                'type' => is_string($movement['type'] ?? null) ? $movement['type'] : 'expense',
                'date' => is_string($movement['occurred_at'] ?? null)
                    ? substr($movement['occurred_at'], 0, 10)
                    : '',
                'income' => ($movement['type'] ?? null) === 'income',
            ], array_values(array_filter($movements, 'is_array')));
        } catch (HomesideException $exception) {
            $this->needsLogin = $exception->requiresLogin();

            // Red de seguridad: si el módulo se apaga entre la consulta y la
            // llamada, la API responde 403/404 igual.
            $this->moduleDisabled = $exception->status === 403 || $exception->status === 404;
            $this->error = $this->moduleDisabled ? null : $exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function useScope(string $scope): void
    {
        if (! in_array($scope, ['home', 'personal'], true)) {
            return;
        }

        $this->scope = $scope;
        $this->load();
    }

    public function newExpense(): void
    {
        $this->navigate('/expenses/new?scope='.$this->scope);
    }

    public function openExpense(string $transactionId): void
    {
        $this->navigate('/expenses/'.$transactionId.'?scope='.$this->scope);
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
