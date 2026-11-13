<?php

namespace App\Support;

/**
 * Formato de importes.
 *
 * La API trabaja en **unidades menores** (céntimos) para no perder precisión, así
 * que la app nunca ve floats de dinero: convierte sólo al mostrar.
 */
final class Money
{
    public static function format(int $minor, string $currency = 'EUR'): string
    {
        $amount = $minor / 100;

        // Separadores por idioma: el usuario ve 1.234,56 o 1,234.56 según la app.
        $decimal = str_starts_with((string) app()->getLocale(), 'es') ? ',' : '.';
        $thousands = $decimal === ',' ? '.' : ',';

        $formatted = number_format($amount, 2, $decimal, $thousands);

        return $currency === ''
            ? $formatted
            : $formatted.' '.$currency;
    }

    public static function toMinor(string $amount): ?int
    {
        $clean = str_replace([' ', ','], ['', '.'], trim($amount));

        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (int) round(((float) $clean) * 100);
    }
}
