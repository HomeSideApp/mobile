<?php

namespace App\Services\Homeside;

/**
 * Recetario: listado, alta, edición, importación, IA, fotos y hogar.
 *
 * Los endpoints personales cuelgan de `/recipes`; los compartidos, de
 * `/households/{household}/recipes`. Los contratos están en `routes/api.php`
 * del back y no hace falta ningún endpoint nuevo.
 */
final class RecipesService
{
    public function __construct(
        private readonly Session $session = new Session,
    ) {}

    /**
     * Recetas propias.
     *
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function personal(array $query = []): array
    {
        return array_values(Session::data($this->session->client()->get('/recipes', $query)));
    }

    /**
     * Recetas compartidas con el hogar activo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function household(string $householdId): array
    {
        return array_values(Session::data(
            $this->session->client()->get('/households/'.$householdId.'/recipes'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $recipeId): array
    {
        return Session::data($this->session->client()->get('/recipes/'.$recipeId));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        return Session::data($this->session->client()->post('/recipes', $payload, $idempotencyKey));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $recipeId, array $payload): array
    {
        return Session::data($this->session->client()->put('/recipes/'.$recipeId, $payload));
    }

    public function delete(string $recipeId): void
    {
        $this->session->client()->delete('/recipes/'.$recipeId);
    }

    /**
     * Vista previa de una receta importada desde una URL con JSON-LD.
     *
     * @return array<string, mixed>
     */
    public function previewUrl(string $url): array
    {
        // El servidor baja la página: puede tardar.
        return Session::data($this->session->client()->post(
            '/recipes/import/json-ld',
            ['url' => $url],
            null,
            60,
        ));
    }

    /**
     * Vista previa de una receta escrita en Cooklang.
     *
     * @return array<string, mixed>
     */
    public function previewCooklang(string $text): array
    {
        return Session::data($this->session->client()->post('/recipes/import/cooklang', ['text' => $text]));
    }

    /**
     * Normaliza y valida una receta importada antes de guardarla.
     *
     * @param  array<string, mixed>  $recipe
     * @return array<string, mixed>
     */
    public function review(array $recipe): array
    {
        return Session::data($this->session->client()->post('/recipes/import/review', ['recipe' => $recipe]));
    }

    /**
     * Genera una receta con IA a partir de un prompt libre.
     *
     * @return array<string, mixed>
     */
    public function generate(string $prompt): array
    {
        // Generar una receta completa tarda decenas de segundos.
        return Session::data($this->session->client()->post(
            '/recipes/ai-generate',
            ['prompt' => $prompt],
            null,
            150,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadCover(string $recipeId, string $contents, string $filename): array
    {
        return Session::data(
            $this->session->client()->uploadFile('/recipes/'.$recipeId.'/image', 'image', $contents, $filename),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadStepImage(string $recipeId, string $stepId, string $contents, string $filename): array
    {
        return Session::data($this->session->client()->uploadFile(
            '/recipes/'.$recipeId.'/steps/'.$stepId.'/image',
            'image',
            $contents,
            $filename,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function share(string $householdId, string $recipeId): array
    {
        return Session::data(
            $this->session->client()->post('/households/'.$householdId.'/recipes/'.$recipeId.'/share'),
        );
    }

    public function unshare(string $householdId, string $recipeId): void
    {
        $this->session->client()->delete('/households/'.$householdId.'/recipes/'.$recipeId.'/share');
    }

    /**
     * Manda los ingredientes de la receta a una lista de compras.
     *
     * @return array<string, mixed>
     */
    public function addToList(string $householdId, string $recipeId, string $listId): array
    {
        return Session::data($this->session->client()->post(
            '/households/'.$householdId.'/recipes/'.$recipeId.'/add-to-list/'.$listId,
        ));
    }

    /**
     * Descarga autenticada de una imagen de receta o de paso.
     */
    public function imageContents(string $path): string
    {
        return $this->session->client()->download($path);
    }
}
