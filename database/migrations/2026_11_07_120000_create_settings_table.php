<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Almacén de ajustes de la app en el SQLite del dispositivo.
     *
     * El token de sesión se guarda cifrado en esta misma tabla (Laravel usa un
     * APP_KEY único por instalación, custodiado por NativePHP en el Keychain /
     * Keystore), así que la base nunca contiene el token en claro.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
