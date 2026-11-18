<?php

namespace App\Services\Offline;

use App\Exceptions\HomesideException;

/**
 * Escribe contra la API y, si no hay red, deja la operación en el outbox.
 *
 * Es la costura mínima para que una pantalla existente gane comportamiento
 * offline sin reescribir su flujo: la llamada real se intenta igual que hoy y
 * sólo el fallo de transporte cambia de destino. Un 422 sigue siendo un 422.
 */
final class WriteThrough
{
    public function __construct(
        private readonly Outbox $outbox = new Outbox,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  lo que se enviaría, para el outbox
     * @param  callable(): mixed  $online  la llamada real a la API
     * @return bool true si se aplicó online, false si quedó encolada
     */
    public function run(
        string $op,
        string $path,
        array $payload,
        callable $online,
        ?string $entity = null,
        ?string $entityId = null,
    ): bool {
        try {
            $online();

            return true;
        } catch (HomesideException $exception) {
            if (! $this->isTransport($exception)) {
                throw $exception;
            }

            $this->outbox->enqueue($op, $path, $payload, $entity, $entityId);

            return false;
        }
    }

    private function isTransport(HomesideException $exception): bool
    {
        return in_array($exception->reason, [
            HomesideException::NETWORK,
            HomesideException::NOT_CONFIGURED,
        ], true);
    }
}
