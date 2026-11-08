<?php

namespace App\Support;

use App\Exceptions\InvalidServerUrl;
use Stringable;

/**
 * Dirección de una instancia de HomeSide, normalizada y validada.
 *
 * Reglas:
 *  - sin esquema se asume https
 *  - solo http/https
 *  - http únicamente en localhost o red privada (el resto de la app se
 *    distribuye por tiendas y viaja por internet: la sesión lleva un token)
 *  - se permite un subdirectorio (`https://casa.example.com/homeside`)
 *  - sin usuario/contraseña, query ni fragmento
 *
 * Es inmutable: la URL se valida una vez y después sólo se compone la base
 * de la API sobre el valor ya canónico.
 */
final readonly class ServerUrl implements Stringable
{
    private function __construct(
        public string $baseUrl,
        public string $apiBase,
    ) {}

    /**
     * @throws InvalidServerUrl
     */
    public static function fromUserInput(?string $input): self
    {
        $raw = trim((string) $input);

        if ($raw === '') {
            throw InvalidServerUrl::empty();
        }

        if (preg_match('~^[a-z][a-z0-9+.\-]*://~i', $raw) !== 1) {
            $raw = 'https://'.$raw;
        }

        $parts = parse_url($raw);

        if ($parts === false || ! isset($parts['host']) || $parts['host'] === '') {
            throw InvalidServerUrl::malformed();
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw InvalidServerUrl::unsupportedScheme($scheme);
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw InvalidServerUrl::malformed();
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ($host === '') {
            throw InvalidServerUrl::malformed();
        }

        $isLocal = self::isLocalHost($host);

        if ($scheme === 'http' && ! $isLocal) {
            throw InvalidServerUrl::insecure();
        }

        if (! $isLocal && ! str_contains($host, '.')) {
            throw InvalidServerUrl::malformed();
        }

        $formattedHost = str_contains($host, ':') ? '['.$host.']' : $host;
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = rtrim($parts['path'] ?? '', '/');

        $baseUrl = $scheme.'://'.$formattedHost.$port.$path;

        return new self(
            baseUrl: $baseUrl,
            apiBase: $baseUrl.self::apiPrefix(),
        );
    }

    public static function default(): self
    {
        return self::fromUserInput((string) config('homeside.default_server'));
    }

    public function healthUrl(): string
    {
        return $this->apiBase.'/health';
    }

    public function host(): string
    {
        return (string) (parse_url($this->baseUrl, PHP_URL_HOST) ?? $this->baseUrl);
    }

    public function isSecure(): bool
    {
        return str_starts_with($this->baseUrl, 'https://');
    }

    public function equals(self $other): bool
    {
        return $this->baseUrl === $other->baseUrl;
    }

    public function __toString(): string
    {
        return $this->baseUrl;
    }

    private static function apiPrefix(): string
    {
        $prefix = (string) config('homeside.api_prefix', '/api/v1');

        return '/'.trim($prefix, '/');
    }

    private static function isLocalHost(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.test') || str_ends_with($host, '.localhost')) {
            return true;
        }

        return self::isPrivateIp($host);
    }

    private static function isPrivateIp(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
