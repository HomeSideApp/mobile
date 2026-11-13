<?php

namespace App\Providers;

use App\Services\Homeside\HouseholdsService;
use App\Services\Settings\AppearanceService;
use App\Services\Settings\LocaleService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Un solo `HouseholdsService` por request: la barra superior y la
        // pantalla piden el hogar activo en el mismo render, y con instancias
        // separadas eso son dos `GET /me` y dos `GET /households` para el mismo
        // dato. El alcance por request es el correcto: cambiar de hogar abre un
        // request nuevo, así que no hay caché vieja que invalidar.
        $this->app->singleton(HouseholdsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Best effort: en un arranque frío la tabla `settings` puede no existir
        // todavía; `apply()` lo resuelve con el idioma por defecto.
        (new LocaleService)->apply();

        // La apariencia elegida se aplica por encima del tema antes del primer
        // render; el chrome del sistema sigue a Android.
        (new AppearanceService)->apply();
    }
}
