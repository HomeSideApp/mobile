<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Descarta las operaciones encoladas antes de que el outbox normalizara la
     * ruta.
     *
     * El push habla `/v1/...` y el cliente HTTP habla rutas relativas a
     * `.../api/v1`. Las pantallas encolaban con su propia forma, así que esas
     * filas apuntaban a una ruta inexistente y se reintentaban en cada
     * sincronización sin poder aplicarse nunca.
     *
     * Se descartan en vez de remapearlas: son toggles y bajas de una versión en
     * desarrollo, con días de antigüedad, y aplicarlas ahora podría resucitar
     * algo ya borrado o pisar un cambio posterior. Rehacerlas cuesta un toque.
     */
    public function up(): void
    {
        DB::table('outbox')
            ->where('path', 'not like', '/v1/%')
            ->delete();
    }

    public function down(): void
    {
        // No se puede deshacer: las filas no son recuperables.
    }
};
