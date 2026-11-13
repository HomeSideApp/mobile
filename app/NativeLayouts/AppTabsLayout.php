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
        $name = $this->activeHouseholdName();

        // Sin hogar resoluble (sin sesión, o un id que ya no existe) la barra
        // queda como antes: título de la pantalla y sin selector.
        if ($name === null) {
            return NavBar::make()->title($screen->navTitle());
        }

        // El hogar activo va de **título** y la pantalla de subtítulo: es el
        // contexto que condiciona todo el contenido, y una acción de barra en
        // Android se colapsa a un menú de tres puntos (no muestra su etiqueta),
        // así que esconderlo ahí dejaba al usuario sin saber dónde estaba.
        // La acción queda como el toque que abre el selector.
        return NavBar::make()
            ->title($name)
            ->subtitle($screen->navTitle())
            ->action(
                NavAction::make('households')
                    ->label((string) __('app.house.switch_short'))
                    ->a11yLabel((string) __('app.house.switch'))
                    ->press('openHouseholds'),
            );
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
            ->add(Tab::link((string) __('app.tabs.lists'), '/lists', ios: 'cart.fill', android: 'shopping_cart'))
            ->add(Tab::link((string) __('app.tabs.recipes'), '/recipes', ios: 'book.fill', android: 'menu_book'))
            ->add(Tab::link((string) __('app.tabs.economy'), '/expenses', ios: 'creditcard.fill', android: 'credit_card'))
            ->add(Tab::link((string) __('app.tabs.house'), '/house', ios: 'house.fill', android: 'home'))
            ->add(Tab::link((string) __('app.tabs.profile'), '/profile', ios: 'person.fill', android: 'person'));
    }
}
