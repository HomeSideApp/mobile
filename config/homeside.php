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
    | Actualizaciones de la app (APK)
    |--------------------------------------------------------------------------
    |
    | Cada canal publica un manifiesto JSON como asset de un release de
    | GitHub. `dev` apunta al release rodante `dev-latest`; `stable` usa el
    | permalink `releases/latest`, que GitHub resuelve siempre al último
    | release que no sea prerelease (o sea, al último tag estable).
    |
    | Se leen como assets y no por la API de GitHub a propósito: sin token,
    | la API limita a 60 requests por hora y por IP.
    |
    */

    'updates' => [
        'channels' => [
            'dev' => [
                'manifest' => env(
                    'HOMESIDE_UPDATE_MANIFEST_DEV',
                    'https://github.com/HomeSideApp/mobile/releases/download/dev-latest/latest.json',
                ),
            ],
            'stable' => [
                'manifest' => env(
                    'HOMESIDE_UPDATE_MANIFEST_STABLE',
                    'https://github.com/HomeSideApp/mobile/releases/latest/download/latest.json',
                ),
            ],
        ],
        // Mientras la app esté en 0.x, el canal por defecto es dev: no hay
        // releases estables todavía. Al publicar 1.0 se cambia acá.
        'default_channel' => env('HOMESIDE_UPDATE_CHANNEL', 'dev'),
        'check_interval_hours' => (int) env('HOMESIDE_UPDATE_INTERVAL_HOURS', 24),
        'timeout' => (int) env('HOMESIDE_UPDATE_TIMEOUT', 8),
    ],

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
