<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tickets capturados sin red.
     *
     * Van en su propia tabla y no en `outbox` porque `/sync/push` sólo alcanza
     * las rutas de escritura del alcance: la subida de un ticket usa el
     * endpoint de imports y se reintenta aparte.
     */
    public function up(): void
    {
        Schema::create('pending_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('file_path');
            $table->string('filename');
            $table->string('household_id')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_imports');
    }
};
