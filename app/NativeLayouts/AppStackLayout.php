<?php

namespace App\NativeLayouts;

use App\Services\Settings\AppearanceService;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/**
 * Chrome para pantallas empujadas sobre las tabs: barra superior con volver.
 */
final class AppStackLayout extends NativeLayout
{
    public function usesNativeChrome(): bool
    {
        return true;
    }

    public function navBar(NativeComponent $screen): ?NavBar
    {
        // Último punto antes de publicar el árbol: si el usuario cambió la
        // apariencia, el tema tiene que estar empujado ahora.
        (new AppearanceService)->flush();

        return NavBar::make()
            ->title($screen->navTitle())
            ->back();
    }
}
