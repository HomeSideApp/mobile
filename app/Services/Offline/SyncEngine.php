<?php

namespace App\Services\Offline;

use App\Exceptions\HomesideException;
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

    public function __construct(
        private readonly LocalStore $store = new LocalStore,
        private readonly SyncState $state = new SyncState,
        private readonly Session $session = new Session,
    ) {}

    /**
     * Intenta dejar el dispositivo al día.
     *
     * @return array{pulled: int, pushed: int, pending: int, failed: int, offline: bool}
     */
    public function run(): array
    {
        $result = ['pulled' => 0, 'pushed' => 0, 'pending' => 0, 'failed' => 0, 'offline' => false];

        try {
            $pushed = $this->push();
        } catch (HomesideException) {
            // Sin red: lo pendiente queda en el outbox y la UI sigue con el espejo.
            return [
                'pulled' => 0,
                'pushed' => 0,
                'pending' => $this->pendingCount(),
                'failed' => 0,
                'offline' => true,
            ];
        }

        $result['pushed'] = $pushed['applied'];
        $result['failed'] = $pushed['failed'];

        try {
            $result['pulled'] = $this->pull();
        } catch (HomesideException) {
            $result['offline'] = true;
        }

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

            $operations = $entries->map(fn (OutboxEntry $entry): array => [
                'op_id' => (string) $entry->id,
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
                $entry = $entries->firstWhere('id', (int) ($result['op_id'] ?? 0));

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

    public function pendingCount(): int
    {
        return OutboxEntry::query()->where('state', 'pending')->count();
    }

    public function failedCount(): int
    {
        return OutboxEntry::query()->where('state', 'failed')->count();
    }
}
