<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Hogar activo: quiénes viven ahí y qué módulos están habilitados.
 *
 * Fase 0: cáscara navegable. Fase 2: miembros, invitaciones y cambio de hogar.
 */
final class House extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Casa';
    }

    public function render(): View
    {
        return view('native.house');
    }
}
