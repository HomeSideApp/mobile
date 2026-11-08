<?php

namespace App\Support;

use App\Services\Homeside\Session;

/**
 * URLs de los iconos de producto.
 *
 * La API devuelve el nombre del archivo (`apple.webp`); los iconos del catálogo
 * son estáticos y públicos en `/icons/webp/<archivo>`, servidos por la misma
 * instancia de HomeSide a la que está conectada la app.
 */
final class ProductIcon
{
    public static function url(?string $icon): ?string
    {
        if ($icon === null || trim($icon) === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $icon) === 1) {
            return $icon;
        }

        $name = basename(trim($icon));

        if (preg_match('/^[a-z0-9][a-z0-9_-]*\.webp$/i', $name) !== 1) {
            return null;
        }

        $server = app(Session::class)->server();

        if ($server === null) {
            return null;
        }

        return $server->baseUrl.'/icons/webp/'.$name;
    }
}
