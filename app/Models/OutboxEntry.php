<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Escritura pendiente de enviar al servidor.
 *
 * La clave de idempotencia se genera al encolar y **no** cambia entre reintentos:
 * es lo que evita duplicados cuando la red falla después de que el servidor
 * procesó la operación.
 */
final class OutboxEntry extends Model
{
    protected $table = 'outbox';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'op', 'path', 'payload', 'idempotency_key', 'entity', 'entity_id',
        'attempts', 'last_error', 'state', 'created_at', 'sent_at',
    ];

    /** @return array<string, mixed> */
    public function body(): array
    {
        $decoded = json_decode((string) $this->payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
