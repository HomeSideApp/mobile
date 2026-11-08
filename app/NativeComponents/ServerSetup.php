<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Exceptions\InvalidServerUrl;
use App\Services\Homeside\HomesideClient;
use App\Services\Homeside\Session;
use App\Services\Settings\SettingsRepository;
use App\Support\ServerUrl;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Pantalla de conexión: elegir la instancia de HomeSide.
 *
 * Por defecto ofrece homeside.cloud; cualquier usuario puede escribir la
 * dirección de su propio servidor. Antes de guardar se prueba `GET /health`,
 * porque un error de red acá es mucho más barato que en el login.
 */
final class ServerSetup extends NativeComponent
{
    public string $server = '';

    public string $status = 'idle';

    public ?string $error = null;

    public ?string $serverName = null;

    public bool $alreadyConfigured = false;

    public function mount(): void
    {
        $settings = new SettingsRepository;
        $saved = $settings->server();

        $this->alreadyConfigured = $saved !== null;
        $this->server = $saved?->baseUrl ?? (string) config('homeside.default_server');
    }

    public function navTitle(): string
    {
        return 'Conectar';
    }

    /**
     * El input cambió: limpiar el resultado anterior mientras escribe.
     */
    public function onServerEdited(?string $value = null): void
    {
        if (is_string($value) && trim($value) !== '') {
            $this->server = $value;
        }

        $this->status = 'idle';
        $this->error = null;
        $this->serverName = null;
    }

    /**
     * Prueba la conexión y, si responde, guarda el servidor y sigue.
     */
    public function checkConnection(?string $submitted = null): void
    {
        if (is_string($submitted) && trim($submitted) !== '') {
            $this->server = $submitted;
        }

        $this->status = 'checking';
        $this->error = null;
        $this->serverName = null;

        try {
            $url = ServerUrl::fromUserInput($this->server);
        } catch (InvalidServerUrl $exception) {
            $this->fail($exception->getMessage());

            return;
        }

        try {
            $health = HomesideClient::for($url)->health();
        } catch (HomesideException $exception) {
            $this->fail($exception->getMessage());

            return;
        }

        $app = is_string($health['app'] ?? null) ? $health['app'] : null;
        $isHealthy = ($health['status'] ?? null) === 'ok';

        if (! $isHealthy) {
            $this->fail('El servidor respondió, pero no parece un HomeSide.');

            return;
        }

        (new SettingsRepository)->setServer($url);

        $this->server = $url->baseUrl;
        $this->serverName = $app ?? $url->host();
        $this->status = 'ok';
        $this->alreadyConfigured = true;

        $this->navigate((new Session)->isAuthenticated() ? '/lists' : '/login');
    }

    /**
     * Seguir sin volver a probar (el servidor ya está guardado).
     */
    public function continueToApp(): void
    {
        if (! $this->alreadyConfigured) {
            $this->checkConnection();

            return;
        }

        $this->navigate((new Session)->isAuthenticated() ? '/lists' : '/login');
    }

    private function fail(string $message): void
    {
        $this->status = 'error';
        $this->error = $message;
    }

    public function render(): View
    {
        return view('native.server-setup');
    }
}
