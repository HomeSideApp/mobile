<?php

namespace App\Support;

/**
 * Colores de las cards de listas.
 *
 * El color de fondo lo manda la API (`category.color`, hex): son datos, no
 * marca, así que no pasan por el tema. Lo que sí se calcula acá es el color del
 * texto: con blanco fijo, los amarillos y grises del catálogo (#FFCA28,
 * #BDBDBD, #FFA726) quedan ilegibles.
 */
final class CardColor
{
    public const string FALLBACK = '#2FA090';

    public const string PENDING = '#B42318';

    public const string CHECKED = '#1E6B63';

    private const string DARK_TEXT = '#153C3A';

    private const string LIGHT_TEXT = '#FFFFFF';

    /**
     * Acepta `#RGB`, `#RRGGBB` y `#RRGGBBAA`; devuelve el que corresponde para
     * el texto que va encima.
     */
    public static function foreground(?string $background): string
    {
        $rgb = self::toRgb($background);

        if ($rgb === null) {
            return self::LIGHT_TEXT;
        }

        [$r, $g, $b] = $rgb;

        $background = self::relativeLuminance($r, $g, $b);

        $contrastWithWhite = self::contrast($background, 1.0);
        $contrastWithDark = self::contrast($background, self::relativeLuminance(21, 60, 58));

        return $contrastWithDark > $contrastWithWhite ? self::DARK_TEXT : self::LIGHT_TEXT;
    }

    /**
     * Normaliza cualquier entrada a un hex de seis dígitos utilizable en una
     * clase `bg-[...]`; si no se puede, devuelve el color de marca por defecto.
     */
    public static function normalize(?string $background, string $fallback = self::FALLBACK): string
    {
        $rgb = self::toRgb($background);

        if ($rgb === null) {
            return $fallback;
        }

        return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * Versión translúcida del color, para fondos suaves (el acordeón de
     * categorías usa el color de la categoría al 12%).
     */
    public static function tint(?string $hex, float $alpha = 0.12): string
    {
        $rgb = self::toRgb($hex);

        if ($rgb === null) {
            $rgb = self::toRgb(self::FALLBACK) ?? [47, 160, 144];
        }

        $byte = (int) round(max(0.0, min(1.0, $alpha)) * 255);

        return sprintf('#%02X%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2], $byte);
    }

    /**
     * @return array{int, int, int}|null
     */
    private static function toRgb(?string $hex): ?array
    {
        if ($hex === null) {
            return null;
        }

        $value = ltrim(trim($hex), '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $value) === 1) {
            $value = $value[0].$value[0].$value[1].$value[1].$value[2].$value[2];
        }

        if (preg_match('/^[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $value) !== 1) {
            return null;
        }

        return [
            (int) hexdec(substr($value, 0, 2)),
            (int) hexdec(substr($value, 2, 2)),
            (int) hexdec(substr($value, 4, 2)),
        ];
    }

    private static function contrast(float $a, float $b): float
    {
        $lighter = max($a, $b);
        $darker = min($a, $b);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function relativeLuminance(int $r, int $g, int $b): float
    {
        $channel = static function (int $value): float {
            $normalized = $value / 255;

            return $normalized <= 0.03928
                ? $normalized / 12.92
                : (($normalized + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }
}
