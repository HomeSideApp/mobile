<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Se asegura de que la base de SQLite exista antes de que alguien la consulte.
 *
 * En el teléfono la base la crea el shell nativo **durante la extracción del
 * bundle**, y las migraciones corren ahí mismo. Si el archivo desaparece con el
 * bundle ya extraído —limpieza de almacenamiento del sistema, un borrado
 * manual—, la app arranca y muere con "Database file at path [...] does not
 * exist" sin forma de recuperarse salvo reinstalar.
 *
 * Este guardia la recrea y migra. En el caso normal (el archivo existe) no hace
 * nada: una comprobación de `file_exists` y listo.
 */
final class EnsureDatabase
{
    public function run(): void
    {
        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            return;
        }

        if (file_exists($database)) {
            return;
        }

        try {
            $directory = dirname($database);

            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            @touch($database);

            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            // Un guardia de arranque nunca puede impedir que la app abra: si
            // falla, se reporta y el error real lo muestra la pantalla que
            // corresponda.
            report($exception);
        }
    }
}
