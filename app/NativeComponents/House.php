<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\HouseholdsService;
use App\Services\Settings\LocaleService;
use App\Support\Concerns\HasHouseholdSwitcher;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Hogar activo: quiénes viven ahí, cómo viene la semana y qué módulos están
 * habilitados. Todo de lectura; cambiar de hogar (T-96), invitaciones (T-97) e
 * invitar (T-98) llegan en tareas aparte.
 *
 * Los módulos habilitados vienen del dashboard del hogar y las etiquetas del
 * catálogo del servidor, así no hay que hardcodear `economy`/`recipes` acá: si
 * mañana aparece otro módulo, se muestra solo.
 */
final class House extends NativeComponent
{
    use HasHouseholdSwitcher;

    public bool $loading = true;

    public bool $needsLogin = false;

    public ?string $error = null;

    public ?string $householdId = null;

    public string $householdName = '';

    public string $householdInitial = '';

    public ?string $householdDescription = null;

    public ?string $householdColor = null;

    public int $membersCount = 0;

    public ?string $since = null;

    public int $pendingCount = 0;

    public int $listsCount = 0;

    public int $recipesCount = 0;

    /** @var list<array<string, mixed>> */
    public array $members = [];

    /** @var list<array{key: string, label: string, enabled: bool}> */
    public array $modules = [];

    /** @var list<string> */
    private array $enabledModules = [];

    public function mount(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            (new LocaleService)->apply();

            $households = app(HouseholdsService::class);
            $active = $households->active();

            if ($active === null) {
                // Sin hogar activo: la pantalla queda en estado vacío, no en error.
                return;
            }

            $this->householdId = (string) $active['id'];

            $this->applyHousehold($households->find($this->householdId));
            $this->applyDashboard($households->dashboard($this->householdId));
            $this->applyModules($households->moduleCatalog(), $this->enabledModules);
        } catch (HomesideException $exception) {
            $this->needsLogin = $exception->requiresLogin();
            $this->error = $exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    /**
     * @param  array<string, mixed>  $household
     */
    private function applyHousehold(array $household): void
    {
        $this->householdName = (string) ($household['name'] ?? '');
        $this->householdInitial = mb_strtoupper(mb_substr($this->householdName, 0, 1));
        $this->householdDescription = is_string($household['description'] ?? null)
            ? $household['description']
            : null;
        $this->householdColor = is_string($household['color'] ?? null) ? $household['color'] : null;
        $this->membersCount = (int) ($household['members_count'] ?? 0);
        $this->since = $this->month($household['created_at'] ?? null);

        $members = is_array($household['members'] ?? null) ? $household['members'] : [];

        $this->members = array_values(array_map(function (array $member): array {
            $user = is_array($member['user'] ?? null) ? $member['user'] : [];
            $name = is_string($user['name'] ?? null) && $user['name'] !== ''
                ? $user['name']
                : __('app.house.unknown_member');

            $role = is_string($member['role'] ?? null) ? $member['role'] : 'member';

            return [
                'id' => (string) ($member['id'] ?? ''),
                'name' => $name,
                'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
                'email' => is_string($user['email'] ?? null) ? $user['email'] : '',
                'role' => $role,
                'role_label' => (string) __('app.house.role_'.$role),
                'joined' => $this->month($member['joined_at'] ?? null),
            ];
        }, array_filter($members, 'is_array')));

        if ($this->membersCount === 0) {
            $this->membersCount = count($this->members);
        }
    }

    /**
     * @param  array<string, mixed>  $dashboard
     */
    private function applyDashboard(array $dashboard): void
    {
        $this->pendingCount = (int) ($dashboard['pending_items_count'] ?? 0);
        $this->listsCount = (int) ($dashboard['active_lists_count'] ?? 0);
        $this->recipesCount = (int) ($dashboard['household_recipes_count'] ?? 0);

        $enabled = is_array($dashboard['enabled_modules'] ?? null) ? $dashboard['enabled_modules'] : [];

        $this->enabledModules = array_values(array_filter($enabled, 'is_string'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalog
     * @param  list<string>  $enabled
     */
    private function applyModules(array $catalog, array $enabled): void
    {
        $this->modules = array_values(array_map(function (array $module) use ($enabled): array {
            $key = (string) ($module['key'] ?? '');

            return [
                'key' => $key,
                'label' => $this->moduleLabel($key, $module['label'] ?? null),
                'enabled' => in_array($key, $enabled, true),
            ];
        }, $catalog));
    }

    /**
     * El catálogo del servidor devuelve etiquetas ya traducidas… salvo cuando
     * `__()` no resuelve la clave y viaja cruda (`households.modules.recipes_label`).
     * Se usa la traducción de la app cuando la hay, y si no se humaniza la clave.
     */
    private function moduleLabel(string $key, mixed $fromServer): string
    {
        $translated = 'app.modules.'.$key;

        if ($key !== '' && trans()->has($translated)) {
            return (string) __($translated);
        }

        if (is_string($fromServer) && $fromServer !== '' && ! str_contains($fromServer, '.')) {
            return $fromServer;
        }

        return $key === '' ? '' : (string) str($key)->replace('_', ' ')->title();
    }

    private function month(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->translatedFormat('M Y');
        } catch (\Throwable) {
            return null;
        }
    }

    public function goToLogin(): void
    {
        $this->navigate('/login');
    }

    public function navTitle(): string
    {
        return (string) __('app.house.title');
    }

    public function render(): View
    {
        return view('native.house');
    }
}
