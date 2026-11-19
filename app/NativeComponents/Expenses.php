<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\EconomyService;
use App\Services\Homeside\HouseholdsService;
use App\Services\Offline\LocalStore;
use App\Services\Offline\SyncEngine;
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

    public bool $offline = false;

    /** Ámbito elegido: `home` sigue al hogar activo, `personal` usa /economy/me. */
    public string $scope = 'home';

    /** @var list<array<string, mixed>> */
    public array $movements = [];

    /** Rango de la lista: mes actual, mes pasado, año o todo. */
    public string $range = 'month';

    public function mount(): void
    {
        $this->load();
    }

    public function refreshFromServer(): void
    {
        $this->offline = app(SyncEngine::class)->run()['offline'];
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

            $store = app(LocalStore::class);
            $cached = $store->all(LocalStore::TRANSACTIONS);

            $movements = $cached !== []
                ? $this->filterCached($cached, $householdId)
                : $economy->transactions($householdId, [
                    'perPage' => 30,
                    ...$this->rangeQuery(),
                ]);

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

    /**
     * Aplica el ámbito y el rango sobre el espejo, donde no hay servidor que
     * filtre. Mismo criterio que la query: fecha descendente y tope de 30.
     *
     * @param  array<int, array<string, mixed>>  $cached
     * @return array<int, array<string, mixed>>
     */
    private function filterCached(array $cached, ?string $householdId): array
    {
        $range = $this->rangeQuery();
        $from = $range['from'] ?? null;
        $to = $range['to'] ?? null;

        $filtered = array_values(array_filter($cached, function (array $row) use ($householdId, $from, $to): bool {
            $scopeOk = $householdId === null
                ? ($row['household_id'] ?? null) === null
                : ($row['household_id'] ?? null) === $householdId;

            if (! $scopeOk) {
                return false;
            }

            $date = is_string($row['occurred_at'] ?? null) ? substr($row['occurred_at'], 0, 10) : null;

            if ($date === null) {
                return true;
            }

            if ($from !== null && $date < $from) {
                return false;
            }

            return ! ($to !== null && $date > $to);
        }));

        usort($filtered, fn (array $a, array $b): int => strcmp(
            (string) ($b['occurred_at'] ?? ''),
            (string) ($a['occurred_at'] ?? ''),
        ));

        return array_slice($filtered, 0, 30);
    }

    /**
     * @return array<string, string>
     */
    private function rangeQuery(): array
    {
        $today = now();

        return match ($this->range) {
            'last' => [
                'from' => $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'to' => $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'year' => [
                'from' => $today->copy()->startOfYear()->toDateString(),
                'to' => $today->copy()->endOfYear()->toDateString(),
            ],
            // 'month' y 'all': la lista sólo muestra lo que el servidor ordena por
            // fecha; acotar a mes actual evita traer años de historial.
            'month' => [
                'from' => $today->copy()->startOfMonth()->toDateString(),
                'to' => $today->copy()->endOfMonth()->toDateString(),
            ],
            default => [],
        };
    }

    public function useRange(string $range): void
    {
        if (! in_array($range, ['month', 'last', 'year', 'all'], true)) {
            return;
        }

        $this->range = $range;
        $this->load();
    }

    public function useScope(string $scope): void
    {
        if (! in_array($scope, ['home', 'personal'], true)) {
            return;
        }

        $this->scope = $scope;
        $this->load();
    }

    public function scanTicket(): void
    {
        $this->navigate('/expenses/ticket?scope='.$this->scope);
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
