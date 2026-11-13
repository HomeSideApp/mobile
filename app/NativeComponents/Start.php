<?php

namespace App\NativeComponents;

use App\Services\Settings\SettingsRepository;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Pantalla de arranque: decide a dónde entrar y desaparece.
 *
 * Sin esto la app abría siempre en `/server-setup` (`NATIVEPHP_START_URL`), así
 * que alguien con todo configurado tenía que confirmar el servidor en cada
 * arranque. Usa `replace()` para no dejar esta pantalla en la pila: volver atrás
 * desde Listas no debe caer acá.
 */
final class Start extends NativeComponent
{
    public function mount(): void
    {
        $settings = new SettingsRepository;

        // El orden importa: sin servidor no hay a dónde iniciar sesión.
        if (! $settings->isConfigured()) {
            $this->replace('/server-setup');

            return;
        }

        if (! $settings->isAuthenticated()) {
            $this->replace('/login');

            return;
        }

        $this->replace('/lists');
    }

    public function render(): View
    {
        return view('native.start');
    }
}
