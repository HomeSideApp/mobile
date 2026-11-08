<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Recetario personal y compartido.
 *
 * Fase 0: cáscara navegable. Fase 2: listado, detalle con pasos/timers,
 * "agregar a la lista" y modo cocina.
 */
final class Recipes extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Recetas';
    }

    public function render(): View
    {
        return view('native.recipes');
    }
}
