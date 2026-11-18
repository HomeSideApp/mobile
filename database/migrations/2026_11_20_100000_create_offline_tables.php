<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Espejo local para funcionar sin red.
     *
     * `mirror_entities` guarda el resource tal como lo manda la API: acoplarse al
     * esquema remoto obligaría a migrar la base del teléfono cada vez que el
     * servidor agrega un campo. Sólo se indexan las columnas que la UI consulta.
     */
    public function up(): void
    {
        Schema::create('mirror_entities', function (Blueprint $table): void {
            $table->string('entity');
            $table->string('id');
            $table->text('payload');
            $table->string('updated_at')->nullable();
            $table->string('deleted_at')->nullable();
            $table->string('fetched_at');

            $table->primary(['entity', 'id']);
            $table->index(['entity', 'updated_at']);
        });

        Schema::create('sync_state', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('op');                 // post | put | patch | delete
            $table->string('path');               // ruta de la API, sin base
            $table->text('payload')->nullable();
            $table->string('idempotency_key');
            $table->string('entity')->nullable(); // para aplicar optimista y reconciliar
            $table->string('entity_id')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('state')->default('pending'); // pending | failed
            $table->string('created_at');
            $table->string('sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
        Schema::dropIfExists('sync_state');
        Schema::dropIfExists('mirror_entities');
    }
};
