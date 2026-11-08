<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Servidor por defecto
    |--------------------------------------------------------------------------
    |
    | Instancia que se ofrece en la pantalla de conexión. Cualquier usuario
    | puede reemplazarla por la dirección de su propio HomeSide (self-hosted).
    |
    */

    'default_server' => env('HOMESIDE_DEFAULT_SERVER', 'https://homeside.cloud'),

    /*
    |--------------------------------------------------------------------------
    | Prefijo de la API
    |--------------------------------------------------------------------------
    |
    | HomeSide expone la API versionada en `/api/v1`.
    |
    */

    'api_prefix' => env('HOMESIDE_API_PREFIX', '/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Tiempos de espera (segundos)
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('HOMESIDE_HTTP_TIMEOUT', 15),

    'health_timeout' => (int) env('HOMESIDE_HEALTH_TIMEOUT', 8),

    /*
    |--------------------------------------------------------------------------
    | Locale que se envía a la API
    |--------------------------------------------------------------------------
    |
    | HomeSide resuelve traducciones por el locale del usuario, pero el header
    | viaja igual para que una sesión recién creada responda en el idioma de la
    | app antes del primer `PATCH /me/profile`.
    |
    */

    'locale' => env('HOMESIDE_LOCALE', 'es-ES'),

];
