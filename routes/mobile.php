<?php

use App\NativeComponents\House;
use App\NativeComponents\Layouts\AppStackLayout;
use App\NativeComponents\Layouts\AppTabsLayout;
use App\NativeComponents\ListCreate;
use App\NativeComponents\ListDetail;
use App\NativeComponents\Lists;
use App\NativeComponents\Login;
use App\NativeComponents\ProductPicker;
use App\NativeComponents\Profile;
use App\NativeComponents\Recipes;
use App\NativeComponents\ServerSetup;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pantallas nativas (NativePHP v4 + SuperNative / EDGE)
|--------------------------------------------------------------------------
|
| /server-setup   conexión a una instancia de HomeSide (arranque de la app)
| /login          credenciales + OTP
| /lists ...      tabs y pantallas empujadas de listas
|
| El administrador no tiene pantallas acá: sigue en la web.
|
*/

Route::native('/server-setup', ServerSetup::class)->name('server.setup');
Route::native('/login', Login::class)->name('login');

Route::nativeGroup(AppTabsLayout::class, function (): void {
    Route::native('/lists', Lists::class)->name('lists');
    Route::native('/recipes', Recipes::class)->name('recipes');
    Route::native('/house', House::class)->name('house');
    Route::native('/profile', Profile::class)->name('profile');
});

// Pantallas empujadas: barra superior con volver.
Route::native('/lists/create', ListCreate::class)
    ->layout(AppStackLayout::class)
    ->name('lists.create');

Route::native('/lists/{list}', ListDetail::class)
    ->layout(AppStackLayout::class)
    ->name('lists.show');

Route::native('/lists/{list}/add', ProductPicker::class)
    ->layout(AppStackLayout::class)
    ->name('lists.add');

Route::native('/households/{household}/lists/{list}', ListDetail::class)
    ->layout(AppStackLayout::class)
    ->name('households.lists.show');

Route::native('/households/{household}/lists/{list}/add', ProductPicker::class)
    ->layout(AppStackLayout::class)
    ->name('households.lists.add');
