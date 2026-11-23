<?php

namespace App\Services\Offline;

use App\Exceptions\HomesideException;
use App\Models\MirrorEntity;
use App\Models\OutboxEntry;
use App\Services\Homeside\Session;

/**
 * Motor de sincronización: baja el delta y sube el outbox.
 *
 * El orden importa. Primero se sube lo pendiente (para no perder cambios locales
 * antes de traer el estado del servidor) y después se baja el delta, que ya
 * incluye esas escrituras.
 */
final class SyncEngine
{
    /** La API acepta hasta 50 operaciones por lote. */
    private const int BATCH_SIZE = 50;

    /** Días que se guardan los tombstones antes de podarlos. */
    private const int TOMBSTONE_DAYS = 30;

    public function __construct(
        private readonly LocalStore $store = new LocalStore,
        private readonly SyncState $state = new SyncState,
        private readonly Session $session = new Session,
        private readonly PendingImports $imports = new PendingImports,
        private readonly CatalogSync $catalog = new CatalogSync,
    ) {}

    /**
     * Intenta dejar el dispositivo al día.
     *
     * @return array{pulled: int, pushed: int, pending: int, failed: int, offline: bool}
     */
    public function run(): array
    {
        $result = [
            'pulled' => 0, 'pushed' => 0, 'pending' => 0, 'failed' => 0,
            'offline' => false, 'needsLogin' => false,
        ];

        try {
            $pushed = $this->push();
            $result['pushed'] = $pushed['applied'];
            $result['failed'] = $pushed['failed'];
        } catch (HomesideException $exception) {
            // Subir y bajar son independientes: si el push falla, lo pendiente
            // queda en el outbox y la bajada tiene que correr igual. Cortar acá
            // dejaba el espejo congelado —una lista borrada en el servidor
            // seguía apareciendo— por un solo pendiente trabado.
            //
            // Una sesión vencida **no** es falta de red: mostrarla como "sin
            // conexión" dejaría al usuario tildando cosas que nunca se van a
            // sincronizar.
            $result['offline'] = ! $exception->requiresLogin();
            $result['needsLogin'] = $exception->requiresLogin();
            // Un rechazo del servidor no es falta de red: la pantalla tiene que
            // poder decir por qué en vez de mostrar el espejo como si nada.
            $result['pushError'] = $result['offline'] ? null : $exception->getMessage();
        }

        try {
            $result['pulled'] = $this->pull();
        } catch (HomesideException $exception) {
            $result['offline'] = ! $exception->requiresLogin();
            $result['needsLogin'] = $result['needsLogin'] || $exception->requiresLogin();
        }

        // El delta no trae catálogo: sin esto, sin red no hay productos que
        // agregar salvo los que se hayan abierto a mano.
        if (! $result['offline']) {
            try {
                if ($this->catalog->refresh()) {
                    $result['catalog'] = true;
                }
            } catch (HomesideException) {
                // Lo que había sigue sirviendo.
            }
        }

        // Filas optimistas que el servidor ya confirmó: se retiran solas.
        $this->settlePendingItems();

        // Tickets capturados sin red: su endpoint no pasa por /sync/push.
        if (! $result['offline']) {
            $this->imports->flush();
        }

        $this->pruneTombstones();

        $result['pending'] = $this->pendingCount();

        return $result;
    }

    /**
     * Baja páginas del delta hasta agotarlas.
     */
    public function pull(): int
    {
        $applied = 0;
        $cursor = $this->state->cursor();
        $guard = 0;

        do {
            $page = Session::data($this->session->client()->get('/sync/changes', array_filter([
                'since' => $cursor,
                'limit' => 500,
            ])));

            $changes = is_array($page['changes'] ?? null) ? $page['changes'] : [];
            $deleted = is_array($page['deleted'] ?? null) ? $page['deleted'] : [];

            foreach ($changes as $entity => $rows) {
                if (! is_string($entity) || ! is_array($rows)) {
                    continue;
                }

                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $this->store->put($entity, $row);
                        $applied++;
                    }
                }
            }

            foreach ($deleted as $entity => $ids) {
                if (! is_string($entity) || ! is_array($ids)) {
                    continue;
                }

                foreach ($ids as $id) {
                    if (is_string($id)) {
                        $this->store->forget($entity, $id);
                    }
                }
            }

            $cursor = is_string($page['cursor'] ?? null) ? $page['cursor'] : $cursor;
            $this->state->setCursor($cursor);

            $hasMore = ($page['has_more'] ?? false) === true;
            $guard++;
            // Tope defensivo: una API que devuelva siempre has_more no puede
            // dejar el arranque en un bucle infinito.
        } while ($hasMore && $guard < 200);

        $this->state->markSynced();

        return $applied;
    }

    /**
     * Vacía el outbox en lotes, respetando el orden de creación.
     *
     * @return array{applied: int, failed: int}
     */
    public function push(): array
    {
        $applied = 0;
        $failed = 0;

        while (true) {
            $entries = OutboxEntry::query()
                ->where('state', 'pending')
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->get();

            if ($entries->isEmpty()) {
                break;
            }

            // `op_id` tiene que ser un UUID (el endpoint lo valida así), y el
            // id numérico de la fila no lo es: el lote entero volvía 422 y el
            // motor lo interpretaba como falta de red, sin decir nada.
            $operations = $entries->map(fn (OutboxEntry $entry): array => [
                'op_id' => (string) $entry->idempotency_key,
                'method' => strtoupper((string) $entry->op),
                'path' => (string) $entry->path,
                'body' => $entry->body(),
                'idempotency_key' => (string) $entry->idempotency_key,
            ])->all();

            $payload = Session::data(
                $this->session->client()->post('/sync/push', ['operations' => $operations]),
            );

            /** @var array<int, array<string, mixed>> $results */
            $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];

            foreach ($results as $result) {
                $entry = $entries->first(
                    fn (OutboxEntry $candidate): bool => (string) $candidate->idempotency_key === (string) ($result['op_id'] ?? ''),
                );

                if (! $entry instanceof OutboxEntry) {
                    continue;
                }

                $status = (string) ($result['status'] ?? 'failed');

                if ($status === 'applied') {
                    $this->applyResult($entry, $result);
                    $entry->delete();
                    $applied++;

                    continue;
                }

                // conflicto o rechazo: no se reintenta solo, se marca para que la
                // pantalla lo muestre. Los 5xx sí se reintentan.
                $entry->update([
                    'attempts' => $entry->attempts + 1,
                    'last_error' => mb_substr(json_encode($result['body'] ?? []) ?: '', 0, 1000),
                    'state' => $status === 'failed' ? 'pending' : 'failed',
                ]);

                $failed++;
            }
        }

        return ['applied' => $applied, 'failed' => $failed];
    }

    /**
     * Refleja en el espejo lo que el servidor aceptó, para que la UI no dependa
     * del próximo delta para ver su propio cambio.
     *
     * @param  array<string, mixed>  $result
     */
    private function applyResult(OutboxEntry $entry, array $result): void
    {
        $entity = (string) $entry->entity;
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];

        if ($entity === '' || $body === []) {
            return;
        }

        $resource = is_array($body['data'] ?? null) ? $body['data'] : $body;

        if (($resource['id'] ?? null) === null) {
            return;
        }

        if (strtoupper((string) $entry->op) === 'DELETE') {
            $this->store->forget($entity, (string) $entry->entity_id);

            return;
        }

        $this->store->put($entity, $resource);
    }

    /**
     * Retira las filas optimistas que el servidor ya reemplazó.
     *
     * Un ítem creado sin red se muestra con un id local porque el producto nace
     * en el servidor. Cuando el delta trae el ítem real de esa lista con el
     * mismo nombre, la fila local sobra: se borra para no mostrar dos.
     */
    private function settlePendingItems(): void
    {
        $rows = $this->store->all(LocalStore::LIST_ITEMS);

        if ($rows === []) {
            return;
        }

        $confirmed = [];

        foreach ($rows as $row) {
            if (($row['pending_sync'] ?? false) === true) {
                continue;
            }

            $listId = is_string($row['list_id'] ?? null) ? $row['list_id'] : '';
            $name = $this->itemName($row);

            if ($listId !== '' && $name !== '') {
                $confirmed[$listId][$name] = true;
            }
        }

        foreach ($rows as $row) {
            if (($row['pending_sync'] ?? false) !== true) {
                continue;
            }

            $listId = is_string($row['list_id'] ?? null) ? $row['list_id'] : '';
            $name = $this->itemName($row);

            if ($listId !== '' && $name !== '' && isset($confirmed[$listId][$name])) {
                $this->store->remove(LocalStore::LIST_ITEMS, (string) ($row['id'] ?? ''));
            }
        }
    }

    /**
     * Nombre normalizado de un ítem, venga del producto o del nombre libre.
     *
     * @param  array<string, mixed>  $row
     */
    private function itemName(array $row): string
    {
        $custom = is_string($row['custom_name'] ?? null) ? $row['custom_name'] : '';
        $product = is_array($row['product'] ?? null) && is_string($row['product']['name'] ?? null)
            ? $row['product']['name']
            : '';

        return mb_strtolower(trim($custom !== '' ? $custom : $product));
    }

    /**
     * Suelta los tombstones viejos.
     *
     * Las filas vivas **no** se podan: en el teléfono son la única copia que el
     * usuario tiene cuando no hay red.
     */
    private function pruneTombstones(): void
    {
        $limit = now()->subDays(self::TOMBSTONE_DAYS)->toIso8601String();

        MirrorEntity::query()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $limit)
            ->delete();
    }

    public function pendingCount(): int
    {
        return OutboxEntry::query()->where('state', 'pending')->count();
    }

    public function failedCount(): int
    {
        return OutboxEntry::query()->where('state', 'failed')->count();
    }
}
