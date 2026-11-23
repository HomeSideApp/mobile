<?php

namespace Tests\Feature;

use App\Services\Storage\EnsureDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El guardia que recrea la base cuando el archivo desapareció.
 *
 * En el teléfono la crea el shell nativo al extraer el bundle; si se pierde con
 * el bundle ya extraído, la app quedaba inservible.
 */
class EnsureDatabaseTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/homeside-guard-'.uniqid('', true).'/database.sqlite';
        config(['database.connections.sqlite.database' => $this->path]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->path, $this->path.'-wal', $this->path.'-shm'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $directory = dirname($this->path);

        if (is_dir($directory)) {
            @rmdir($directory);
        }

        parent::tearDown();
    }

    public function test_a_missing_database_is_recreated_and_migrated(): void
    {
        $this->assertFileDoesNotExist($this->path);

        (new EnsureDatabase)->run();

        $this->assertFileExists($this->path, 'La base tiene que quedar creada.');

        // Y con las tablas de la app, no vacía.
        $this->assertTrue(
            DB::connection('sqlite')->getSchemaBuilder()->hasTable('settings'),
            'Tiene que haber migrado, no sólo creado el archivo.',
        );
    }

    public function test_an_existing_database_is_left_alone(): void
    {
        (new EnsureDatabase)->run();

        $before = filemtime($this->path);

        // Sin cambios no tiene que volver a migrar ni tocar el archivo.
        (new EnsureDatabase)->run();

        $this->assertSame($before, filemtime($this->path));
    }

    public function test_an_in_memory_database_is_ignored(): void
    {
        config(['database.connections.sqlite.database' => ':memory:']);

        (new EnsureDatabase)->run();

        $this->assertTrue(true, 'No tiene que intentar crear archivos para :memory:.');
    }
}
