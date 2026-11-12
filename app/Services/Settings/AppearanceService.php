<?php

namespace App\Services\Settings;

use Native\Mobile\UI\Theme;

/**
 * Apariencia de la app: sistema, claro u oscuro.
 *
 * `nativephp/mobile-ui` resuelve el tema por la apariencia del sistema, sin una
 * forma de forzarla desde PHP. Lo que sí se puede hacer es **espejar la paleta**:
 * para forzar claro se copian los tokens de `light` sobre el bloque `dark`, así
 * el renderer, aunque el sistema esté en oscuro, dibuja los colores claros.
 *
 * Consecuencia honesta: el chrome del sistema (barra de estado, teclado, el
 * scrim de las hojas) sigue a Android, no a esta elección.
 */
final class AppearanceService
{
    public const string SETTING_KEY = 'app.appearance';

    /** @var list<string> */
    public const array SUPPORTED = ['system', 'light', 'dark'];

    /**
     * Paletas originales, capturadas una sola vez.
     *
     * `Theme::merge()` no sólo cambia los tokens: escribe el resultado en
     * `config('native-ui.theme')`. Después de forzar claro, la config deja de
     * tener el bloque `light`, así que leerla otra vez devolvería un tema ya
     * mutado y "volver a sistema" no podría restaurar nada.
     *
     * @var array{light: array<string, string>, dark: array<string, string>}|null
     */
    private static ?array $palettes = null;

    public function __construct(
        private readonly SettingsRepository $settings = new SettingsRepository,
    ) {}

    public function current(): string
    {
        $stored = $this->settings->get(self::SETTING_KEY);

        return is_string($stored) && in_array($stored, self::SUPPORTED, true)
            ? $stored
            : 'system';
    }

    public function isSupported(string $appearance): bool
    {
        return in_array($appearance, self::SUPPORTED, true);
    }

    public function set(string $appearance): bool
    {
        if (! $this->isSupported($appearance)) {
            return false;
        }

        $this->settings->set(self::SETTING_KEY, $appearance);
        $this->apply($appearance);

        return true;
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function options(): array
    {
        return array_map(fn (string $code): array => [
            'code' => $code,
            'label' => (string) __('app.profile.appearance_'.$code),
        ], self::SUPPORTED);
    }

    /**
     * Aplica la elección al tema en runtime. Nunca tira: en un arranque frío la
     * tabla de ajustes puede no existir todavía.
     */
    public function apply(?string $appearance = null): void
    {
        if (! class_exists(Theme::class)) {
            return;
        }

        try {
            $chosen = $appearance ?? $this->current();
        } catch (\Throwable) {
            $chosen = 'system';
        }

        $palettes = $this->palettes();
        $light = $palettes['light'];
        $dark = $palettes['dark'];

        if ($light === [] || $dark === []) {
            return;
        }

        // Las DOS paletas viajan siempre. Empujar sólo el bloque que se quiere
        // forzar deja el otro vacío en el store del dispositivo, que cae a su
        // paleta de respaldo: el chrome (que sí lee la config en PHP) cambiaba y
        // el cuerpo no.
        match ($chosen) {
            'light' => Theme::merge(['light' => $light, 'dark' => $light]),
            'dark' => Theme::merge(['light' => $dark, 'dark' => $dark]),
            default => Theme::merge(['light' => $light, 'dark' => $dark]),
        };
    }

    /**
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    private function palettes(): array
    {
        if (self::$palettes !== null) {
            return self::$palettes;
        }

        $light = config('native-ui.theme.light');
        $dark = config('native-ui.theme.dark');

        // Si la config ya viene mutada (un merge previo), se lee el archivo: es
        // la única copia intacta de la paleta del proyecto.
        if (! is_array($light) || ! is_array($dark) || $light === [] || $dark === []) {
            $file = is_file(config_path('native-ui.php'))
                ? require config_path('native-ui.php')
                : [];

            $light = $file['theme']['light'] ?? [];
            $dark = $file['theme']['dark'] ?? [];
        }

        /** @var array{light: array<string, string>, dark: array<string, string>} $captured */
        $captured = [
            'light' => is_array($light) ? $light : [],
            'dark' => is_array($dark) ? $dark : [],
        ];

        return self::$palettes = $captured;
    }
}
