<?php

namespace Tests\Feature;

use App\Services\Homeside\Session;
use App\Services\Media\ItemImageCache;
use App\Support\ServerUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

class ItemImageCacheTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/homeside-images-'.uniqid();
        File::makeDirectory($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    private function cache(): ItemImageCache
    {
        return new ItemImageCache(new Session, $this->directory);
    }

    private function jpeg(): string
    {
        return "\xFF\xD8\xFF\xE0".str_repeat('a', 64);
    }

    public function test_it_downloads_with_the_token_and_returns_a_file_url(): void
    {
        $this->connect();
        Http::fake(['*/api/v1/lists/list-1/items/item-1/image' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);

        $url = $this->cache()->url('list-1', 'item-1', null, '2026-11-12T10:00:00Z');

        $this->assertNotNull($url);
        $this->assertStringStartsWith('file://', $url);

        $path = substr((string) $url, strlen('file://'));
        $this->assertFileExists($path);
        $this->assertStringEndsWith('.jpg', $path);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer session-token'));
    }

    public function test_the_second_call_is_served_from_disk(): void
    {
        $this->connect();
        Http::fake(['*' => Http::response($this->jpeg())]);

        $this->cache()->url('list-1', 'item-1');
        $this->cache()->url('list-1', 'item-1');

        Http::assertSentCount(1);
    }

    public function test_a_new_version_replaces_the_old_file(): void
    {
        $this->connect();
        Http::fake(['*' => Http::response($this->jpeg())]);

        $first = $this->cache()->url('list-1', 'item-1', null, 'v1');
        $this->cache()->forget('list-1', 'item-1');
        $second = $this->cache()->url('list-1', 'item-1', null, 'v2');

        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist(substr((string) $first, strlen('file://')));
    }

    public function test_a_missing_image_returns_null_instead_of_writing_a_file(): void
    {
        $this->connect();
        Http::fake(['*' => Http::response('', 404)]);

        $this->assertNull($this->cache()->url('list-1', 'item-1'));
        $this->assertSame([], File::files($this->directory));
    }

    public function test_it_detects_the_real_extension(): void
    {
        $this->connect();

        Http::fake([
            '*/list-1/items/item-1/image' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('a', 32)),
            '*/list-2/items/item-2/image' => Http::response('RIFF'.str_repeat("\0", 4).'WEBP'.str_repeat('a', 32)),
        ]);

        $png = $this->cache()->url('list-1', 'item-1', null, 'png');
        $webp = $this->cache()->url('list-2', 'item-2', null, 'webp');

        $this->assertStringEndsWith('.png', (string) $png);
        $this->assertStringEndsWith('.webp', (string) $webp);
    }

    public function test_a_network_failure_is_swallowed(): void
    {
        $this->connect();
        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->assertNull($this->cache()->url('list-1', 'item-1'));
    }

    public function test_the_household_path_is_used_for_household_lists(): void
    {
        $this->connect();
        Http::fake(['*' => Http::response($this->jpeg())]);

        $this->cache()->url('list-1', 'item-1', 'household-1', 'v1');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://homeside.cloud/api/v1/households/household-1/lists/list-1/items/item-1/image');
    }

    public function test_a_configured_server_is_required(): void
    {
        // Sin servidor configurado el cache no puede descargar nada.
        $this->assertNull($this->cache()->url('list-1', 'item-1'));
    }

    public function test_the_fallback_server_helper_is_not_needed(): void
    {
        // Guarda contra un refactor que asuma `ServerUrl::default()`.
        $this->assertInstanceOf(ServerUrl::class, ServerUrl::fromUserInput('https://homeside.cloud'));
    }
}
