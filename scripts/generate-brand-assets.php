<?php

/**
 * Genera los assets de marca que NativePHP consume en el build:
 *
 *   resources/brand/logo.png  (fuente: el mark canónico de HomeSide)
 *        ├── public/icon.png        1024x1024, opaco (iOS rechaza alfa)
 *        ├── public/splash.png      960x1440, fondo cálido del tema
 *        ├── public/splash-dark.png 960x1440, fondo oscuro del tema
 *        └── public/logo-mark.png   mark recortado y transparente, para la UI
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

/**
 * Recorta el margen transparente del mark.
 *
 * El logo canónico viene con aire alrededor (pensado para el lockup), pero en
 * pantalla a 72pt ese aire hace que el mark se vea chico. Acá se busca la caja
 * real de píxeles opacos y se recorta.
 */
function trimTransparent(GdImage $image): GdImage
{
    $width = imagesx($image);
    $height = imagesy($image);

    $minX = $width;
    $minY = $height;
    $maxX = -1;
    $maxY = -1;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgba = imagecolorat($image, $x, $y);
            $alpha = ($rgba & 0x7F000000) >> 24;

            if ($alpha >= 120) {
                continue;
            }

            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
        }
    }

    if ($maxX < 0 || $maxY < 0) {
        return $image;
    }

    $cropped = imagecreatetruecolor($maxX - $minX + 1, $maxY - $minY + 1);
    imagealphablending($cropped, false);
    imagesavealpha($cropped, true);
    imagecopy($cropped, $image, 0, 0, $minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1);

    return $cropped;
}

/**
 * Mark recortado sobre canvas transparente, listo para la UI nativa.
 */
function markCanvas(GdImage $mark, int $size, float $fill): GdImage
{
    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

    $side = (int) round($size * $fill);
    $scaled = imagescale($mark, $side, $side, IMG_BICUBIC);

    if ($scaled !== false) {
        imagealphablending($canvas, true);
        imagecopy($canvas, $scaled, (int) (($size - $side) / 2), (int) (($size - $side) / 2), 0, 0, $side, $side);
    }

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

// Mark para la UI: transparente, recortado y con un 6% de aire.
save(markCanvas(trimTransparent($logo), 512, 0.88), $root.'/public/logo-mark.png');

// Splash: full-bleed portrait; NativePHP lo reduce a cada densidad.
save(compose($logo, 960, 1440, LIGHT_BACKGROUND, 0.42, 0.44), $root.'/public/splash.png');
save(compose($logo, 960, 1440, DARK_BACKGROUND, 0.42, 0.44), $root.'/public/splash-dark.png');

echo "Listo. Recompilá con `make android` o `make android-emulator`.\n";
