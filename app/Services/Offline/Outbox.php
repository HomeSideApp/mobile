<?php

namespace App\Services\Offline;

use App\Models\OutboxEntry;
use Illuminate\Support\Str;

/**
 * Cola de escrituras pendientes.
 *
 * Cada operación lleva su clave de idempotencia desde que se encola: si la red se
 * corta después de que el servidor procesó, el reintento replaya en vez de
 * duplicar.
 */
final class Outbox
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function enqueue(
        string $op,
        string $path,
        array $payload,
        ?string $entity = null,
        ?string $entityId = null,
    ): OutboxEntry {
        // Mismo recurso, misma operación, todavía sin enviar: el toque nuevo
        // reemplaza al anterior. Sin esto un ítem tildado y destildado offline
        // encolaba dos PUT que después competían entre sí en el push.
        if ($entity !== null && $entityId !== null) {
            $pending = OutboxEntry::query()
                ->where('entity', $entity)
                ->where('entity_id', $entityId)
                ->where('op', strtolower($op))
                ->where('state', 'pending')
                ->where('attempts', 0)
                ->first();

            if ($pending !== null) {
                $pending->update([
                    'payload' => json_encode($payload),
                    'created_at' => now()->toIso8601String(),
                ]);

                return $pending;
            }
        }

        return OutboxEntry::query()->create([
            'op' => strtolower($op),
            'path' => $this->apiPath($path),
            'payload' => json_encode($payload),
            'idempotency_key' => (string) Str::uuid(),
            'entity' => $entity,
            'entity_id' => $entityId,
            'attempts' => 0,
            'last_error' => null,
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
            'sent_at' => null,
        ]);
    }

    /**
     * Ruta API-relativa, como la espera `/sync/push`.
     *
     * El cliente HTTP resuelve las rutas contra `.../api/v1`, así que las
     * pantallas encolan `/lists/{id}/items/{item}` y eso online funciona. El
     * push es otra historia: su contrato es `/v1/...` y el servidor sólo le
     * antepone `/api` cuando ve ese prefijo. Sin la normalización la operación
     * apunta a una ruta inexistente y queda fallando para siempre.
     */
    private function apiPath(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return str_starts_with($path, '/v1/') || $path === '/v1'
            ? $path
            : '/v1'.$path;
    }

    public function pending(): int
    {
        return OutboxEntry::query()->where('state', 'pending')->count();
    }

    public function failed(): int
    {
        return OutboxEntry::query()->where('state', 'failed')->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return OutboxEntry::query()->orderBy('id')->get()->map(fn (OutboxEntry $entry): array => [
            'id' => $entry->id,
            'op' => $entry->op,
            'path' => $entry->path,
            'state' => $entry->state,
            'attempts' => $entry->attempts,
            'last_error' => $entry->last_error,
        ])->all();
    }

    public function clear(): void
    {
        OutboxEntry::query()->delete();
    }
}
