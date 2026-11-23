<?php

namespace App\Services\Media;

use App\Services\Homeside\RecipesService;
use App\Services\Homeside\Session;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Fotos de recetas y de pasos.
 *
 * Igual que las imágenes de ítems: el endpoint exige `auth:sanctum` y
 * `<native:image>` no manda headers, así que se descargan con el token y se le
 * pasa al renderer una ruta `file://`.
 */
final class RecipeImageCache
{
    public function __construct(
        private readonly Session $session = new Session,
        private readonly ?string $directory = null,
    ) {}

    /**
     * Portada de la receta, o `null` si no hay.
     */
    public function cover(string $recipeId, ?string $version = null): ?string
    {
        return $this->resolve('cover', $recipeId, null, '/recipes/'.$recipeId.'/image', $version);
    }

    /**
     * Foto de un paso.
     */
    public function step(string $recipeId, string $stepId, ?string $version = null): ?string
    {
        return $this->resolve(
            'step',
            $recipeId,
            $stepId,
            '/recipes/'.$recipeId.'/steps/'.$stepId.'/image',
            $version,
        );
    }

    private function resolve(string $kind, string $recipeId, ?string $stepId, string $path, ?string $version): ?string
    {
        $file = $this->directory().'/'.$this->key($kind, $recipeId, $stepId, $version);

        if (! File::exists($file)) {
            try {
                $contents = (new RecipesService)->imageContents($path);
            } catch (Throwable) {
                // Sin red o sin imagen: la pantalla muestra el respaldo.
                return null;
            }

            File::ensureDirectoryExists($this->directory());

            if (File::put($file, $contents) === false) {
                return null;
            }
        }

        return 'file://'.$file;
    }

    private function key(string $kind, string $recipeId, ?string $stepId, ?string $version): string
    {
        $suffix = $version !== null ? '-'.md5($version) : '';

        return $kind.'-'.md5($recipeId.'|'.($stepId ?? '')).$suffix.'.img';
    }

    private function directory(): string
    {
        return $this->directory ?? storage_path('app/recipe-images');
    }
}
