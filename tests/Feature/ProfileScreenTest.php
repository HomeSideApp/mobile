<?php

namespace Tests\Feature;

use App\NativeComponents\Profile;
use App\Services\Homeside\AppUpdateChecker;
use App\Services\Settings\AppearanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

class ProfileScreenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeManifest(array $overrides = []): void
    {
        Http::fake([
            '*/releases/*/latest.json' => Http::response([
                'channel' => 'dev',
                'version' => '0.1.50-dev',
                'version_code' => 50,
                'min_version_code' => 0,
                'apk_url' => 'https://example.test/homeside-dev.apk',
                'sha256' => str_repeat('a', 64),
                'notes' => 'Listas compartidas',
                ...$overrides,
            ]),
        ]);
    }

    public function test_it_shows_the_current_version_and_the_offered_update(): void
    {
        config(['nativephp.version_code' => 10, 'nativephp.version' => '0.1.10-dev']);
        $this->fakeManifest();

        Native::test(Profile::class)
            ->assertSee('0.1.10-dev')
            ->assertSee('Hay una versión nueva: 0.1.50-dev')
            ->assertSee('Descargar actualización')
            ->assertSee('Desarrollo');
    }

    public function test_it_says_when_there_is_nothing_to_update(): void
    {
        config(['nativephp.version_code' => 50]);
        $this->fakeManifest();

        Native::test(Profile::class)
            ->assertSee('Estás al día.')
            ->assertSet('updateAvailable', false);
    }

    public function test_the_appearance_is_a_native_select_and_persists(): void
    {
        config(['nativephp.version_code' => 50]);
        Http::fake(['*/releases/*/latest.json' => Http::response('', 404)]);

        $component = Native::test(Profile::class, platform: 'android')
            ->assertSet('appearance', 'system')
            ->assertSet('appearanceOptions', ['Sistema', 'Claro', 'Oscuro'])
            ->assertSet('appearanceValue', 'Sistema')
            ->call('setAppearanceOption', 'Claro')
            ->assertSet('appearance', 'light')
            ->assertSet('appearanceValue', 'Claro');

        $this->assertSame('light', (new AppearanceService)->current());
        $this->assertSame('light', $component->get('appearance'));
    }

    public function test_downloading_opens_the_apk_url_in_the_browser(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        $bridge = Native::fakeBridge();
        Native::test(Profile::class)->call('downloadUpdate');

        $bridge->assertCalled(
            'Browser.Open',
            fn ($params): bool => str_contains(json_encode($params) ?: '', 'homeside-dev.apk'),
        );
    }

    public function test_switching_the_channel_persists_and_rechecks(): void
    {
        config(['nativephp.version_code' => 10]);

        Http::fake([
            '*/download/dev-latest/latest.json' => Http::response([
                'channel' => 'dev', 'version' => '0.1.50-dev', 'version_code' => 50,
                'apk_url' => 'https://example.test/dev.apk',
            ]),
            '*/releases/latest/download/latest.json' => Http::response([
                'channel' => 'stable', 'version' => '0.2.0', 'version_code' => 30,
                'apk_url' => 'https://example.test/stable.apk',
            ]),
        ]);

        $component = Native::test(Profile::class)
            ->assertSet('channel', 'dev')
            ->call('setChannel', 'stable')
            ->assertSet('channel', 'stable')
            ->assertSee('0.2.0');

        $this->assertSame('stable', (new AppUpdateChecker)->channel());
        $this->assertStringContainsString('stable.apk', (string) $component->get('update.apk_url'));
    }

    public function test_the_channel_is_a_native_select_and_changing_it_rechecks(): void
    {
        config(['nativephp.version_code' => 10]);

        Http::fake([
            '*/download/dev-latest/latest.json' => Http::response([
                'channel' => 'dev', 'version' => '0.1.50-dev', 'version_code' => 50,
            ]),
            '*/releases/latest/download/latest.json' => Http::response([
                'channel' => 'stable', 'version' => '0.2.0', 'version_code' => 30,
            ]),
        ]);

        Native::test(Profile::class, platform: 'android')
            ->assertSet('channelOptions', ['Desarrollo', 'Estable'])
            ->assertSet('channelValue', 'Desarrollo')
            ->call('setChannelOption', 'Estable')
            ->assertSet('channel', 'stable')
            ->assertSet('channelValue', 'Estable')
            ->assertSet('channelDescription', 'Solo versiones tageadas.');
    }

    public function test_a_manual_check_reports_when_the_channel_has_no_releases(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);

        Native::test(Profile::class)
            ->call('checkForUpdates')
            ->assertSee('Todavía no hay versiones publicadas en este canal.');
    }
}
