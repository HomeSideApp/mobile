<?php

namespace Tests\Feature;

use App\NativeComponents\RecipeForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * La generación con IA tiene que llenar el formulario entero, no sólo los
 * números: ingredientes y pasos son la receta.
 */
class RecipeAiFormTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function draft(): array
    {
        return [
            'name' => 'Milanesas napolitanas',
            'description' => 'Clásico de domingo',
            'servings' => 4,
            'prep_time_seconds' => 2700,
            'cook_time_seconds' => 900,
            'difficulty' => 'medium',
            'ingredients' => [
                ['name' => 'carne de res', 'quantity' => 600, 'unit' => 'g'],
                ['name' => 'huevos', 'quantity' => 2, 'unit' => 'unidades'],
                ['name' => 'pan rallado', 'quantity' => 200, 'unit' => 'g'],
            ],
            'steps' => [
                ['description' => 'Empanar la carne'],
                ['description' => 'Freír hasta dorar'],
            ],
        ];
    }

    public function test_generating_with_ai_fills_ingredients_and_steps(): void
    {
        $this->connect();

        $this->fakeHttp([
            '*/api/v1/recipes/ai-generate' => Http::response(['data' => $this->draft(), 'warnings' => []], 200),
            '*/api/v1/*' => Http::response(['data' => []]),
        ]);

        $component = Native::test(RecipeForm::class, platform: 'android');
        $component->set('prompt', 'milanesas napolitanas');
        $component->call('generate');

        $this->assertSame('', (string) $component->get('error'));
        $this->assertSame('Milanesas napolitanas', $component->get('name'));
        $this->assertSame('4', $component->get('servings'));

        $ingredients = $component->get('ingredients');

        $this->assertCount(3, $ingredients);
        $this->assertSame('carne de res', $ingredients[0]['name']);
        $this->assertSame('600', $ingredients[0]['quantity']);
        $this->assertSame('g', $ingredients[0]['unit']);

        $steps = $component->get('steps');

        $this->assertCount(2, $steps);
        $this->assertSame('Empanar la carne', $steps[0]['description']);

        // Y que el render las muestre: el estado puede estar bien y la vista no.
        $component->assertSee('carne de res')->assertSee('Empanar la carne');
    }
}
