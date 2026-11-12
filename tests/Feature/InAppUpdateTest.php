<?php

namespace Tests\Feature;

use App\NativeComponents\Profile;
use Homeside\ApkUpdater\ApkUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * Flujo de actualización con el plugin nativo instalado.
 *
 * El paquete `homeside/apk-updater` es opcional: mientras no esté en
 * `composer.json`, estos tests se saltean y el resto de la suite sigue
 * cubriendo el fallback al navegador (ver `ProfileScreenTest`).
 */
class InAppUpdateTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private const string SHA = '905615432b8d565a004a1e4f1e6b39723cab9c1f2cb95e22553f91a61bb95851';

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ApkUpdater::class)) {
            $this->markTestSkipped('homeside/apk-updater is not installed.');
        }
    }

    private function fakeManifest(): void
    {
        Http::fake([
            '*/releases/*/latest.json' => Http::response([
                'channel' => 'dev',
                'version' => '0.1.50-dev',
                'version_code' => 50,
                'min_version_code' => 0,
                'apk_url' => 'https://example.test/homeside-dev.apk',
                'sha256' => self::SHA,
                'notes' => 'Listas compartidas',
            ]),
        ]);
    }

    public function test_the_update_is_downloaded_and_installed_without_leaving_the_app(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        $bridge = Native::fakeBridge()
            ->withApkUpdaterDownloaded(id: '7')
            ->withApkUpdaterStatus(status: 'successful', progress: 100, id: '7');

        $bridge->respondTo('ApkUpdater.Install', ['started' => 'true']);

        Native::test(Profile::class, platform: 'android')
            ->call('downloadUpdate')
            ->assertSet('downloadId', '7')
            ->assertSet('downloadStatus', 'running')
            ->assertApkUpdaterDownloaded('https://example.test/homeside-dev.apk')
            ->call('pollDownload')
            ->assertSet('downloadStatus', 'successful')
            ->assertSet('downloadProgress', 100)
            ->assertApkUpdaterInstalled('7');
    }

    public function test_the_published_checksum_is_sent_to_the_installer(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        Native::fakeBridge()->withApkUpdaterDownloaded(id: '7');

        Native::test(Profile::class, platform: 'android')
            ->call('downloadUpdate');

        $call = Native::fakeBridge()->callsTo('ApkUpdater.Download')[0] ?? null;

        $this->assertNotNull($call);
        $this->assertSame(self::SHA, $call['params']['sha256'] ?? null);
    }

    public function test_progress_is_refreshed_while_downloading(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        Native::fakeBridge()
            ->withApkUpdaterDownloaded(id: '7')
            ->withApkUpdaterStatus(status: 'running', progress: 42, id: '7');

        Native::test(Profile::class, platform: 'android')
            ->call('downloadUpdate')
            ->call('pollDownload')
            ->assertSet('downloadStatus', 'running')
            ->assertSet('downloadProgress', 42);
    }

    public function test_progress_is_shown_once_while_downloading(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        Native::fakeBridge()
            ->withApkUpdaterDownloaded(id: '7')
            ->withApkUpdaterStatus(status: 'running', progress: 42, id: '7');

        // La fila con el spinner muestra el progreso; el mensaje queda vacío
        // para no pintar un segundo contador debajo del botón.
        Native::test(Profile::class, platform: 'android')
            ->call('downloadUpdate')
            ->call('pollDownload')
            ->assertSet('downloadProgress', 42)
            ->assertSet('downloadMessage', null);
    }

    public function test_without_the_install_permission_it_opens_the_settings(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        Native::fakeBridge()
            ->withApkUpdaterDownloaded(id: '7')
            ->withApkUpdaterStatus(status: 'successful', progress: 100, id: '7')
            ->withApkUpdaterInstallError();

        Native::test(Profile::class, platform: 'android')
            ->call('downloadUpdate')
            ->call('pollDownload')
            ->assertApkUpdaterSettingsOpened()
            ->assertSee('instalar apps desconocidas');
    }

    public function test_a_failed_download_is_reported(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        Native::fakeBridge()->withApkUpdaterDownloadError(message: 'Sin conexión.');

        Native::test(Profile::class, platform: 'android')
            ->call('downloadUpdate')
            ->assertSet('downloadStatus', 'failed')
            ->assertSee('Sin conexión.');
    }

    public function test_on_another_platform_the_browser_is_used(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        $bridge = Native::fakeBridge();

        Native::test(Profile::class, platform: 'ios')
            ->call('downloadUpdate');

        $bridge->assertNotCalled('ApkUpdater.Download');
        $bridge->assertCalled('Browser.Open');
    }
}
