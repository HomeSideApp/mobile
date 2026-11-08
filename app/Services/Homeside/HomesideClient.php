<?php

namespace App\Services\Homeside;

use App\Exceptions\HomesideException;
use App\Support\ServerUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cliente HTTP de la API de HomeSide (v1).
 *
 * Responsabilidades:
 *  - una única base de URL por instancia de servidor
 *  - token opcional (Bearer) para los endpoints autenticados
 *  - `Idempotency-Key` automático en mutaciones: el backend lo exige y
 *    devuelve la respuesta original durante 24 h, así que reintentar es seguro
 *  - traducción de errores HTTP a {@see HomesideException}
 *
 * No conoce la UI ni la sesión: sólo transporte.
 */
final class HomesideClient
{
    public function __construct(
        private readonly ServerUrl $server,
        private readonly ?string $token = null,
    ) {}

    public static function for(ServerUrl $server, ?string $token = null): self
    {
        return new self($server, $token);
    }

    public function server(): ServerUrl
    {
        return $this->server;
    }

    public function withToken(?string $token): self
    {
        return new self($this->server, $token);
    }

    /**
     * Comprobación de conectividad pública (sin token).
     *
     * @return array{status?: string, app?: string, environment?: string}
     */
    public function health(): array
    {
        return $this->send('GET', '/health', authenticated: false);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, query: $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        return $this->send('POST', $path, payload: $payload, idempotencyKey: $idempotencyKey ?? self::newIdempotencyKey());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->send('PUT', $path, payload: $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function patch(string $path, array $payload = []): array
    {
        return $this->send('PATCH', $path, payload: $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $path): array
    {
        return $this->send('DELETE', $path);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $path,
        array $query = [],
        array $payload = [],
        bool $authenticated = true,
        ?string $idempotencyKey = null,
    ): array {
        $url = $this->server->apiBase.'/'.ltrim($path, '/');
        $timeout = $authenticated
            ? (int) config('homeside.timeout', 15)
            : (int) config('homeside.health_timeout', 8);

        $request = Http::baseUrl($this->server->apiBase)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->withHeaders([
                'Accept-Language' => (string) config('homeside.locale', 'es-ES'),
            ]);

        if ($authenticated && $this->token !== null && $this->token !== '') {
            $request = $request->withToken($this->token);
        }

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $options = [];

        if ($query !== []) {
            $options['query'] = $query;
        }

        if ($payload !== []) {
            $options['json'] = $payload;
        }

        try {
            $response = $request->send($method, $url, $options);
        } catch (ConnectionException $exception) {
            throw HomesideException::network($this->connectionDetail($exception));
        }

        if ($response->successful()) {
            $decoded = $response->json();

            return is_array($decoded) ? $decoded : [];
        }

        throw $this->exceptionFor($response);
    }

    private function exceptionFor(Response $response): HomesideException
    {
        $status = $response->status();
        $body = $response->json();
        $message = is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : '';
        $code = is_array($body) && is_string($body['code'] ?? null) ? $body['code'] : null;

        if ($status === 401) {
            return HomesideException::unauthorized();
        }

        if ($status === 403) {
            return HomesideException::forbidden($code, $message);
        }

        if ($status === 409) {
            return $message !== ''
                ? new HomesideException(HomesideException::CONFLICT, $message, 409, serverCode: $code)
                : HomesideException::conflict($code);
        }

        if ($status === 422) {
            $errors = is_array($body) && is_array($body['errors'] ?? null) ? $body['errors'] : [];

            return HomesideException::validation($errors, $message);
        }

        if ($status === 429) {
            return HomesideException::rateLimited($this->retryAfter($response));
        }

        if ($status >= 500) {
            return HomesideException::server($status);
        }

        return HomesideException::unexpected($status, $message);
    }

    private function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }

    private function connectionDetail(ConnectionException $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return 'El servidor tardó demasiado en responder.';
        }

        if (str_contains($message, 'SSL') || str_contains($message, 'certificate')) {
            return 'El certificado del servidor no es válido.';
        }

        if (str_contains($message, 'Could not resolve host')) {
            return 'No encontramos ese dominio.';
        }

        return '';
    }

    private static function newIdempotencyKey(): string
    {
        return (string) Str::uuid();
    }
}
