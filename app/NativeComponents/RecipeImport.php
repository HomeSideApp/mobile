<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\RecipesService;
use App\Services\Settings\LocaleService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Importar una receta desde una URL (JSON-LD) o desde texto Cooklang.
 *
 * El servidor hace el trabajo: baja la página o parsea el Cooklang y devuelve
 * una receta normalizada. Acá sólo se elige el origen y se pasa el borrador al
 * formulario para revisarlo.
 */
final class RecipeImport extends NativeComponent
{
    /** page | cooklang */
    public string $mode = 'page';

    public string $value = '';

    public bool $working = false;

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(): void
    {
        (new LocaleService)->apply();
    }

    public function navTitle(): string
    {
        return __('app.recipes.import');
    }

    public function usePage(): void
    {
        $this->mode = 'page';
        $this->error = null;
    }

    public function useCooklang(): void
    {
        $this->mode = 'cooklang';
        $this->error = null;
    }

    public function run(): void
    {
        $value = trim($this->value);

        if ($value === '' || $this->working) {
            return;
        }

        $this->working = true;
        $this->error = null;
        $this->notice = null;

        $service = new RecipesService;

        try {
            $preview = $this->mode === 'cooklang'
                ? $service->previewCooklang($value)
                : $service->previewUrl($value);

            $recipe = is_array($preview['recipe'] ?? null) ? $preview['recipe'] : $preview;

            // El servidor normaliza y valida antes de guardar: si la receta
            // viene incompleta, se entera acá y no al crear el registro.
            $reviewed = $service->review($recipe);

            $draft = is_array($reviewed['recipe'] ?? null) ? $reviewed['recipe'] : $recipe;
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->working = false;

            return;
        }

        $this->working = false;

        $this->navigate('/recipes/create', [
            'draft' => base64_encode((string) json_encode($draft)),
        ]);
    }

    public function render(): View
    {
        return view('native.recipe-import');
    }
}
