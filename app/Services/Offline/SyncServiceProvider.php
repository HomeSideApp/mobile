<?php

namespace App\Services\Offline;

use Illuminate\Support\ServiceProvider;

/**
 * Una sola instancia de cada pieza por request: el motor y el outbox comparten
 * estado y no tiene sentido construir dos.
 */
final class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocalStore::class);
        $this->app->singleton(SyncState::class);
        $this->app->singleton(Outbox::class);
        $this->app->singleton(SyncEngine::class);
        $this->app->singleton(WriteThrough::class);
    }
}
