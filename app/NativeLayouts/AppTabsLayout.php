<?php

namespace App\NativeLayouts;

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
        return NavBar::make()->title($screen->navTitle());
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
