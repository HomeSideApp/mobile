<?php

use App\NativeComponents\House;
use App\NativeComponents\Layouts\AppTabsLayout;
use App\NativeComponents\Lists;
use App\NativeComponents\Profile;
use App\NativeComponents\Recipes;
use App\NativeComponents\ServerSetup;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pantallas nativas (NativePHP v4 + SuperNative / EDGE)
|--------------------------------------------------------------------------
|
| Fase 0: conexión al servidor + cáscaras de las tabs.
| La raíz de la app apunta a /server-setup (NATIVEPHP_START_URL); el gate de
| sesión real llega con el login de la Fase 1.
|
*/

Route::native('/server-setup', ServerSetup::class)->name('server.setup');

Route::nativeGroup(AppTabsLayout::class, function (): void {
    Route::native('/lists', Lists::class)->name('lists');
    Route::native('/recipes', Recipes::class)->name('recipes');
    Route::native('/house', House::class)->name('house');
    Route::native('/profile', Profile::class)->name('profile');
});
