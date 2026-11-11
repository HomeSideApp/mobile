<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AppUpdateChecker;
use App\Services\Homeside\AuthService;
use App\Services\Homeside\Session;
use Illuminate\View\View;
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
     * Abre el APK en el navegador: el sistema descarga y ofrece instalar.
     */
    public function downloadUpdate(): void
    {
        $url = $this->update['apk_url'] ?? null;

        if (is_string($url) && $url !== '') {
            Browser::open($url);
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
