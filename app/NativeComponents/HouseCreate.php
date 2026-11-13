<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\HouseholdsService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Crear un hogar: nombre, color y módulos.
 *
 * Los módulos vienen **prendidos por defecto** a propósito: un hogar sin
 * `shopping_lists` deja la primera pantalla respondiendo "This module is not
 * enabled for this household", que parece un bug aunque sea configuración válida.
 *
 * La API cambia el hogar activo sola al crear, así que después de un alta exitosa
 * se va a Listas: es la pantalla que el usuario quiere ver con su casa nueva.
 */
final class HouseCreate extends NativeComponent
{
    public string $name = '';

    public string $color = '#1E6B63';

    public bool $shoppingLists = true;

    public bool $recipes = true;

    public bool $economy = true;

    public bool $saving = false;

    public ?string $error = null;

    public ?string $errorReason = null;

    /** @return list<string> */
    public function colors(): array
    {
        return ['#1E6B63', '#B45309', '#7C3AED', '#0E7490', '#B91C1C'];
    }

    public function chooseColor(string $color): void
    {
        $this->color = $color;
        $this->error = null;
        $this->errorReason = null;
    }

    public function toggleModule(string $module): void
    {
        match ($module) {
            'shopping_lists' => $this->shoppingLists = ! $this->shoppingLists,
            'recipes' => $this->recipes = ! $this->recipes,
            'economy' => $this->economy = ! $this->economy,
            default => null,
        };
    }

    public function create(): void
    {
        $this->error = null;
        $this->errorReason = null;

        $name = trim($this->name);

        if ($name === '') {
            $this->error = (string) __('app.house_create.name_required');
            $this->errorReason = 'validation';

            return;
        }

        $this->saving = true;

        try {
            app(HouseholdsService::class)->create([
                'name' => $name,
                'color' => $this->color,
                'modules' => [
                    'shopping_lists' => $this->shoppingLists,
                    'recipes' => $this->recipes,
                    'economy' => $this->economy,
                ],
            ]);
        } catch (HomesideException $exception) {
            $this->errorReason = $exception->reason;
            $this->error = $exception->reason === HomesideException::QUOTA
                ? (string) __('app.house_create.quota')
                : $exception->getMessage();

            return;
        } finally {
            $this->saving = false;
        }

        $this->navigate('/lists');
    }

    public function navTitle(): string
    {
        return (string) __('app.house_create.title');
    }

    public function render(): View
    {
        return view('native.house-create');
    }
}
