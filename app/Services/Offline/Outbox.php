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
        return OutboxEntry::query()->create([
            'op' => strtolower($op),
            'path' => $path,
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
