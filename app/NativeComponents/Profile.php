<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AppUpdateChecker;
use App\Services\Homeside\AuthService;
use App\Services\Homeside\Session;
use App\Services\Settings\AppearanceService;
use App\Services\Settings\LocaleService;
use Illuminate\View\View;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\TailwindParser;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\System;

/**
 * Perfil: datos de la cuenta, servidor actual, cierre de sesión y cambio de
 * instancia.
 *
 * Los ajustes sensibles (contraseña, 2FA) siguen en la web por ahora: acá se
 * muestra el estado para que el usuario sepa dónde está parado.
 */
final class Profile extends NativeComponent
{
    public ?string $server = null;

    public ?string $userName = null;

    public ?string $userEmail = null;

    public ?string $locale = null;

    /** Idioma elegido en este dispositivo. */
    public string $appLocale = 'es-ES';

    /** @var list<array{code: string, label: string}> */
    public array $locales = [];

    public ?string $languageNotice = null;

    /** @var list<string> Etiquetas para el select de idioma. */
    public array $localeOptions = [];

    /** Etiqueta seleccionada (el select compara strings). */
    public string $localeValue = '';

    /** @var list<string> Etiquetas para el select de canal. */
    public array $channelOptions = [];

    public string $channelValue = '';

    public string $channelDescription = '';

    /** system | light | dark */
    public string $appearance = 'system';

    /** @var list<string> */
    public array $appearanceOptions = [];

    public string $appearanceValue = '';

    public bool $twoFactorEnabled = false;

    public bool $hasSession = false;

    public bool $busy = false;

    public ?string $error = null;

    public string $channel = 'dev';

    /** @var array<string, mixed> */
    public array $update = [];

    public bool $checkingUpdate = false;

    public bool $updateAvailable = false;

    /** Id de la descarga en curso (sólo cuando el plugin está instalado). */
    public ?string $downloadId = null;

    /** idle | running | successful | failed */
    public string $downloadStatus = 'idle';

    public int $downloadProgress = 0;

    public ?string $downloadMessage = null;

    /** @var array<string, array{manifest: string}> */
    public array $channels = [];

    public function mount(): void
    {
        $this->refreshLocales();

        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    /**
     * Refresca el idioma del dispositivo y las opciones del selector.
     *
     * `$locales` es lo que dibuja la lista de idiomas: si queda vacío, la
     * sección aparece con su título y sin nada para elegir.
     */
    private function refreshLocales(): void
    {
        $service = new LocaleService;
        $service->apply();

        $this->appLocale = $service->current();
        $this->locales = $service->options();
        $this->localeOptions = array_column($this->locales, 'label');
        $this->localeValue = $this->localeLabels()[$this->appLocale] ?? '';

        // Las etiquetas de apariencia también están traducidas.
        $this->refreshAppearance();
    }

    private function refreshAppearance(): void
    {
        $service = new AppearanceService;

        $this->appearance = $service->current();
        $this->appearanceOptions = array_values(array_map(
            static fn (array $option): string => $option['label'],
            $service->options(),
        ));
        $this->appearanceValue = (string) __('app.profile.appearance_'.$this->appearance);
    }

    /**
     * El select devuelve la etiqueta traducida; se mapea contra las mismas
     * traducciones con las que se armó la lista.
     */
    /**
     * Panel TEMPORAL de diagnóstico del cambio de apariencia.
     *
     * Existe porque el bug no se reproduce en el emulador y en el dispositivo
     * no hay adb disponible: en vez de leer logcat, la pantalla muestra qué
     * paleta está viendo PHP en este momento. Se borra cuando el problema esté
     * cerrado.
     *
     * @return list<array{label: string, value: string}>
     */
    public function diagnostics(): array
    {
        $parser = class_exists(TailwindParser::class)
            ? TailwindParser::parse('bg-theme-background')
            : [];

        $lightBg = is_string($parser['bg'] ?? null) ? $parser['bg'] : '—';
        $darkBg = is_string($parser['dark']['bg'] ?? null) ? $parser['dark']['bg'] : $lightBg;

        // El renderer elige el hex del companion `dark` cuando Android está en
        // modo oscuro: es la variable que decide qué se ve, y sin ella un
        // diagnóstico de color queda a medias.
        // Comparar el archivo con la config viva delata una config cacheada con
        // las paletas colapsadas, que es lo que deja la app trabada.
        $file = (new AppearanceService)->palettes();

        $systemDark = false;

        try {
            $systemDark = System::isDarkMode();
        } catch (\Throwable) {
            // Sin bridge (tests, arranque sin runtime) no hay dato.
        }

        return [
            ['label' => 'version', 'value' => (string) config('nativephp.version')],
            ['label' => 'appearance', 'value' => (new AppearanceService)->current()],
            ['label' => 'system dark', 'value' => $systemDark ? 'yes' : 'no'],
            ['label' => 'body bg', 'value' => $lightBg],
            ['label' => 'body bg dark', 'value' => $darkBg],
            ['label' => 'body bg used', 'value' => $systemDark ? $darkBg : $lightBg],
            ['label' => 'file light bg', 'value' => (string) ($file['light']['background'] ?? '—')],
            ['label' => 'file dark bg', 'value' => (string) ($file['dark']['background'] ?? '—')],
            ['label' => 'live light bg', 'value' => (string) config('native-ui.theme.light.background')],
            ['label' => 'live dark bg', 'value' => (string) config('native-ui.theme.dark.background')],
            ['label' => 'last merge', 'value' => AppearanceService::lastAppliedLabel()],
        ];
    }

    public function setAppearanceOption(?string $label = null): void
    {
        $label = $label ?? $this->appearanceValue;

        foreach (AppearanceService::SUPPORTED as $code) {
            if ((string) __('app.profile.appearance_'.$code) === $label) {
                (new AppearanceService)->set($code);
                $this->refreshAppearance();

                return;
            }
        }
    }

    /**
     * Etiqueta => código. Las etiquetas son nombres fijos (Español, English),
     * no traducciones, así que el mapeo es estable.
     *
     * @return array<string, string>
     */
    private function localeLabels(): array
    {
        return array_column($this->locales, 'label', 'code');
    }

    /**
     * El select devuelve la etiqueta elegida; acá se vuelve al código.
     */
    public function setLocaleOption(?string $label = null): void
    {
        $label = $label ?? $this->localeValue;
        $code = array_search($label, $this->localeLabels(), true);

        if (is_string($code)) {
            $this->setLocale($code);
        }
    }

    private function refreshChannelOptions(): void
    {
        $checker = new AppUpdateChecker;

        $this->channels = $checker->channels();
        $this->channel = $checker->channel();
        $this->channelOptions = array_values(array_map(
            static fn (string $key): string => (string) __('app.updates.channels.'.$key),
            array_keys($this->channels),
        ));
        $this->channelValue = (string) __('app.updates.channels.'.$this->channel);
        $this->channelDescription = (string) __('app.updates.channels.'.$this->channel.'_description');
    }

    /**
     * El select devuelve la etiqueta traducida del canal; se mapea de vuelta
     * contra las mismas traducciones con las que se armó la lista.
     */
    public function setChannelOption(?string $label = null): void
    {
        $label = $label ?? $this->channelValue;

        foreach (array_keys($this->channels) as $key) {
            if ((string) __('app.updates.channels.'.$key) === $label) {
                $this->setChannel($key);

                return;
            }
        }
    }

    public function navTitle(): string
    {
        return __('app.profile.title');
    }

    /**
     * Cambia el idioma de la app y, si hay sesión, lo guarda en la cuenta.
     *
     * El servidor traduce categorías y productos con `user.locale`, así que
     * dejar los dos desalineados mostraría la UI en un idioma y el catálogo en
     * otro.
     */
    public function setLocale(string $locale): void
    {
        $service = new LocaleService;

        if (! $service->set($locale)) {
            return;
        }

        $this->appLocale = $locale;
        $this->locale = $locale;
        $this->languageNotice = null;
        $this->refreshLocales();
        $this->refreshChannelOptions();

        if (! $this->hasSession) {
            return;
        }

        $cached = (new Session)->user();
        $name = is_array($cached) ? ($cached['name'] ?? null) : null;
        $email = is_array($cached) ? ($cached['email'] ?? null) : null;

        if (! is_string($name) || ! is_string($email)) {
            return;
        }

        try {
            $user = (new AuthService)->updateProfile([
                'name' => $name,
                'email' => $email,
                'locale' => $locale,
            ]);
        } catch (\Throwable) {
            $this->languageNotice = __('app.profile.language_failed');

            return;
        }

        $this->applyUser($user);
        $this->languageNotice = __('app.profile.language_changed');
    }

    public function changeServer(): void
    {
        $this->navigate('/server-setup');
    }

    /**
     * Cambiar de canal invalida el resultado cacheado del canal anterior.
     */
    public function setChannel(string $channel): void
    {
        $checker = new AppUpdateChecker;

        if (! $checker->setChannel($channel)) {
            return;
        }

        $this->channel = $checker->channel();
        $this->refreshChannelOptions();
        $this->applyUpdate($checker->status());
    }

    public function checkForUpdates(): void
    {
        if ($this->checkingUpdate) {
            return;
        }

        $checker = new AppUpdateChecker;

        $this->checkingUpdate = true;
        $this->applyUpdate($checker->status(force: true));
        $this->checkingUpdate = false;
    }

    /**
     * Descarga la actualización.
     *
     * Con el plugin `homeside/apk-updater` instalado, la descarga y el
     * instalador viven dentro de la app. Sin el plugin (o fuera de Android) cae
     * al navegador, que es el único mecanismo que no depende de código nativo.
     */
    public function downloadUpdate(): void
    {
        if ($this->inAppUpdater() === null) {
            $url = $this->update['apk_url'] ?? null;

            if (is_string($url) && $url !== '') {
                Browser::open($url);
                $this->downloadMessage = __('app.updates.opened_browser');
            }

            return;
        }

        if ($this->downloadStatus === 'successful' && $this->downloadId !== null) {
            $this->installDownloadedUpdate();

            return;
        }

        $url = $this->update['apk_url'] ?? null;

        if (! is_string($url) || $url === '') {
            return;
        }

        $options = ['file_name' => 'homeside-update.apk'];

        if (is_string($this->update['sha256'] ?? null)) {
            $options['sha256'] = $this->update['sha256'];
        }

        $result = $this->inAppUpdater()::download($url, $options);

        if (isset($result['error'])) {
            $this->downloadStatus = 'failed';
            $this->downloadMessage = $result['message'] ?? __('app.updates.failed_download');

            return;
        }

        $this->downloadId = $result['id'] ?? null;
        $this->downloadStatus = 'running';
        $this->downloadProgress = 0;
        // El progreso vive en la fila con el spinner; el mensaje queda para
        // avisos y errores. Duplicarlo mostraba dos contadores.
        $this->downloadMessage = null;
    }

    /**
     * Refresca el progreso mientras hay una descarga activa. El atributo Poll
     * sólo re-renderiza; si no hay descarga, no hace nada.
     */
    #[Poll(2000)]
    public function pollDownload(): void
    {
        if ($this->downloadId === null || $this->inAppUpdater() === null) {
            return;
        }

        if (! in_array($this->downloadStatus, ['running', 'idle'], true)) {
            return;
        }

        $status = $this->inAppUpdater()::status($this->downloadId);

        if (isset($status['error'])) {
            $this->downloadStatus = 'failed';
            $this->downloadMessage = $status['message'] ?? __('app.updates.failed_status');

            return;
        }

        $this->downloadProgress = (int) ($status['progress'] ?? 0);
        $this->downloadStatus = (string) ($status['status'] ?? 'running');

        match ($this->downloadStatus) {
            'successful' => $this->installDownloadedUpdate(),
            'failed' => $this->downloadMessage = __('app.updates.download_failed'),
            'paused' => $this->downloadMessage = __('app.updates.paused'),
            default => $this->downloadMessage = null,
        };
    }

    private function installDownloadedUpdate(): void
    {
        if ($this->downloadId === null || $this->inAppUpdater() === null) {
            return;
        }

        try {
            $result = $this->inAppUpdater()::install($this->downloadId);
        } catch (\Throwable) {
            // El plugin es opcional: si el lado nativo no contesta, la pantalla
            // no puede caerse por eso.
            $this->downloadStatus = 'failed';
            $this->downloadMessage = __('app.updates.installer_failed');

            return;
        }

        if (($result['error'] ?? null) === 'unknown_sources_disabled') {
            try {
                $this->inAppUpdater()::openInstallSettings();
            } catch (\Throwable) {
                // El mensaje alcanza aunque no podamos abrir los ajustes.
            }

            $this->downloadMessage = __('app.updates.unknown_sources');

            return;
        }

        if (isset($result['error'])) {
            $this->downloadStatus = 'failed';
            $this->downloadMessage = $result['message'] ?? __('app.updates.installer_failed');

            return;
        }

        $this->downloadMessage = __('app.updates.installer_steps');
    }

    /**
     * Clase del plugin si está instalado y el dispositivo es Android.
     *
     * @return class-string|null
     */
    private function inAppUpdater(): ?string
    {
        $class = 'Homeside\\ApkUpdater\\Facades\\ApkUpdater';

        if (! class_exists($class)) {
            return null;
        }

        try {
            return $class::isSupported() ? $class : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function goToLogin(): void
    {
        $this->navigate('/login');
    }

    public function logout(): void
    {
        if ($this->busy) {
            return;
        }

        $this->busy = true;
        (new AuthService)->logout();
        $this->busy = false;

        $this->hasSession = false;
        $this->userName = null;
        $this->userEmail = null;

        $this->replace('/login');
    }

    private function load(): void
    {
        $session = new Session;

        $this->server = $session->server()?->baseUrl;
        $this->hasSession = $session->isAuthenticated();
        $this->error = null;

        $checker = new AppUpdateChecker;
        $this->channels = $checker->channels();
        $this->channel = $checker->channel();
        $this->refreshChannelOptions();
        $this->applyUpdate($checker->status());

        $cached = $session->user();

        if (is_array($cached)) {
            $this->applyUser($cached);
        }

        if (! $this->hasSession) {
            return;
        }

        try {
            $this->applyUser((new AuthService)->me());
            // `/me` puede haber cambiado el idioma (la cuenta manda).
            $this->refreshLocales();
        } catch (HomesideException $exception) {
            if ($exception->requiresLogin()) {
                $this->hasSession = false;
            }

            $this->error = $exception->getMessage();
        }
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function applyUpdate(array $status): void
    {
        $this->update = $status;
        $this->updateAvailable = (new AppUpdateChecker)->hasUpdate($status);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function applyUser(array $user): void
    {
        $this->userName = is_string($user['name'] ?? null) ? $user['name'] : null;
        $this->userEmail = is_string($user['email'] ?? null) ? $user['email'] : null;
        $this->locale = is_string($user['locale'] ?? null) ? $user['locale'] : null;
        $this->twoFactorEnabled = (bool) ($user['two_factor_enabled'] ?? false);
    }

    public function render(): View
    {
        return view('native.profile');
    }
}
