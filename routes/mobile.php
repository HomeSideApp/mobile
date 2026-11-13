<?php

use App\Http\Middleware\ApplyAppearance;
use App\NativeComponents\ExpenseForm;
use App\NativeComponents\Expenses;
use App\NativeComponents\ExpenseTicket;
use App\NativeComponents\House;
use App\NativeComponents\HouseCreate;
use App\NativeComponents\Households;
use App\NativeComponents\ListCreate;
use App\NativeComponents\ListDetail;
use App\NativeComponents\Lists;
use App\NativeComponents\Login;
use App\NativeComponents\Profile;
use App\NativeComponents\Recipes;
use App\NativeComponents\ServerSetup;
use App\NativeComponents\Start;
use App\NativeLayouts\AppStackLayout;
use App\NativeLayouts\AppTabsLayout;
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

// El tema se aplica acá, con todos los providers ya arrancados: el cuerpo de
// cada pantalla resuelve `bg-theme-*` a hex al parsearse, así que esta
// escritura tiene que ser la última antes del render.
Route::middleware(ApplyAppearance::class)->group(function (): void {
    // Arranque: decide setup / login / listas y se reemplaza en la pila.
    Route::native('/start', Start::class)->name('start');

    Route::native('/server-setup', ServerSetup::class)->name('server.setup');
    Route::native('/login', Login::class)->name('login');

    Route::nativeGroup(AppTabsLayout::class, function (): void {
        Route::native('/lists', Lists::class)->name('lists');
        Route::native('/recipes', Recipes::class)->name('recipes');
        Route::native('/expenses', Expenses::class)->name('expenses');
        Route::native('/house', House::class)->name('house');
        Route::native('/profile', Profile::class)->name('profile');
    });

    // Pantallas empujadas: barra superior con volver.
    // Ticket con OCR: subir, esperar y confirmar.
    Route::native('/expenses/ticket', ExpenseTicket::class)
        ->layout(AppStackLayout::class)
        ->name('expenses.ticket');

    // Alta y edición de movimientos de economía.
    Route::native('/expenses/new', ExpenseForm::class)
        ->layout(AppStackLayout::class)
        ->name('expenses.create');

    Route::native('/expenses/{id}', ExpenseForm::class)
        ->layout(AppStackLayout::class)
        ->name('expenses.edit');

    // Crear hogar: se entra desde el selector.
    Route::native('/households/create', HouseCreate::class)
        ->layout(AppStackLayout::class)
        ->name('households.create');

    // Selector de hogar: se abre desde la barra superior de cualquier pestaña.
    Route::native('/households', Households::class)
        ->layout(AppStackLayout::class)
        ->name('households.index');

    Route::native('/lists/create', ListCreate::class)
        ->layout(AppStackLayout::class)
        ->name('lists.create');

    Route::native('/lists/{list}', ListDetail::class)
        ->layout(AppStackLayout::class)
        ->name('lists.show');

    Route::native('/households/{household}/lists/{list}', ListDetail::class)
        ->layout(AppStackLayout::class)
        ->name('households.lists.show');
});
