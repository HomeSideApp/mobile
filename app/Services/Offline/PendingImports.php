<?php

namespace App\Services\Offline;

use App\Exceptions\HomesideException;
use App\Services\Homeside\EconomyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cola de tickets capturados sin red.
 *
 * El outbox de sincronización no sirve acá: `/sync/push` sólo alcanza las rutas
 * de escritura del alcance (listas, ítems y movimientos), y la subida de un
 * ticket usa `POST .../imports`. Así que la foto se guarda en disco y se
 * reintenta aparte.
 */
final class PendingImports
{
    /**
     * Guarda la foto en el almacenamiento de la app y la deja en la cola.
     */
    public function enqueue(string $contents, string $filename, ?string $householdId): void
    {
        $directory = storage_path('app/pending-imports');

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $path = $directory.'/'.Str::uuid()->toString().'.jpg';
        @file_put_contents($path, $contents);

        DB::table('pending_imports')->insert([
            'file_path' => $path,
            'filename' => $filename,
            'household_id' => $householdId,
            'attempts' => 0,
            'last_error' => null,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Reintenta todas las subidas pendientes.
     *
     * @return int cantidad enviada
     */
    public function flush(): int
    {
        $sent = 0;

        $rows = DB::table('pending_imports')->orderBy('id')->get();

        foreach ($rows as $row) {
            $path = (string) ($row->file_path ?? '');
            $contents = is_file($path) ? @file_get_contents($path) : false;

            if (! is_string($contents) || $contents === '') {
                // Foto perdida: no tiene sentido dejarla en la cola para siempre.
                DB::table('pending_imports')->where('id', $row->id)->delete();

                continue;
            }

            try {
                (new EconomyService)->createImport(
                    is_string($row->household_id ?? null) ? $row->household_id : null,
                    $contents,
                    (string) ($row->filename ?? 'ticket.jpg'),
                );
            } catch (HomesideException $exception) {
                if ($exception->reason === HomesideException::NETWORK) {
                    // Sigue sin red: se corta acá para no reiterar en vano.
                    break;
                }

                DB::table('pending_imports')->where('id', $row->id)->update([
                    'attempts' => (int) $row->attempts + 1,
                    'last_error' => mb_substr($exception->getMessage(), 0, 500),
                ]);

                continue;
            }

            @unlink($path);
            DB::table('pending_imports')->where('id', $row->id)->delete();
            $sent++;
        }

        return $sent;
    }

    public function count(): int
    {
        return DB::table('pending_imports')->count();
    }

    public function clear(): void
    {
        foreach (DB::table('pending_imports')->get() as $row) {
            if (is_string($row->file_path ?? null) && is_file($row->file_path)) {
                @unlink($row->file_path);
            }
        }

        DB::table('pending_imports')->delete();
    }
}
