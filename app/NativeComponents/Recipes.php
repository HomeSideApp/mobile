<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\HouseholdsService;
use App\Services\Homeside\RecipesService;
use App\Services\Media\RecipeImageCache;
use App\Services\Settings\LocaleService;
use App\Support\Concerns\HasHouseholdSwitcher;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Recetario personal y compartido.
 *
 * Lista lo propio y lo del hogar activo, y es la puerta a crear, importar,
 * generar con IA y cocinar.
 */
final class Recipes extends NativeComponent
{
    use HasHouseholdSwitcher;

    /** @var array<int, array<string, mixed>> */
    public array $personal = [];

    /** @var array<int, array<string, mixed>> */
    public array $household = [];

    public bool $loading = true;

    public bool $needsLogin = false;

    public ?string $error = null;

    public ?string $activeHouseholdId = null;

    public ?string $activeHouseholdName = null;

    public function mount(): void
    {
        (new LocaleService)->apply();

        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    public function navTitle(): string
    {
        return __('app.recipes.title');
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            $households = app(HouseholdsService::class);
            $this->activeHouseholdId = $households->activeId();
            $this->activeHouseholdName = $households->name($this->activeHouseholdId);

            $service = new RecipesService;
            $images = new RecipeImageCache;

            $this->personal = array_map(
                fn (array $recipe): array => $this->normalize($recipe, $images),
                $service->personal(),
            );

            $this->household = $this->activeHouseholdId !== null
                ? array_map(
                    fn (array $recipe): array => $this->normalize($recipe, $images),
                    $service->household($this->activeHouseholdId),
                )
                : [];
        } catch (HomesideException $exception) {
            $this->needsLogin = $exception->requiresLogin();
            $this->error = $exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @return array<string, mixed>
     */
    private function normalize(array $recipe, RecipeImageCache $images): array
    {
        $id = (string) ($recipe['id'] ?? '');
        $total = is_numeric($recipe['total_time_seconds'] ?? null) ? (int) $recipe['total_time_seconds'] : 0;
        $servings = is_numeric($recipe['servings'] ?? null) ? (int) $recipe['servings'] : 0;

        return [
            'id' => $id,
            'name' => is_string($recipe['name'] ?? null) ? $recipe['name'] : '—',
            'description' => is_string($recipe['description'] ?? null) ? $recipe['description'] : null,
            'image_url' => $images->cover($id, is_string($recipe['updated_at'] ?? null) ? $recipe['updated_at'] : null),
            'time' => $total > 0 ? $this->duration($total) : null,
            'servings' => $servings > 0 ? $servings : null,
            'difficulty' => is_string($recipe['difficulty'] ?? null) ? $recipe['difficulty'] : null,
        ];
    }

    private function duration(int $seconds): string
    {
        $minutes = (int) round($seconds / 60);

        if ($minutes < 60) {
            return $minutes.'′';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? $hours.' h' : $hours.' h '.$rest.'′';
    }

    public function open(string $recipeId): void
    {
        $this->navigate('/recipes/'.$recipeId);
    }

    public function create(): void
    {
        $this->navigate('/recipes/create');
    }

    /**
     * Abre el formulario con el prompt para que la IA lo complete ahí mismo.
     */
    public function generateWithAi(): void
    {
        $this->navigate('/recipes/create', ['mode' => 'ai']);
    }

    public function import(): void
    {
        $this->navigate('/recipes/import');
    }

    public function goToLogin(): void
    {
        $this->navigate('/login');
    }

    public function render(): View
    {
        return view('native.recipes');
    }
}
