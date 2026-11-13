<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\HouseholdsService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Lista de hogares del usuario: el selector que abre la acción de la barra
 * superior.
 *
 * Tocar el hogar activo vuelve atrás; tocar otro lo cambia en el servidor y
 * vuelve a Listas, que es la pantalla por defecto. El refresco del resto de las
 * pestañas no hace falta acá: cada una resuelve el hogar activo en su `mount`.
 */
final class Households extends NativeComponent
{
    public bool $loading = true;

    public bool $needsLogin = false;

    public ?string $error = null;

    public string $activeId = '';

    /** @var list<array<string, mixed>> */
    public array $households = [];

    public function mount(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            $service = app(HouseholdsService::class);

            $this->activeId = $service->activeId() ?? '';

            $this->households = array_map(function (array $household) {
                $name = is_string($household['name'] ?? null) ? $household['name'] : '';
                $id = (string) ($household['id'] ?? '');

                return [
                    'id' => $id,
                    'name' => $name,
                    'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
                    'color' => is_string($household['color'] ?? null) ? $household['color'] : null,
                    'members' => (int) ($household['members_count'] ?? 0),
                    'active' => $id === $this->activeId,
                ];
            }, array_filter($service->all(), 'is_array'));
        } catch (HomesideException $exception) {
            $this->needsLogin = $exception->requiresLogin();
            $this->error = $exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function choose(string $householdId): void
    {
        if ($householdId === $this->activeId) {
            $this->back();

            return;
        }

        try {
            app(HouseholdsService::class)->switchTo($householdId);
        } catch (HomesideException $exception) {
            $this->needsLogin = $exception->requiresLogin();
            $this->error = $exception->getMessage();

            return;
        }

        $this->navigate('/lists');
    }

    public function goToLogin(): void
    {
        $this->navigate('/login');
    }

    public function navTitle(): string
    {
        return (string) __('app.households.title');
    }

    public function render(): View
    {
        return view('native.households');
    }
}
