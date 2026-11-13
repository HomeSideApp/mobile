<?php

namespace App\NativeLayouts;

use App\Services\Homeside\HouseholdsService;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/**
 * Chrome principal de la app: barra superior por stack y tabs inferiores.
 *
 * Las tabs son la navegación de primer nivel del usuario final. El área de
 * administración no existe acá: el administrador siempre va por la web.
 */
final class AppTabsLayout extends NativeLayout
{
    public function usesNativeChrome(): bool
    {
        return true;
    }

    public function navBar(NativeComponent $screen): ?NavBar
    {
        $bar = NavBar::make()->title($screen->navTitle());

        // El hogar activo es el contexto de toda la app, así que vive en la
        // barra: se ve desde cualquier pestaña y se cambia con un toque.
        // `NavAction` sólo admite etiqueta o imagen, no íconos por nombre, así
        // que va como texto y el sistema lo recorta si el nombre es largo.
        $name = $this->activeHouseholdName();

        if ($name !== null) {
            $bar->action(
                NavAction::make('households')
                    ->label($name)
                    ->a11yLabel((string) __('app.house.switch'))
                    ->press('openHouseholds'),
            );
        }

        return $bar;
    }

    private function activeHouseholdName(): ?string
    {
        try {
            return app(HouseholdsService::class)->name(
                app(HouseholdsService::class)->activeId(),
            );
        } catch (\Throwable) {
            // Sin sesión (o sin bridge) la barra no muestra el contexto y la
            // pantalla se encarga de pedir login.
            return null;
        }
    }

    public function tabBar(NativeComponent $screen): ?TabBar
    {
        return TabBar::make()
            // Los colores del chrome salen del tema HomeSide, no del default de
            // Material (que pinta el indicador activo en lavanda).
            ->activeColor(theme('primary'))
            ->backgroundColor(theme('surface'))
            ->textColor(theme('on-surface-variant'))
            ->add(Tab::link('Listas', '/lists', ios: 'cart.fill', android: 'shopping_cart'))
            ->add(Tab::link('Recetas', '/recipes', ios: 'book.fill', android: 'menu_book'))
            ->add(Tab::link('Casa', '/house', ios: 'house.fill', android: 'home'))
            ->add(Tab::link('Perfil', '/profile', ios: 'person.fill', android: 'person'));
    }
}
