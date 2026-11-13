<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AppUpdateChecker;
use App\Services\Homeside\HouseholdsService;
use App\Services\Homeside\ListsService;
use App\Services\Homeside\Session;
use App\Services\Settings\LocaleService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Listas de la compra del usuario: las personales y las del hogar activo.
 *
 * Nota de alcance: la API todavía no devuelve contadores por lista (el web los
 * arma con `withCount`), así que acá se muestra el nombre y el ámbito. Cuando
 * entre `GET /v1/lists` unificado (T-90) esta pantalla suma pendientes.
 */
final class Lists extends NativeComponent
{
    /** @var array<int, array<string, mixed>> */
    public array $personal = [];

    /** @var array<int, array<string, mixed>> */
    public array $householdLists = [];

    /** @var array<int, array<string, mixed>> */
    public array $households = [];

    public ?string $activeHouseholdId = null;

    public ?string $activeHouseholdName = null;

    public string $search = '';

    public bool $loading = true;

    public bool $needsLogin = false;

    public ?string $error = null;

    public bool $updateAvailable = false;

    public ?string $updateVersion = null;

    public ?string $updateUrl = null;

    public function mount(): void
    {
        (new LocaleService)->apply();

        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    public function refresh(): void
    {
        $this->load();
    }

    public function navTitle(): string
    {
        return __('app.lists.title');
    }

    public function onSearch(?string $value = null): void
    {
        if (is_string($value)) {
            $this->search = $value;
        }

        $this->load();
    }

    public function openList(string $listId): void
    {
        $this->navigate('/lists/'.$listId);
    }

    public function openHouseholdList(string $listId): void
    {
        if ($this->activeHouseholdId === null) {
            return;
        }

        $this->navigate('/households/'.$this->activeHouseholdId.'/lists/'.$listId);
    }

    public function createList(): void
    {
        $this->navigate('/lists/create');
    }

    public function goToLogin(): void
    {
        $this->navigate('/login');
    }

    /**
     * La descarga y el instalador viven en Perfil: desde acá sólo se navega.
     */
    public function openUpdate(): void
    {
        $this->navigate('/profile');
    }

    /**
     * Sólo lee el último chequeo: acá no se sale a la red.
     */
    private function loadUpdateBanner(): void
    {
        $checker = new AppUpdateChecker;
        $status = $checker->lastKnownStatus();

        $this->updateAvailable = $checker->hasUpdate($status);
        $this->updateVersion = is_string($status['latest_version'] ?? null) ? $status['latest_version'] : null;
        $this->updateUrl = $this->updateAvailable && is_string($status['apk_url'] ?? null)
            ? $status['apk_url']
            : null;
    }

    public function goToServerSetup(): void
    {
        $this->navigate('/server-setup');
    }

    private function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $this->loadUpdateBanner();

        if (! (new Session)->isAuthenticated()) {
            $this->needsLogin = true;
            $this->loading = false;

            return;
        }

        $this->needsLogin = false;

        $filters = ['perPage' => 100];

        if (trim($this->search) !== '') {
            $filters['search'] = trim($this->search);
        }

        try {
            $lists = new ListsService;
            $households = new HouseholdsService;

            $this->households = $households->all();
            $this->activeHouseholdId = $households->activeId();
            $this->activeHouseholdName = $households->name($this->activeHouseholdId);
            $this->personal = $this->normalizeLists($lists->personal($filters));

            $this->householdLists = $this->activeHouseholdId !== null
                ? $this->normalizeLists($lists->household($this->activeHouseholdId, $filters))
                : [];
        } catch (HomesideException $exception) {
            $this->needsLogin = $exception->requiresLogin();
            $this->error = $exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lists
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLists(array $lists): array
    {
        return array_map(function (array $list): array {
            $household = is_array($list['household'] ?? null) ? $list['household'] : null;

            return [
                'id' => (string) ($list['id'] ?? ''),
                'name' => (string) ($list['name'] ?? 'Lista'),
                'household_id' => is_string($list['household_id'] ?? null) ? $list['household_id'] : null,
                'household_name' => is_string($household['name'] ?? null) ? $household['name'] : null,
                'updated_at' => is_string($list['updated_at'] ?? null) ? $list['updated_at'] : null,
            ];
        }, $lists);
    }

    public function render(): View
    {
        return view('native.lists');
    }
}
