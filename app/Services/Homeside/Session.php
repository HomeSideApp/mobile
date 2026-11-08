<?php

namespace App\Services\Homeside;

use App\Exceptions\HomesideException;
use App\Services\Settings\SettingsRepository;
use App\Support\ServerUrl;
use Illuminate\Support\Arr;

/**
 * Estado de la sesión de la app: servidor, token y usuario actual.
 *
 * Deliberadamente delgada y sin estado propio: todo vive en el SQLite local,
 * así que reiniciar la app no pierde la sesión y los tests pueden sembrarla.
 */
final class Session
{
    public function __construct(
        private readonly SettingsRepository $settings = new SettingsRepository,
    ) {}

    public function server(): ?ServerUrl
    {
        return $this->settings->server();
    }

    public function isConfigured(): bool
    {
        return $this->settings->isConfigured();
    }

    public function isAuthenticated(): bool
    {
        return $this->settings->isAuthenticated();
    }

    /**
     * Cliente autenticado con el token guardado.
     *
     * @throws HomesideException cuando falta servidor o sesión
     */
    public function client(): HomesideClient
    {
        if (! $this->settings->isConfigured()) {
            throw HomesideException::notConfigured();
        }

        $token = $this->settings->token();

        if ($token === null) {
            throw HomesideException::unauthorized();
        }

        return HomesideClient::for($this->settings->server(), $token);
    }

    /**
     * Cliente sin exigir sesión (login, health).
     */
    public function guestClient(): HomesideClient
    {
        $server = $this->settings->server();

        if ($server === null) {
            throw HomesideException::notConfigured();
        }

        return HomesideClient::for($server);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $raw = $this->settings->get('auth.user');

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function start(string $token, array $user): void
    {
        $this->settings->setToken($token);
        $this->settings->set('auth.user', (string) json_encode($user));
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function rememberUser(array $user): void
    {
        $this->settings->set('auth.user', (string) json_encode($user));
    }

    public function end(): void
    {
        $this->settings->forgetToken();
        $this->settings->set('auth.user', '');
    }

    public function token(): ?string
    {
        return $this->settings->token();
    }

    /**
     * Extrae `data` del envelope de recursos de Laravel sin duplicar la lógica
     * en cada servicio.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int|string, mixed>
     */
    public static function data(array $payload): array
    {
        $data = Arr::get($payload, 'data', $payload);

        return is_array($data) ? $data : [];
    }
}
