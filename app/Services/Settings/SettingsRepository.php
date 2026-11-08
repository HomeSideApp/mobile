<?php

namespace App\Services\Settings;

use App\Exceptions\HomesideException;
use App\Exceptions\InvalidServerUrl;
use App\Models\Setting;
use App\Services\Homeside\HomesideClient;
use App\Support\ServerUrl;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Ajustes persistentes de la app en el SQLite local.
 *
 * - `server.url`: instancia de HomeSide elegida por el usuario
 * - `auth.token`: token de Sanctum, cifrado con la APP_KEY del dispositivo
 *
 * El token va cifrado a propósito: NativePHP crea una APP_KEY por instalación
 * y la guarda en el Keychain/Keystore, así que la base no expone credenciales
 * si alguien copia el archivo. Si el valor ya no se puede descifrar (restore en
 * otro dispositivo) se descarta y la app pide login otra vez.
 */
final class SettingsRepository
{
    private const string SERVER_KEY = 'server.url';

    private const string TOKEN_KEY = 'auth.token';

    public function server(): ?ServerUrl
    {
        $value = $this->read(self::SERVER_KEY);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return ServerUrl::fromUserInput($value);
        } catch (InvalidServerUrl) {
            // Un valor corrupto no debe dejar la app en un estado raro.
            $this->forget(self::SERVER_KEY);

            return null;
        }
    }

    public function setServer(ServerUrl $server): void
    {
        $this->write(self::SERVER_KEY, $server->baseUrl);

        // Cambiar de instancia invalida la sesión: el token pertenece al
        // servidor anterior.
        $this->forgetToken();
    }

    public function forgetServer(): void
    {
        $this->forget(self::SERVER_KEY);
        $this->forgetToken();
    }

    public function token(): ?string
    {
        $stored = $this->read(self::TOKEN_KEY);

        if ($stored === null || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            $this->forget(self::TOKEN_KEY);

            return null;
        }
    }

    public function setToken(string $token): void
    {
        $this->write(self::TOKEN_KEY, Crypt::encryptString($token));
    }

    public function forgetToken(): void
    {
        $this->forget(self::TOKEN_KEY);
    }

    public function isConfigured(): bool
    {
        return $this->server() !== null;
    }

    public function isAuthenticated(): bool
    {
        return $this->token() !== null;
    }

    /**
     * Cliente listo para hablar con la instancia configurada.
     *
     * @throws HomesideException si todavía no hay servidor
     */
    public function client(): HomesideClient
    {
        $server = $this->server();

        if ($server === null) {
            throw HomesideException::notConfigured();
        }

        return HomesideClient::for($server, $this->token());
    }

    public function reset(): void
    {
        Setting::query()->delete();
    }

    private function read(string $key): ?string
    {
        $value = Setting::query()->whereKey($key)->value('value');

        return is_string($value) ? $value : null;
    }

    private function write(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function forget(string $key): void
    {
        Setting::query()->whereKey($key)->delete();
    }
}
