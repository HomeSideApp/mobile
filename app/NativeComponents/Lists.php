<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Listas de la compra (personales y del hogar).
 *
 * Fase 0: cáscara navegable con estado vacío. La carga real, los cards con
 * color por categoría y el toggle de ítems llegan en la Fase 1.
 */
final class Lists extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Listas';
    }

    public function render(): View
    {
        return view('native.lists');
    }
}
