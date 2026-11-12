<?php

namespace App\Providers;

use App\Services\Settings\LocaleService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Best effort: en un arranque frío la tabla `settings` puede no existir
        // todavía; `apply()` lo resuelve con el idioma por defecto.
        (new LocaleService)->apply();
    }
}
