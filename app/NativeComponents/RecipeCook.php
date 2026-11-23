<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\RecipesService;
use App\Services\Media\RecipeImageCache;
use App\Services\Settings\LocaleService;
use Illuminate\View\View;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;

/**
 * Modo cocina: un paso por pantalla, con sus timers.
 *
 * Todo local: no hay endpoint de "estoy cocinando". Los timers se cuentan en el
 * componente mientras la pantalla está abierta.
 */
final class RecipeCook extends NativeComponent
{
    public ?string $recipeId = null;

    public string $name = '';

    /** @var array<int, array<string, mixed>> */
    public array $steps = [];

    public int $index = 0;

    public bool $loading = true;

    public ?string $error = null;

    public ?string $notice = null;

    /** Segundos que quedan del timer activo (null = ninguno). */
    public ?int $remaining = null;

    public ?string $timerLabel = null;

    /** Texto formateado del timer, listo para la vista. */
    public ?string $timerText = null;

    public function mount(): void
    {
        (new LocaleService)->apply();

        $this->recipeId = $this->param('recipe');

        $this->load();
    }

    public function navTitle(): string
    {
        return $this->name !== '' ? $this->name : __('app.recipes.cook');
    }

    public function load(): void
    {
        if ($this->recipeId === null || $this->recipeId === '') {
            $this->loading = false;
            $this->error = __('app.recipes.not_found');

            return;
        }

        try {
            $recipe = (new RecipesService)->show($this->recipeId);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->loading = false;

            return;
        }

        $images = new RecipeImageCache;
        $version = is_string($recipe['updated_at'] ?? null) ? $recipe['updated_at'] : null;

        $this->name = is_string($recipe['name'] ?? null) ? $recipe['name'] : '';
        $steps = is_array($recipe['steps'] ?? null) ? $recipe['steps'] : [];

        $this->steps = array_values(array_map(function (array $step, int $index) use ($images, $version): array {
            $id = (string) ($step['id'] ?? '');
            $timers = [];

            foreach (is_array($step['timers'] ?? null) ? $step['timers'] : [] as $timer) {
                if (! is_array($timer)) {
                    continue;
                }

                $timers[] = [
                    'name' => is_string($timer['name'] ?? null) ? $timer['name'] : '',
                    'duration' => is_numeric($timer['duration_seconds'] ?? null) ? (int) $timer['duration_seconds'] : 0,
                ];
            }

            return [
                'id' => $id,
                'number' => $index + 1,
                'description' => is_string($step['description'] ?? null) ? $step['description'] : '',
                'image_url' => $id !== '' ? $images->step((string) $this->recipeId, $id, $version) : null,
                'timers' => $timers,
            ];
        }, $steps, array_keys($steps)));

        $this->loading = false;
    }

    public function next(): void
    {
        if ($this->index < count($this->steps) - 1) {
            $this->index++;
        }
    }

    public function previous(): void
    {
        if ($this->index > 0) {
            $this->index--;
        }
    }

    /**
     * Arranca el timer de un paso (uno por vez).
     */
    public function startTimer(string $name, int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $this->remaining = $seconds;
        $this->timerLabel = $name;
        $this->timerText = $this->format($seconds);
        $this->notice = null;
    }

    public function stopTimer(): void
    {
        $this->remaining = null;
        $this->timerLabel = null;
        $this->timerText = null;
    }

    /**
     * Descuenta el timer mientras la pantalla está abierta.
     */
    #[Poll(1000)]
    public function tick(): void
    {
        if ($this->remaining === null) {
            return;
        }

        $this->remaining--;
        $this->timerText = $this->format($this->remaining);

        if ($this->remaining <= 0) {
            $this->remaining = null;
            $this->timerText = null;
            $this->notice = __('app.recipes.timer_done', ['name' => $this->timerLabel ?? '']);
            $this->timerLabel = null;
        }
    }

    private function format(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public function render(): View
    {
        return view('native.recipe-cook');
    }
}
