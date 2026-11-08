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
                ? 'No pudimos conectar con el servidor. '.$detail
                : 'No pudimos conectar con el servidor. Revisá tu conexión y la dirección.',
        );
    }

    public static function notConfigured(): self
    {
        return new self(
            self::NOT_CONFIGURED,
            'Todavía no elegiste un servidor de HomeSide.',
        );
    }

    public static function unauthorized(): self
    {
        return new self(
            self::UNAUTHORIZED,
            'Tu sesión venció. Volvé a iniciar sesión.',
            401,
        );
    }

    public static function forbidden(?string $serverCode = null, string $message = ''): self
    {
        return new self(
            self::FORBIDDEN,
            $message !== '' ? $message : 'Tu cuenta no tiene permiso para hacer esto.',
            403,
            serverCode: $serverCode,
        );
    }

    public static function conflict(?string $serverCode = null): self
    {
        return new self(
            self::CONFLICT,
            'Ese recurso ya cambió. Actualizá y volvé a intentar.',
            409,
            serverCode: $serverCode,
        );
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validation(array $errors, string $message = ''): self
    {
        return new self(
            self::VALIDATION,
            $message !== '' ? $message : 'Revisá los datos ingresados.',
            422,
            $errors,
        );
    }

    public static function rateLimited(?int $retryAfter = null): self
    {
        return new self(
            self::RATE_LIMITED,
            $retryAfter !== null
                ? sprintf('Demasiados intentos. Esperá %d segundos.', $retryAfter)
                : 'Demasiados intentos. Esperá un momento.',
            429,
            retryAfter: $retryAfter,
        );
    }

    public static function server(int $status): self
    {
        return new self(
            self::SERVER,
            'El servidor de HomeSide está teniendo un problema. Probá de nuevo en unos minutos.',
            $status,
        );
    }

    public static function unexpected(int $status, string $message = ''): self
    {
        return new self(
            self::UNEXPECTED,
            $message !== '' ? $message : 'Ocurrió un error inesperado.',
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
