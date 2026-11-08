<?php

namespace App\NativeComponents;

use App\Services\Settings\SettingsRepository;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Perfil y ajustes locales.
 *
 * Fase 0: muestra a qué servidor está conectada la app y permite cambiarlo.
 * Fase 1: datos de la cuenta, cambio de contraseña, estado 2FA, idioma y
 * cierre de sesión.
 */
final class Profile extends NativeComponent
{
    public ?string $server = null;

    public bool $hasSession = false;

    public function mount(): void
    {
        $settings = new SettingsRepository;

        $this->server = $settings->server()?->baseUrl;
        $this->hasSession = $settings->isAuthenticated();
    }

    public function navTitle(): string
    {
        return 'Perfil';
    }

    public function changeServer(): void
    {
        $this->navigate('/server-setup');
    }

    public function render(): View
    {
        return view('native.profile');
    }
}
