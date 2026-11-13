<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guarda contra el error más silencioso de la i18n: una clave mal escrita o
 * puesta en el lugar equivocado del archivo se muestra cruda en pantalla
 * (`app.list_detail.actions`) y ningún test de pantalla la mira.
 */
class TranslationsTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function referencedKeys(): array
    {
        $keys = [];

        $files = [
            ...File::allFiles(app_path()),
            ...File::allFiles(resource_path('views')),
        ];

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            preg_match_all("/__\\(\\s*['\"](app\\.[A-Za-z0-9_.]+)['\"]/", $contents, $matches);

            foreach ($matches[1] as $key) {
                // Las claves armadas con concatenación terminan en punto o en
                // guión bajo: no se pueden verificar estáticamente.
                if (! str_ends_with($key, '.') && ! str_ends_with($key, '_')) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * @return list<string>
     */
    private function keysFor(string $locale): array
    {
        /** @var array<string, mixed> $contents */
        $contents = require lang_path($locale.'/app.php');

        return array_map(
            static fn (string $key): string => 'app.'.$key,
            array_keys(Arr::dot($contents)),
        );
    }

    public function test_plural_strings_use_explicit_ranges(): void
    {
        // En este runtime `trans_choice` con `|` simple devuelve siempre la
        // primera forma, aunque el locale sea en/es. Visto en el dispositivo:
        // un hogar de 2 personas mostraba "2 member".
        app()->setLocale('es-ES');

        $this->assertSame('1 miembro', trans_choice('app.house.members_count', 1, ['count' => 1]));
        $this->assertSame('2 miembros', trans_choice('app.house.members_count', 2, ['count' => 2]));

        app()->setLocale('en-US');

        $this->assertSame('2 members', trans_choice('app.house.members_count', 2, ['count' => 2]));
        $this->assertSame('1 invitation', trans_choice('app.house.invitations_count', 1, ['count' => 1]));
    }

    public function test_every_referenced_key_exists_in_every_locale(): void
    {
        $used = $this->referencedKeys();

        $this->assertNotEmpty($used, 'No encontramos claves traducibles en el código.');

        foreach (['es-ES', 'en-US'] as $locale) {
            $missing = array_values(array_diff($used, $this->keysFor($locale)));

            $this->assertSame(
                [],
                $missing,
                "Faltan en {$locale}: ".implode(', ', $missing),
            );
        }
    }

    public function test_both_locales_define_the_same_keys(): void
    {
        $es = $this->keysFor('es-ES');
        $en = $this->keysFor('en-US');

        sort($es);
        sort($en);

        $this->assertSame($es, $en, 'Los dos archivos de idioma tienen que tener las mismas claves.');
    }

    public function test_a_missing_translation_is_never_the_raw_key(): void
    {
        // Una clave existente devuelve texto, no el identificador.
        $this->assertNotSame(
            'app.list_detail.actions',
            __('app.list_detail.actions'),
        );
    }
}
