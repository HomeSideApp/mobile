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
                // Las claves armadas con concatenación terminan en punto: no se
                // pueden verificar estáticamente.
                if (! str_ends_with($key, '.')) {
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
