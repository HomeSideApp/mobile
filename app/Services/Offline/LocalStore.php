<?php

namespace App\Services\Offline;

use App\Models\MirrorEntity;

/**
 * Espejo local de los resources de la API.
 *
 * Es la única puerta de lectura para las pantallas: mientras se pueda leer de
 * acá, la UI no espera a la red.
 */
final class LocalStore
{
    public const string HOUSEHOLDS = 'households';

    public const string LISTS = 'lists';

    public const string LIST_ITEMS = 'list_items';

    public const string TRANSACTIONS = 'economy_transactions';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(string $entity, array $payload): void
    {
        $id = $payload['id'] ?? null;

        if (! is_string($id) || $id === '') {
            return;
        }

        MirrorEntity::query()->updateOrCreate(
            ['entity' => $entity, 'id' => $id],
            [
                'payload' => json_encode($payload),
                'updated_at' => is_string($payload['updated_at'] ?? null) ? $payload['updated_at'] : null,
                'deleted_at' => null,
                'fetched_at' => now()->toIso8601String(),
            ],
        );
    }

    /**
     * Marca un id como borrado. La fila no se elimina: si el servidor la vuelve
     * a mandar (otra página, un replay) el estado tiene que seguir siendo
     * "borrado" hasta que la poda la saque.
     */
    public function forget(string $entity, string $id): void
    {
        MirrorEntity::query()
            ->where('entity', $entity)
            ->where('id', $id)
            ->update(['deleted_at' => now()->toIso8601String()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $entity): array
    {
        return MirrorEntity::query()
            ->where('entity', $entity)
            ->whereNull('deleted_at')
            ->get()
            ->map(fn (MirrorEntity $row): array => $row->data())
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $entity, string $id): ?array
    {
        $row = MirrorEntity::query()
            ->where('entity', $entity)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row?->data();
    }

    /**
     * Ítems de una lista, ordenados como los muestra la pantalla.
     *
     * @return array<int, array<string, mixed>>
     */
    public function itemsOf(string $listId): array
    {
        return array_values(array_filter(
            $this->all(self::LIST_ITEMS),
            fn (array $item): bool => ($item['list_id'] ?? null) === $listId,
        ));
    }

    public function isEmpty(string $entity): bool
    {
        return ! MirrorEntity::query()->where('entity', $entity)->exists();
    }

    public function clear(): void
    {
        MirrorEntity::query()->delete();
    }
}
