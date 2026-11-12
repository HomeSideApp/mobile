<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * La dirección de servidor que escribió el usuario no es usable.
 *
 * El `reason` es estable para que la UI pueda traducirlo; el mensaje ya viene
 * listo para mostrar.
 */
final class InvalidServerUrl extends InvalidArgumentException
{
    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function empty(): self
    {
        return new self('empty', __('app.server_url.empty'));
    }

    public static function malformed(): self
    {
        return new self('malformed', __('app.server_url.malformed'));
    }

    public static function unsupportedScheme(string $scheme): self
    {
        return new self(
            'unsupported_scheme',
            __('app.server_url.unsupported_scheme', ['scheme' => $scheme]),
        );
    }

    public static function insecure(): self
    {
        return new self('insecure', __('app.server_url.insecure'));
    }
}
