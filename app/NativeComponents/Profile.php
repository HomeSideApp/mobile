<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AppUpdateChecker;
use App\Services\Homeside\AuthService;
use App\Services\Homeside\Session;
use Illuminate\View\View;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

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

    /** @var array<string, array{label: string, description: string}> */
    public array $channels = [];

    public function mount(): void
    {
        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    public function navTitle(): string
    {
        return 'Perfil';
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
                $this->downloadMessage = 'Abrimos la descarga en el navegador.';
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
            $this->downloadMessage = $result['message'] ?? 'No pudimos descargar la actualización.';

            return;
        }

        $this->downloadId = $result['id'] ?? null;
        $this->downloadStatus = 'running';
        $this->downloadProgress = 0;
        $this->downloadMessage = 'Descargando…';
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
            $this->downloadMessage = $status['message'] ?? 'No pudimos leer el estado de la descarga.';

            return;
        }

        $this->downloadProgress = (int) ($status['progress'] ?? 0);
        $this->downloadStatus = (string) ($status['status'] ?? 'running');

        match ($this->downloadStatus) {
            'successful' => $this->installDownloadedUpdate(),
            'failed' => $this->downloadMessage = 'La descarga falló. Probá de nuevo.',
            'paused' => $this->downloadMessage = 'La descarga está en pausa.',
            default => $this->downloadMessage = 'Descargando… '.$this->downloadProgress.'%',
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
            $this->downloadMessage = 'No pudimos abrir el instalador de Android.';

            return;
        }

        if (($result['error'] ?? null) === 'unknown_sources_disabled') {
            try {
                $this->inAppUpdater()::openInstallSettings();
            } catch (\Throwable) {
                // El mensaje alcanza aunque no podamos abrir los ajustes.
            }

            $this->downloadMessage = 'Habilitá «instalar apps desconocidas» y volvé a intentar.';

            return;
        }

        if (isset($result['error'])) {
            $this->downloadStatus = 'failed';
            $this->downloadMessage = $result['message'] ?? 'No pudimos abrir el instalador.';

            return;
        }

        $this->downloadMessage = 'Seguí los pasos del instalador de Android.';
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
