<?php

namespace App\Services\Homeside;

use App\Exceptions\HomesideException;

/**
 * Autenticación contra la API de HomeSide.
 *
 * Flujo real del backend (no negociable desde el cliente):
 *   1. `POST /auth/login`       → `temp_token` válido 5 minutos
 *   2. `POST /auth/login/otp`   → token de 30 días + usuario
 *
 * La app no decide nada de eso: sólo transporta los tokens y guarda el de
 * sesión cifrado en el SQLite del dispositivo.
 */
final class AuthService
{
    public function __construct(
        private readonly Session $session = new Session,
    ) {}

    /**
     * @return array{temp_token: string}
     *
     * @throws HomesideException
     */
    public function login(string $email, string $password): array
    {
        $payload = $this->session->guestClient()->post('/auth/login', [
            'email' => trim($email),
            'password' => $password,
        ]);

        $tempToken = $payload['temp_token'] ?? null;

        if (! is_string($tempToken) || $tempToken === '') {
            throw HomesideException::unexpected(200, 'El servidor no devolvió un token de verificación.');
        }

        return ['temp_token' => $tempToken];
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     *
     * @throws HomesideException
     */
    public function loginWithOtp(string $tempToken, string $code): array
    {
        $payload = $this->session->guestClient()
            ->withToken($tempToken)
            ->post('/auth/login/otp', ['code' => trim($code)]);

        $token = $payload['token'] ?? null;
        $user = $payload['user'] ?? null;

        if (! is_string($token) || $token === '') {
            throw HomesideException::unexpected(200, 'El servidor no devolvió la sesión.');
        }

        $user = is_array($user) ? $user : [];

        $this->session->start($token, $user);

        return ['token' => $token, 'user' => $user];
    }

    /**
     * Renueva el `temp_token` para no perder el paso 2 si el usuario tarda.
     */
    public function resendOtp(string $tempToken): string
    {
        $payload = $this->session->guestClient()
            ->withToken($tempToken)
            ->post('/auth/login/otp/resend');

        $fresh = $payload['temp_token'] ?? null;

        if (! is_string($fresh) || $fresh === '') {
            throw HomesideException::unexpected(200, 'No pudimos reenviar el código.');
        }

        return $fresh;
    }

    /**
     * Rota el token de sesión. Devuelve `null` si ya no hay sesión válida.
     */
    public function refresh(): ?string
    {
        try {
            $payload = $this->session->client()->post('/auth/refresh');
        } catch (HomesideException $exception) {
            if ($exception->requiresLogin()) {
                $this->session->end();

                return null;
            }

            throw $exception;
        }

        $token = $payload['token'] ?? null;

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = is_array($payload['user'] ?? null) ? $payload['user'] : ($this->session->user() ?? []);
        $this->session->start($token, $user);

        return $token;
    }

    /**
     * Perfil completo: roles, permisos y capacidades por hogar.
     *
     * @return array<string, mixed>
     *
     * @throws HomesideException
     */
    public function me(): array
    {
        $payload = $this->session->client()->get('/me');
        $user = Session::data($payload);

        $this->session->rememberUser($user);

        return $user;
    }

    public function logout(): void
    {
        try {
            $this->session->client()->post('/auth/logout');
        } catch (HomesideException) {
            // Un logout remoto fallido no debe dejar la sesión local abierta.
        } finally {
            $this->session->end();
        }
    }
}
