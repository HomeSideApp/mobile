<?php

namespace App\Support\Concerns;

/**
 * Acción de barra superior que abre la lista de hogares.
 *
 * La barra la arma el layout, así que la acción tiene que invocar un método de
 * la pantalla: las cuatro pestañas comparten este para no repetirlo cuatro veces.
 */
trait HasHouseholdSwitcher
{
    public function openHouseholds(): void
    {
        $this->navigate('/households');
    }
}
