<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\RecipesService;
use App\Services\Media\GalleryPicker;
use App\Services\Media\RecipeImageCache;
use App\Services\Settings\LocaleService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Gallery\MediaSelected;

/**
 * Alta y edición de una receta a mano, con IA o desde una importación.
 *
 * La IA y el importador sólo **rellenan** el formulario: guardar es siempre una
 * decisión de la persona que tiene la pantalla delante.
 */
final class RecipeForm extends NativeComponent
{
    public ?string $recipeId = null;

    public string $name = '';

    public string $description = '';

    public string $servings = '';

    public string $prepMinutes = '';

    public string $cookMinutes = '';

    public string $difficulty = 'easy';

    /** @var array<int, array{name: string, quantity: string, unit: string}> */
    public array $ingredients = [];

    /** @var array<int, array{description: string}> */
    public array $steps = [];

    public string $prompt = '';

    public bool $generating = false;

    public bool $saving = false;

    public ?string $error = null;

    public ?string $notice = null;

    public ?string $imageUrl = null;

    public bool $uploading = false;

    public function mount(): void
    {
        (new LocaleService)->apply();

        $this->recipeId = $this->param('recipe');
        $mode = $this->param('mode');

        if ($this->recipeId !== null && $this->recipeId !== '') {
            $this->loadRecipe();

            return;
        }

        $this->ingredients = [['name' => '', 'quantity' => '', 'unit' => '']];
        $this->steps = [['description' => '']];

        // Desde el importador llega un borrador ya normalizado por el servidor.
        $draft = $this->param('draft');

        if (is_string($draft) && $draft !== '') {
            $decoded = json_decode(base64_decode($draft), true);
            $this->applyDraft(is_array($decoded) ? $decoded : []);
            $this->notice = __('app.recipes.from_import');

            return;
        }

        if ($mode === 'ai') {
            $this->notice = __('app.recipes.ai_hint');
        }
    }

    public function navTitle(): string
    {
        if ($this->recipeId !== null && $this->recipeId !== '') {
            return __('app.recipes.edit');
        }

        return __('app.recipes.new');
    }

    private function loadRecipe(): void
    {
        try {
            $recipe = (new RecipesService)->show((string) $this->recipeId);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->applyDraft($recipe);

        $this->imageUrl = (new RecipeImageCache)->cover(
            (string) $this->recipeId,
            is_string($recipe['updated_at'] ?? null) ? $recipe['updated_at'] : null,
        );
    }

    /**
     * Rellena el formulario con una receta del servidor, de la IA o del importador.
     *
     * @param  array<string, mixed>  $recipe
     */
    private function applyDraft(array $recipe): void
    {
        $this->name = is_string($recipe['name'] ?? null) ? $recipe['name'] : '';
        $this->description = is_string($recipe['description'] ?? null) ? $recipe['description'] : '';
        $this->servings = is_numeric($recipe['servings'] ?? null) ? (string) (int) $recipe['servings'] : '';

        $prep = is_numeric($recipe['prep_time_seconds'] ?? null) ? (int) $recipe['prep_time_seconds'] : 0;
        $cook = is_numeric($recipe['cook_time_seconds'] ?? null) ? (int) $recipe['cook_time_seconds'] : 0;

        $this->prepMinutes = $prep > 0 ? (string) intdiv($prep, 60) : '';
        $this->cookMinutes = $cook > 0 ? (string) intdiv($cook, 60) : '';
        $this->difficulty = is_string($recipe['difficulty'] ?? null) && $recipe['difficulty'] !== ''
            ? strtolower($recipe['difficulty'])
            : 'easy';

        $ingredients = is_array($recipe['ingredients'] ?? null) ? $recipe['ingredients'] : [];
        $this->ingredients = [];

        foreach ($ingredients as $ingredient) {
            if (! is_array($ingredient)) {
                continue;
            }

            $this->ingredients[] = [
                'name' => is_string($ingredient['name'] ?? null) ? $ingredient['name'] : '',
                'quantity' => is_numeric($ingredient['quantity'] ?? null) ? (string) $ingredient['quantity'] : '',
                'unit' => is_string($ingredient['unit'] ?? null) ? $ingredient['unit'] : '',
            ];
        }

        if ($this->ingredients === []) {
            $this->ingredients = [['name' => '', 'quantity' => '', 'unit' => '']];
        }

        $steps = is_array($recipe['steps'] ?? null) ? $recipe['steps'] : [];
        $this->steps = [];

        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }

            $this->steps[] = [
                'description' => is_string($step['description'] ?? null) ? $step['description'] : '',
            ];
        }

        if ($this->steps === []) {
            $this->steps = [['description' => '']];
        }
    }

    public function addIngredient(): void
    {
        $this->ingredients[] = ['name' => '', 'quantity' => '', 'unit' => ''];
    }

    public function removeIngredient(int $index): void
    {
        unset($this->ingredients[$index]);
        $this->ingredients = array_values($this->ingredients);

        if ($this->ingredients === []) {
            $this->ingredients = [['name' => '', 'quantity' => '', 'unit' => '']];
        }
    }

    public function addStep(): void
    {
        $this->steps[] = ['description' => ''];
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);

        if ($this->steps === []) {
            $this->steps = [['description' => '']];
        }
    }

    /**
     * Pide una receta a la IA y rellena el formulario para revisarla.
     */
    public function generate(): void
    {
        $prompt = trim($this->prompt);

        if ($prompt === '' || $this->generating) {
            return;
        }

        $this->generating = true;
        $this->error = null;
        $this->notice = null;

        try {
            $draft = (new RecipesService)->generate($prompt);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->generating = false;

            return;
        }

        $this->applyDraft($draft);
        $this->generating = false;
        $this->notice = __('app.recipes.ai_filled');
    }

    public function save(): void
    {
        if ($this->saving) {
            return;
        }

        if (trim($this->name) === '') {
            $this->error = __('app.recipes.name_required');

            return;
        }

        $this->saving = true;
        $this->error = null;

        $prep = is_numeric($this->prepMinutes) ? (int) $this->prepMinutes * 60 : 0;
        $cook = is_numeric($this->cookMinutes) ? (int) $this->cookMinutes * 60 : 0;

        $payload = [
            'name' => trim($this->name),
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'servings' => is_numeric($this->servings) ? (int) $this->servings : null,
            'prep_time_seconds' => $prep,
            'cook_time_seconds' => $cook,
            'total_time_seconds' => $prep + $cook,
            'difficulty' => $this->difficulty,
            'ingredients' => $this->ingredientsPayload(),
            'steps' => $this->stepsPayload(),
        ];

        $service = new RecipesService;

        try {
            $saved = ($this->recipeId !== null && $this->recipeId !== '')
                ? $service->update((string) $this->recipeId, $payload)
                : $service->create($payload, (string) Str::uuid());
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->saving = false;

            return;
        }

        $this->saving = false;

        $savedId = is_string($saved['id'] ?? null) ? $saved['id'] : $this->recipeId;

        if ($savedId !== null && $savedId !== '') {
            $this->replace('/recipes/'.$savedId);
        } else {
            $this->back();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ingredientsPayload(): array
    {
        $payload = [];

        foreach (array_values($this->ingredients) as $order => $ingredient) {
            $name = trim((string) ($ingredient['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $payload[] = [
                'client_id' => 'ing-'.($order + 1),
                'name' => $name,
                'quantity' => is_numeric($ingredient['quantity'] ?? null) && (float) $ingredient['quantity'] > 0
                    ? (float) $ingredient['quantity']
                    : null,
                'unit' => trim((string) ($ingredient['unit'] ?? '')) !== '' ? trim((string) $ingredient['unit']) : null,
                'order' => $order + 1,
            ];
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stepsPayload(): array
    {
        $payload = [];

        foreach (array_values($this->steps) as $order => $step) {
            $description = trim((string) ($step['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $payload[] = [
                'client_id' => 'step-'.($order + 1),
                'description' => $description,
                'order' => $order + 1,
            ];
        }

        return $payload;
    }

    /**
     * Foto de portada desde el teléfono (galería o cámara).
     */
    public function pickCover(): void
    {
        $this->uploading = true;
        $this->error = null;

        try {
            (new GalleryPicker)->pickImage();
        } catch (HomesideException $exception) {
            $this->uploading = false;
            $this->error = $exception->getMessage();
        }
    }

    #[On(MediaSelected::class)]
    public function onMediaSelected(bool $success = false, array $files = []): void
    {
        $this->uploading = false;

        if (! $success || $files === []) {
            return;
        }

        $path = is_string($files[0] ?? null) ? $files[0] : null;

        if ($path === null || ! File::exists($path)) {
            return;
        }

        if ($this->recipeId === null || $this->recipeId === '') {
            $this->error = __('app.recipes.save_before_photo');

            return;
        }

        $contents = File::get($path);
        $maxKb = (int) config('homeside.image_upload_max_kb', 12288);

        if (strlen($contents) / 1024 > $maxKb) {
            $this->error = __('app.recipes.photo_too_large');

            return;
        }

        try {
            (new RecipesService)->uploadCover((string) $this->recipeId, $contents, 'receta.jpg');
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->imageUrl = null;
        $this->loadRecipe();
        $this->notice = __('app.recipes.photo_saved');
    }

    public function goToLogin(): void
    {
        $this->navigate('/login');
    }

    public function render(): View
    {
        return view('native.recipe-form');
    }
}
