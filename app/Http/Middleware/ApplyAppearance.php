<?php

namespace App\Http\Middleware;

use App\Services\Settings\AppearanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica la apariencia elegida justo antes de renderizar una pantalla nativa.
 *
 * El cuerpo de las pantallas no lleva tokens: `TailwindParser` resuelve
 * `bg-theme-*` a hex **cuando se parsea la vista**, leyendo el tema del runtime.
 * Eso significa que la última escritura del tema gana, y no alcanza con
 * escribirla en el boot del provider: cualquier provider que recargue la paleta
 * después (el propio `mobile-ui` hace `Theme::load()` en su boot) deja la
 * config con los valores originales y la pantalla se dibuja con el color viejo,
 * aunque el store nativo ya tenga el nuevo.
 *
 * Acá los providers ya arrancaron todos, así que esta escritura es la última
 * antes del render.
 */
final class ApplyAppearance
{
    public function handle(Request $request, Closure $next): Response
    {
        app(AppearanceService::class)->apply();

        return $next($request);
    }
}
