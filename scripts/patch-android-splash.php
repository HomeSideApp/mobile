<?php

/**
 * Agrega un spinner al splash nativo de Android.
 *
 * NativePHP muestra `public/splash.png` mientras arranca el runtime PHP (unos
 * segundos: descomprime el bundle y bootea Laravel). La imagen es estática, así
 * que en un teléfono lento parece que la app se colgó.
 *
 * El archivo es parte del proyecto Android generado (`nativephp/`, efímero y
 * regenerado por `native:install`), por eso el parche se aplica en cada build
 * desde `scripts/android-build.sh` en vez de editarse a mano. Es idempotente:
 * si el marcador ya está, no hace nada.
 *
 * Uso: php scripts/patch-android-splash.php <ruta a MainActivity.kt>
 */

declare(strict_types=1);

$path = $argv[1] ?? '';

if ($path === '' || ! is_file($path)) {
    fwrite(STDERR, "patch-android-splash: falta la ruta de MainActivity.kt\n");
    exit(1);
}

$marker = '/* HomeSide: spinner del splash */';
$source = (string) file_get_contents($path);

if (str_contains($source, $marker)) {
    exit(0);
}

// Ancla: el final del bloque que decide entre bitmap y texto.
$needle = "            } else if (splashResourceId == 0) {\n                SplashText()\n            }\n";

if (! str_contains($source, $needle)) {
    fwrite(STDERR, "patch-android-splash: no encontramos el ancla en el splash de MainActivity.kt\n");
    exit(2);
}

$snippet = $needle."\n"
    ."            {$marker}\n"
    ."            androidx.compose.material3.CircularProgressIndicator(\n"
    ."                modifier = Modifier\n"
    ."                    .align(Alignment.BottomCenter)\n"
    ."                    .padding(bottom = 88.dp)\n"
    ."                    .size(26.dp),\n"
    ."                color = Color(0xFF1E6B63),\n"
    ."                strokeWidth = 3.dp,\n"
    ."            )\n";

file_put_contents($path, str_replace($needle, $snippet, $source));

echo "  Splash: spinner agregado a MainActivity.kt\n";
