<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AuthService;
use App\Services\Homeside\Session;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

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
