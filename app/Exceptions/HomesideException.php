<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Fallo normalizado al hablar con la API de HomeSide.
 *
 * `reason` es un vocabulario cerrado y estable: la UI decide el mensaje final
 * y si ofrece reintentar a partir de él, nunca del status crudo ni del texto
 * del servidor.
 */
final class HomesideException extends RuntimeException
{
    public const string NETWORK = 'network';

    public const string NOT_CONFIGURED = 'not_configured';

    public const string UNAUTHORIZED = 'unauthorized';

    public const string FORBIDDEN = 'forbidden';

    public const string CONFLICT = 'conflict';

    public const string VALIDATION = 'validation';

    /**
     * El plan del usuario no permite más (hogares, recetas, tickets). No es un
     * error del servidor ni falta de permisos: es un límite comercial, y la UI
     * tiene que poder decir eso en vez de "algo salió mal".
     */
    public const string QUOTA = 'quota';

    public const string RATE_LIMITED = 'rate_limited';

    public const string SERVER = 'server';

    public const string UNEXPECTED = 'unexpected';

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?int $status = null,
        public readonly array $errors = [],
        public readonly ?int $retryAfter = null,
        public readonly ?string $serverCode = null,
    ) {
        parent::__construct($message);
    }

    public static function network(string $detail = ''): self
    {
        return new self(
            self::NETWORK,
            $detail !== ''
                ? __('app.errors.network').' '.$detail
                : __('app.errors.network'),
        );
    }

    public static function notConfigured(): self
    {
        return new self(self::NOT_CONFIGURED, __('app.errors.not_configured'));
    }

    public static function unauthorized(): self
    {
        return new self(self::UNAUTHORIZED, __('app.errors.unauthorized'), 401);
    }

    public static function forbidden(?string $serverCode = null, string $message = ''): self
    {
        return new self(
            self::FORBIDDEN,
            $message !== '' ? $message : __('app.errors.forbidden'),
            403,
            serverCode: $serverCode,
        );
    }

    public static function conflict(?string $serverCode = null): self
    {
        return new self(self::CONFLICT, __('app.errors.conflict'), 409, serverCode: $serverCode);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validation(array $errors, string $message = ''): self
    {
        return new self(
            self::VALIDATION,
            $message !== '' ? $message : __('app.errors.validation'),
            422,
            $errors,
        );
    }

    public static function quota(?string $serverCode = null, string $message = ''): self
    {
        return new self(
            self::QUOTA,
            $message !== '' ? $message : __('app.errors.quota'),
            402,
            serverCode: $serverCode,
        );
    }

    public static function rateLimited(?int $retryAfter = null): self
    {
        return new self(
            self::RATE_LIMITED,
            $retryAfter !== null
                ? __('app.errors.rate_limited_wait', ['seconds' => $retryAfter])
                : __('app.errors.rate_limited'),
            429,
            retryAfter: $retryAfter,
        );
    }

    public static function server(int $status): self
    {
        return new self(self::SERVER, __('app.errors.server'), $status);
    }

    public static function unexpected(int $status, string $message = ''): self
    {
        return new self(
            self::UNEXPECTED,
            $message !== '' ? $message : __('app.errors.unexpected'),
            $status,
        );
    }

    /**
     * ¿Tiene sentido que el usuario reintente la misma operación?
     */
    public function isRetryable(): bool
    {
        return in_array($this->reason, [self::NETWORK, self::RATE_LIMITED, self::SERVER], true);
    }

    /**
     * ¿El token dejó de servir y hay que reautenticar?
     */
    public function requiresLogin(): bool
    {
        return $this->reason === self::UNAUTHORIZED;
    }

    /**
     * Primer mensaje de validación, útil para mostrarlo junto al campo.
     */
    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if (is_array($messages) && isset($messages[0])) {
                return (string) $messages[0];
            }
        }

        return null;
    }
}
