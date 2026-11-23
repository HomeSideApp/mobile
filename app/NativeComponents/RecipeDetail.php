<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\HouseholdsService;
use App\Services\Homeside\ListsService;
use App\Services\Homeside\RecipesService;
use App\Services\Media\RecipeImageCache;
use App\Services\Settings\LocaleService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Detalle de una receta: ingredientes, pasos y las acciones que importan
 * (cocinar, editar, compartir con el hogar, mandar a la lista).
 */
final class RecipeDetail extends NativeComponent
{
    public ?string $recipeId = null;

    public string $name = '';

    public ?string $description = null;

    public ?string $imageUrl = null;

    public string $meta = '';

    /** @var array<int, array<string, mixed>> */
    public array $ingredients = [];

    /** @var array<int, array<string, mixed>> */
    public array $steps = [];

    public bool $loading = true;

    public bool $needsLogin = false;

    public ?string $error = null;

    public ?string $notice = null;

    public bool $busy = false;

    /** Acciones de la receta. */
    public bool $actionsOpen = false;

    public bool $confirmDelete = false;

    public ?string $householdId = null;

    public bool $shared = false;

    /** @var array<int, string> */
    public array $listOptions = [];

    public string $listValue = '';

    /** @var array<string, string> */
    private array $listIds = [];

    public function mount(): void
    {
        (new LocaleService)->apply();

        $this->recipeId = $this->param('recipe');
        $this->householdId = app(HouseholdsService::class)->activeId();

        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    public function navTitle(): string
    {
        return $this->name !== '' ? $this->name : __('app.recipes.title');
    }

    public function load(): void
    {
        if ($this->recipeId === null || $this->recipeId === '') {
            $this->loading = false;
            $this->error = __('app.recipes.not_found');

            return;
        }

        $this->loading = true;
        $this->error = null;

        try {
            $recipe = (new RecipesService)->show($this->recipeId);
        } catch (HomesideException $exception) {
            $this->needsLogin = $exception->requiresLogin();
            $this->error = $exception->getMessage();
            $this->loading = false;

            return;
        }

        $images = new RecipeImageCache;
        $version = is_string($recipe['updated_at'] ?? null) ? $recipe['updated_at'] : null;

        $this->name = is_string($recipe['name'] ?? null) ? $recipe['name'] : __('app.recipes.title');
        $this->description = is_string($recipe['description'] ?? null) ? $recipe['description'] : null;
        $this->imageUrl = $images->cover((string) $this->recipeId, $version);
        $this->shared = ($recipe['is_shared'] ?? false) === true;

        $this->meta = $this->buildMeta($recipe);
        $this->ingredients = $this->buildIngredients($recipe);
        $this->steps = $this->buildSteps($recipe, $images, $version);

        $this->loadLists();
        $this->loading = false;
    }

    /**
     * @param  array<string, mixed>  $recipe
     */
    private function buildMeta(array $recipe): string
    {
        $parts = [];

        $total = is_numeric($recipe['total_time_seconds'] ?? null) ? (int) $recipe['total_time_seconds'] : 0;

        if ($total > 0) {
            $minutes = (int) round($total / 60);

            if ($minutes < 60) {
                $parts[] = $minutes.' min';
            } else {
                $hours = intdiv($minutes, 60);
                $rest = $minutes % 60;

                $parts[] = $rest === 0 ? $hours.' h' : $hours.' h '.$rest.' min';
            }
        }

        if (is_numeric($recipe['servings'] ?? null) && (int) $recipe['servings'] > 0) {
            $parts[] = __('app.recipes.servings_short', ['count' => (int) $recipe['servings']]);
        }

        if (is_string($recipe['difficulty'] ?? null) && $recipe['difficulty'] !== '') {
            $parts[] = __('app.recipes.difficulty.'.strtolower($recipe['difficulty']));
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @return array<int, array<string, mixed>>
     */
    private function buildIngredients(array $recipe): array
    {
        $items = is_array($recipe['ingredients'] ?? null) ? $recipe['ingredients'] : [];

        return array_values(array_map(function (array $ingredient): array {
            $quantity = is_numeric($ingredient['quantity'] ?? null) ? (float) $ingredient['quantity'] : null;
            $unit = is_string($ingredient['unit'] ?? null) ? $ingredient['unit'] : null;

            return [
                'id' => (string) ($ingredient['id'] ?? ''),
                'name' => is_string($ingredient['name'] ?? null) ? $ingredient['name'] : '—',
                'amount' => $quantity !== null
                    ? rtrim(rtrim(number_format($quantity, 2, ',', ''), '0'), ',').($unit !== null ? ' '.$unit : '')
                    : (is_string($ingredient['quantity_text'] ?? null) ? $ingredient['quantity_text'] : null),
                'optional' => ($ingredient['optional'] ?? false) === true,
            ];
        }, $items));
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @return array<int, array<string, mixed>>
     */
    private function buildSteps(array $recipe, RecipeImageCache $images, ?string $version): array
    {
        $steps = is_array($recipe['steps'] ?? null) ? $recipe['steps'] : [];

        return array_values(array_map(function (array $step, int $index) use ($images, $version): array {
            $id = (string) ($step['id'] ?? '');

            return [
                'id' => $id,
                'number' => $index + 1,
                'description' => is_string($step['description'] ?? null) ? $step['description'] : '',
                'image_url' => $id !== '' ? $images->step((string) $this->recipeId, $id, $version) : null,
                'timers' => is_array($step['timers'] ?? null) ? $step['timers'] : [],
            ];
        }, $steps, array_keys($steps)));
    }

    private function loadLists(): void
    {
        $this->listOptions = [];
        $this->listIds = [];

        $householdId = $this->householdId;

        if ($householdId === null) {
            return;
        }

        try {
            $lists = (new ListsService)->household($householdId);
        } catch (HomesideException) {
            return;
        }

        foreach ($lists as $list) {
            $id = (string) ($list['id'] ?? '');
            $name = is_string($list['name'] ?? null) ? $list['name'] : '';

            if ($id === '' || $name === '') {
                continue;
            }

            $this->listIds[$name] = $id;
            $this->listOptions[] = $name;
        }

        $this->listValue = $this->listOptions[0] ?? '';
    }

    public function toggleActions(): void
    {
        $this->actionsOpen = ! $this->actionsOpen;
        $this->confirmDelete = false;
    }

    public function edit(): void
    {
        $this->navigate('/recipes/'.$this->recipeId.'/edit');
    }

    public function cook(): void
    {
        $this->navigate('/recipes/'.$this->recipeId.'/cook');
    }

    /**
     * Comparte o deja de compartir con el hogar activo.
     */
    public function toggleShare(): void
    {
        if ($this->householdId === null || $this->recipeId === null || $this->busy) {
            return;
        }

        $this->busy = true;
        $this->error = null;
        $service = new RecipesService;

        try {
            if ($this->shared) {
                $service->unshare($this->householdId, $this->recipeId);
                $this->notice = __('app.recipes.unshared');
            } else {
                $service->share($this->householdId, $this->recipeId);
                $this->notice = __('app.recipes.shared');
            }
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->busy = false;

            return;
        }

        $this->shared = ! $this->shared;
        $this->busy = false;
    }

    /**
     * Manda los ingredientes de la receta a la lista elegida.
     */
    public function addToList(): void
    {
        $listId = $this->listIds[$this->listValue] ?? null;

        if ($listId === null || $this->householdId === null || $this->recipeId === null || $this->busy) {
            return;
        }

        $this->busy = true;
        $this->error = null;
        $this->notice = null;

        try {
            (new RecipesService)->addToList($this->householdId, $this->recipeId, $listId);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->busy = false;

            return;
        }

        $this->notice = __('app.recipes.added_to_list');
        $this->busy = false;
    }

    public function requestDelete(): void
    {
        $this->confirmDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmDelete = false;
    }

    public function deleteRecipe(): void
    {
        if ($this->recipeId === null || $this->busy) {
            return;
        }

        $this->busy = true;
        $this->error = null;

        try {
            (new RecipesService)->delete($this->recipeId);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->busy = false;

            return;
        }

        $this->busy = false;
        $this->back();
    }

    public function goToLogin(): void
    {
        $this->navigate('/login');
    }

    public function render(): View
    {
        return view('native.recipe-detail');
    }
}
