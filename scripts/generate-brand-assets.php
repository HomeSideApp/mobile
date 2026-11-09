<?php

/**
 * Genera los assets de marca que NativePHP consume en el build:
 *
 *   resources/brand/logo.png  (fuente: el mark canónico de HomeSide)
 *        ├── public/icon.png        1024x1024, opaco (iOS rechaza alfa)
 *        ├── public/splash.png      960x1440, fondo cálido del tema
 *        └── public/splash-dark.png 960x1440, fondo oscuro del tema
 *
 * Uso: php scripts/generate-brand-assets.php
 *
 * Sin dependencias: sólo GD, que ya viene en la imagen de desarrollo.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$source = $root.'/resources/brand/logo.png';

if (! is_file($source)) {
    fwrite(STDERR, "Falta la fuente: {$source}\n");
    exit(1);
}

const LIGHT_BACKGROUND = [0xFB, 0xFC, 0xFA];
const DARK_BACKGROUND = [0x0B, 0x2F, 0x2F];

$logo = imagecreatefrompng($source);

if ($logo === false) {
    fwrite(STDERR, "No pudimos leer {$source}\n");
    exit(1);
}

/**
 * Dibuja el mark centrado y escalado sobre un canvas opaco.
 *
 * @param  array{0: int, 1: int, 2: int}  $background
 */
function compose(GdImage $logo, int $width, int $height, array $background, float $scale, float $verticalCenter = 0.5): GdImage
{
    $canvas = imagecreatetruecolor($width, $height);
    imagealphablending($canvas, true);
    imagesavealpha($canvas, false);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, $background[0], $background[1], $background[2]));

    $markSize = (int) round($width * $scale);
    $mark = imagescale($logo, $markSize, $markSize, IMG_BICUBIC);

    if ($mark === false) {
        $mark = $logo;
        $markSize = imagesx($logo);
    }

    $offsetX = (int) (($width - $markSize) / 2);
    $offsetY = (int) (($height * $verticalCenter) - ($markSize / 2));

    imagecopy($canvas, $mark, $offsetX, $offsetY, 0, 0, $markSize, $markSize);

    return $canvas;
}

function save(GdImage $image, string $path): void
{
    if (! imagepng($image, $path, 9)) {
        fwrite(STDERR, "No pudimos escribir {$path}\n");
        exit(1);
    }

    printf("  %-32s %dx%d\n", basename($path), imagesx($image), imagesy($image));
}

echo "Generando assets de marca\n";

// Icono: 1024x1024 opaco, el mark ocupando ~62% (la zona segura del launcher).
save(compose($logo, 1024, 1024, LIGHT_BACKGROUND, 0.62), $root.'/public/icon.png');

// Splash: full-bleed portrait; NativePHP lo reduce a cada densidad.
save(compose($logo, 960, 1440, LIGHT_BACKGROUND, 0.42, 0.44), $root.'/public/splash.png');
save(compose($logo, 960, 1440, DARK_BACKGROUND, 0.42, 0.44), $root.'/public/splash-dark.png');

echo "Listo. Recompilá con `make android` o `make android-emulator`.\n";
