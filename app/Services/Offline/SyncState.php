<?php

namespace App\Services\Offline;

use Illuminate\Support\Facades\DB;

/**
 * Cursores y marcas del motor de sincronización.
 */
final class SyncState
{
    public const string CURSOR = 'sync.cursor';

    private const string CATALOG_AT = 'sync.catalog_at';

    public const string LAST_SYNC = 'sync.last_at';

    /**
     * Cuándo se bajó el catálogo por última vez.
     */
    public function catalogSyncedAt(): ?string
    {
        $value = $this->get(self::CATALOG_AT);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setCatalogSyncedAt(string $moment): void
    {
        $this->put(self::CATALOG_AT, $moment);
    }

    public function cursor(): ?string
    {
        $value = $this->get(self::CURSOR);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setCursor(?string $cursor): void
    {
        if ($cursor === null || $cursor === '') {
            return;
        }

        $this->put(self::CURSOR, $cursor);
    }

    public function markSynced(): void
    {
        $this->put(self::LAST_SYNC, now()->toIso8601String());
    }

    public function lastSyncedAt(): ?string
    {
        $value = $this->get(self::LAST_SYNC);

        return is_string($value) ? $value : null;
    }

    public function reset(): void
    {
        DB::table('sync_state')->delete();
    }

    private function get(string $key): ?string
    {
        $row = DB::table('sync_state')->where('key', $key)->first();

        return $row !== null && is_string($row->value ?? null) ? $row->value : null;
    }

    private function put(string $key, string $value): void
    {
        DB::table('sync_state')->updateOrInsert(['key' => $key], ['value' => $value]);
    }
}
