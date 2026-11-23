<?php

namespace Tests\Feature;

use App\NativeComponents\RecipeCook;
use App\NativeComponents\RecipeDetail;
use App\NativeComponents\RecipeForm;
use App\NativeComponents\RecipeImport;
use App\NativeComponents\Recipes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * El recetario en la app: listado, detalle, alta/edición, cocina e importación.
 */
class RecipesScreenTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function recipe(array $overrides = []): array
    {
        return [
            'id' => 'recipe-1',
            'name' => 'Lasaña',
            'description' => 'Clásica',
            'servings' => 4,
            'prep_time_seconds' => 1800,
            'cook_time_seconds' => 3600,
            'total_time_seconds' => 5400,
            'difficulty' => 'medium',
            'updated_at' => '2026-11-20T10:00:00Z',
            'ingredients' => [
                ['id' => 'ing-1', 'name' => 'Harina', 'quantity' => 200, 'unit' => 'g', 'optional' => false],
            ],
            'steps' => [
                ['id' => 'step-1', 'description' => 'Mezclar todo', 'timers' => [
                    ['name' => 'reposo', 'duration_seconds' => 600],
                ]],
            ],
            ...$overrides,
        ];
    }

    public function test_the_list_shows_personal_and_household_recipes(): void
    {
        $this->connect();

        $this->fakeHttp([
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => 'household-1'])]),
            '*/api/v1/households/household-1' => Http::response(['data' => ['id' => 'household-1', 'name' => 'Casa', 'enabled_modules' => []]]),
            '*/api/v1/households/household-1/recipes' => Http::response(['data' => [$this->recipe(['id' => 'recipe-2', 'name' => 'Tarta'])]]),
            '*/api/v1/recipes*' => Http::response(['data' => [$this->recipe()]]),
            '*/api/v1/*' => Http::response(['data' => []]),
        ]);

        $component = Native::test(Recipes::class, platform: 'android');

        $component->assertSee('Lasaña')->assertSee('Tarta');
        $this->assertSame('Lasaña', $component->get('personal')[0]['name'] ?? null);
        $this->assertSame('Tarta', $component->get('household')[0]['name'] ?? null);
    }

    public function test_the_detail_shows_ingredients_steps_and_meta(): void
    {
        $this->connect();

        $this->fakeHttp([
            '*/api/v1/recipes/recipe-1' => Http::response(['data' => $this->recipe()]),
            '*/api/v1/*' => Http::response(['data' => []]),
        ]);

        $component = Native::test(RecipeDetail::class, ['recipe' => 'recipe-1']);

        $component->assertSee('Harina')->assertSee('Mezclar todo');
        $this->assertStringContainsString('4 porciones', $component->get('meta'));
        $this->assertSame(1, count($component->get('steps')));
        $this->assertSame('600', (string) ($component->get('steps')[0]['timers'][0]['duration_seconds'] ?? ''));
    }

    public function test_saving_a_new_recipe_sends_ingredients_and_steps(): void
    {
        $this->connect();

        $this->fakeHttp([
            '*/api/v1/recipes' => Http::response(['data' => $this->recipe(['id' => 'recipe-9'])], 201),
            '*/api/v1/*' => Http::response(['data' => []]),
        ]);

        $component = Native::test(RecipeForm::class, platform: 'android');
        $component->set('name', 'Tortilla');
        $component->set('servings', '2');
        $component->set('prepMinutes', '10');
        $component->set('ingredients', [['name' => 'Huevo', 'quantity' => '3', 'unit' => 'un']]);
        $component->set('steps', [['description' => 'Batir y cuajar']]);
        $component->call('save');

        $sent = null;

        foreach (Http::recorded() as [$request]) {
            if ($request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/recipes')) {
                $sent = $request->data();
            }
        }

        $this->assertNotNull($sent, 'Tenía que mandar el alta.');
        $this->assertSame('Tortilla', $sent['name']);
        $this->assertSame(600, $sent['prep_time_seconds']);
        $this->assertSame('Huevo', $sent['ingredients'][0]['name']);
        $this->assertSame('Batir y cuajar', $sent['steps'][0]['description']);
    }

    public function test_cooking_mode_walks_the_steps_and_runs_a_timer(): void
    {
        $this->connect();

        $this->fakeHttp([
            '*/api/v1/recipes/recipe-1' => Http::response(['data' => $this->recipe()]),
            '*/api/v1/*' => Http::response(['data' => []]),
        ]);

        $component = Native::test(RecipeCook::class, ['recipe' => 'recipe-1']);

        $component->assertSet('index', 0);

        $component->call('startTimer', 'reposo', 2);
        $component->assertSet('remaining', 2);

        $component->call('tick');
        $component->assertSet('remaining', 1);

        $component->call('tick');
        $component->assertSet('remaining', null);
        $this->assertNotNull($component->get('notice'));
    }

    public function test_import_goes_through_the_server_and_opens_the_form_with_the_draft(): void
    {
        $this->connect();

        $this->fakeHttp([
            '*/api/v1/recipes/import/json-ld' => Http::response(['data' => ['recipe' => ['name' => 'Del blog']]]),
            '*/api/v1/recipes/import/review' => Http::response(['data' => ['recipe' => ['name' => 'Del blog', 'steps' => []]]]),
            '*/api/v1/*' => Http::response(['data' => []]),
        ]);

        $component = Native::test(RecipeImport::class, platform: 'android');
        $component->set('value', 'https://ejemplo.test/receta');
        $component->call('run');

        $component->assertSet('error', null);
        $component->assertSet('working', false);

        // El borrador se manda al formulario para revisarlo.
        $component->assertNavigatedTo('/recipes/create');
    }

    public function test_sharing_and_sending_to_a_list_hit_the_household_endpoints(): void
    {
        $this->connect();

        $this->fakeHttp([
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => 'household-1'])]),
            '*/api/v1/households/household-1' => Http::response(['data' => ['id' => 'household-1', 'name' => 'Casa', 'enabled_modules' => []]]),
            '*/api/v1/households/household-1/lists' => Http::response(['data' => [['id' => 'list-1', 'name' => 'Compra']]]),
            '*/api/v1/recipes/recipe-1' => Http::response(['data' => $this->recipe()]),
            '*/api/v1/*' => Http::response(['data' => []]),
        ]);

        $component = Native::test(RecipeDetail::class, ['recipe' => 'recipe-1']);

        $component->call('toggleShare');
        $component->assertSet('shared', true);

        $component->set('listValue', 'Compra');
        $component->call('addToList');

        $urls = array_map(fn (array $pair): string => $pair[0]->method().' '.$pair[0]->url(), Http::recorded()->all());

        $this->assertTrue(
            (bool) array_filter($urls, fn (string $line): bool => str_contains($line, '/households/household-1/recipes/recipe-1/share')),
            'Tenía que compartir con el hogar.',
        );
        $this->assertTrue(
            (bool) array_filter($urls, fn (string $line): bool => str_contains($line, '/add-to-list/list-1')),
            'Tenía que mandar los ingredientes a la lista.',
        );
    }
}
