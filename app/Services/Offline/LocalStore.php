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
     * Catálogo. No viaja en el delta (no es parte del alcance offline del
     * servidor), así que se guarda acá para poder ofrecer productos sin red.
     */
    public const string CATEGORIES = 'categories';

    public const string PRODUCTS = 'products';

    /**
     * Relaciones que la UI dibuja y que un servidor viejo puede omitir.
     *
     * Si llegan ausentes se conserva lo último conocido en vez de perder el dato.
     *
     * @var list<string>
     */
    private const CARRIED_RELATIONS = ['product', 'store', 'category', 'household'];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(string $entity, array $payload): void
    {
        $id = $payload['id'] ?? null;

        if (! is_string($id) || $id === '') {
            return;
        }

        // Un servidor que no cargue las relaciones del ítem las omite del todo.
        // Reemplazar la fila borraría el nombre del producto que ya teníamos y
        // la tarjeta pasaría a mostrar el texto de relleno. Se conserva lo que
        // el servidor **no** mencionó; un borrado real llega como null explícito.
        $existing = $this->find($entity, $id);

        if ($existing !== null) {
            foreach (self::CARRIED_RELATIONS as $relation) {
                if (! array_key_exists($relation, $payload) && array_key_exists($relation, $existing)) {
                    $payload[$relation] = $existing[$relation];
                }
            }
        }

        // Por el query builder, no por Eloquent: el modelo no tiene clave
        // primaria simple (la identidad es `entity`+`id`) y `save()` con
        // `$primaryKey = null` termina actualizando **todas** las filas de la
        // tabla. Así, tildar un ítem tildaba la lista entera.
        MirrorEntity::query()->updateOrInsert(
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
     * Borra la fila del espejo.
     *
     * Se usa para las filas optimistas: no son datos del servidor, así que no
     * corresponde un tombstone. Un borrado del servidor va por `forget()`.
     */
    public function remove(string $entity, string $id): void
    {
        MirrorEntity::query()->where('entity', $entity)->where('id', $id)->delete();
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
