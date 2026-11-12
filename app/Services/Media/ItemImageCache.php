<?php

namespace App\Services\Media;

use App\Services\Homeside\ListsService;
use App\Services\Homeside\Session;
use Illuminate\Support\Facades\File;

/**
 * Imágenes de ítems de lista.
 *
 * El endpoint que las sirve exige `auth:sanctum`, y `<native:image>` no puede
 * mandar headers. Así que la app las descarga con el token y le pasa al
 * renderer una URL `file://` (el renderer resuelve paths absolutos contra el
 * filesystem del dispositivo).
 *
 * El cache se invalida con `updated_at` del ítem: si cambia, se baja de nuevo y
 * se borra el archivo anterior.
 */
final class ItemImageCache
{
    public function __construct(
        private readonly Session $session = new Session,
        private readonly ?string $directory = null,
    ) {}

    /**
     * Ruta local lista para `<native:image>`, o `null` si no hay imagen legible.
     */
    public function url(string $listId, string $itemId, ?string $householdId = null, ?string $version = null): ?string
    {
        $extension = null;
        $path = $this->find($listId, $itemId, $version);

        if ($path === null) {
            $contents = $this->fetch($listId, $itemId, $householdId, $version);

            if ($contents === null) {
                return null;
            }

            $extension = $this->extensionFor($contents);
            $path = $this->pathFor($listId, $itemId, $version, $extension);

            $this->ensureDirectory();
            $this->prune($listId, $itemId, $path);

            if (File::put($path, $contents) === false) {
                return null;
            }
        }

        return 'file://'.$path;
    }

    public function forget(string $listId, string $itemId): void
    {
        foreach (File::glob($this->directory().'/'.$this->prefix($listId, $itemId).'*') ?: [] as $file) {
            File::delete($file);
        }
    }

    private function fetch(string $listId, string $itemId, ?string $householdId, ?string $version): ?string
    {
        try {
            $client = $this->session->client();
            $path = (new ListsService($this->session))
                ->itemImagePath($listId, $itemId, $householdId);

            return $client->download($path);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Busca la variante de *esta* versión. Antes devolvía el primer archivo que
     * coincidía con el prefijo, así que una foto nueva seguía mostrando la
     * vieja hasta borrar el cache a mano.
     */
    private function find(string $listId, string $itemId, ?string $version): ?string
    {
        $base = $this->basePath($listId, $itemId, $version);

        foreach (File::glob($base.'.*') ?: [] as $file) {
            return $file;
        }

        return null;
    }

    private function basePath(string $listId, string $itemId, ?string $version): string
    {
        $suffix = $version !== null ? '-'.substr(sha1($version), 0, 8) : '';

        return $this->directory().'/'.$this->prefix($listId, $itemId).$suffix;
    }

    private function pathFor(string $listId, string $itemId, ?string $version, string $extension): string
    {
        return $this->basePath($listId, $itemId, $version).'.'.$extension;
    }

    private function prefix(string $listId, string $itemId): string
    {
        return preg_replace('/[^A-Za-z0-9-]/', '', $listId).'-'.preg_replace('/[^A-Za-z0-9-]/', '', $itemId);
    }

    /** @param  list<string>  $keep */
    private function prune(string $listId, string $itemId, string $keep): void
    {
        foreach (File::glob($this->directory().'/'.$this->prefix($listId, $itemId).'*') ?: [] as $file) {
            if ($file !== $keep) {
                File::delete($file);
            }
        }
    }

    private function ensureDirectory(): void
    {
        if (! File::isDirectory($this->directory())) {
            File::makeDirectory($this->directory(), 0775, true);
        }
    }

    private function directory(): string
    {
        return $this->directory ?? storage_path('app/item-images');
    }

    /**
     * El renderer nativo decide por el contenido; la extensión es para el cache.
     */
    private function extensionFor(string $contents): string
    {
        return match (true) {
            str_starts_with($contents, "\xFF\xD8") => 'jpg',
            str_starts_with($contents, "\x89PNG") => 'png',
            str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WEBP' => 'webp',
            str_starts_with($contents, 'GIF8') => 'gif',
            default => 'img',
        };
    }
}
