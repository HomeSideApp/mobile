<?php

namespace App\Services\Media;

use Illuminate\Support\Str;
use Native\Mobile\Events\Gallery\MediaSelected;

/**
 * Abre la galería del dispositivo.
 *
 * No usa `Camera::pickImages()->start()` porque su valor de retorno no es de
 * fiar: `PendingMediaPicker::start()` espera `{"status":"success"}`, pero
 * `Camera.PickMedia` devuelve **un mapa vacío** a propósito — el resultado llega
 * después como evento `MediaSelected`. Con esa API, `start()` siempre devuelve
 * false y la app mostraba "no pudimos abrir la galería" con la galería abierta.
 *
 * Acá se llama al bridge directamente: una respuesta con contenido significa
 * "se lanzó"; `null` o vacío significa que no hay implementación nativa (el
 * plugin `nativephp/mobile-camera` no está instalado).
 */
final class GalleryPicker
{
    public function pickImage(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $payload = json_encode([
            'mediaType' => 'image',
            'multiple' => false,
            'maxItems' => 1,
            'id' => (string) Str::uuid(),
            'event' => MediaSelected::class,
        ]);

        $result = nativephp_call('Camera.PickMedia', is_string($payload) ? $payload : '{}');

        return is_string($result) && $result !== '';
    }
}
