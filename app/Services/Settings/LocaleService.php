<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\App;

/**
 * Idioma de la app.
 *
 * Hay dos fuentes de verdad y este servicio las concilia:
 *  - el dispositivo, que recuerda la elección aunque no haya sesión (así la
 *    pantalla de conexión y el login ya salen en el idioma correcto);
 *  - la cuenta, que guarda `locale` en el servidor y es la que manda cuando hay
 *    sesión: el backend traduce categorías y productos con ese valor, así que
 *    la app tiene que seguirlo o la UI y el catálogo quedarían en idiomas
 *    distintos.
 *
 * Los locales son los mismos del backend (`AppLocale`): `es-ES` y `en-US`.
 */
final class LocaleService
{
    public const string SETTING_KEY = 'app.locale';

    /** @var list<string> */
    public const array SUPPORTED = ['es-ES', 'en-US'];

    public function __construct(
        private readonly SettingsRepository $settings = new SettingsRepository,
    ) {}

    /**
     * Idioma activo, ya validado contra los soportados.
     */
    public function current(): string
    {
        $stored = $this->settings->get(self::SETTING_KEY);

        if (is_string($stored) && in_array($stored, self::SUPPORTED, true)) {
            return $stored;
        }

        return $this->defaultLocale();
    }

    public function defaultLocale(): string
    {
        $configured = (string) config('app.locale', 'es-ES');

        return in_array($configured, self::SUPPORTED, true) ? $configured : 'es-ES';
    }

    /**
     * Aplica el idioma al runtime de Laravel. Nunca tira: en un arranque frío
     * la tabla de ajustes puede no existir todavía.
     */
    public function apply(?string $locale = null): void
    {
        try {
            $resolved = $locale ?? $this->current();
        } catch (\Throwable) {
            $resolved = $this->defaultLocale();
        }

        App::setLocale(
            in_array($resolved, self::SUPPORTED, true) ? $resolved : $this->defaultLocale(),
        );
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function options(): array
    {
        return array_map(fn (string $code): array => [
            'code' => $code,
            'label' => $this->label($code),
        ], self::SUPPORTED);
    }

    public function label(string $locale): string
    {
        return match ($locale) {
            'es-ES' => 'Español',
            'en-US' => 'English',
            default => $locale,
        };
    }

    public function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    /**
     * Elección local: se aplica y se recuerda en el dispositivo.
     */
    public function set(string $locale): bool
    {
        if (! $this->isSupported($locale)) {
            return false;
        }

        $this->settings->set(self::SETTING_KEY, $locale);
        $this->apply($locale);

        return true;
    }

    /**
     * La cuenta manda cuando hay sesión. Devuelve true si cambió algo.
     *
     * @param  array<string, mixed>  $user
     */
    public function syncFromUser(array $user): bool
    {
        $locale = $user['locale'] ?? null;

        if (! is_string($locale) || ! $this->isSupported($locale)) {
            return false;
        }

        if ($this->safeCurrent() === $locale) {
            return false;
        }

        $this->settings->set(self::SETTING_KEY, $locale);
        $this->apply($locale);

        return true;
    }

    private function safeCurrent(): ?string
    {
        try {
            return $this->current();
        } catch (\Throwable) {
            return null;
        }
    }
}
