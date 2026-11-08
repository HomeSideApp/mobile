<?php

namespace Tests\Feature;

use App\NativeComponents\ServerSetup;
use App\Services\Settings\SettingsRepository;
use App\Support\ServerUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

class ServerSetupTest extends TestCase
{
    use RefreshDatabase;

    private function healthOk(): void
    {
        Http::fake([
            '*/api/v1/health' => Http::response([
                'status' => 'ok',
                'app' => 'HomeSide',
                'environment' => 'production',
            ]),
        ]);
    }

    public function test_it_prefills_the_configured_default_server(): void
    {
        config()->set('homeside.default_server', 'https://homeside.cloud');

        Native::test(ServerSetup::class)
            ->assertSet('server', 'https://homeside.cloud')
            ->assertSee('HomeSide')
            ->assertSee('Probar conexión');
    }

    public function test_it_prefills_a_previously_saved_server(): void
    {
        (new SettingsRepository)->setServer(
            ServerUrl::fromUserInput('https://casa.example.com'),
        );

        Native::test(ServerSetup::class)
            ->assertSet('server', 'https://casa.example.com')
            ->assertSet('alreadyConfigured', true);
    }

    public function test_a_healthy_server_is_saved_and_the_app_moves_on(): void
    {
        $this->healthOk();

        Native::test(ServerSetup::class)
            ->set('server', 'https://casa.example.com')
            ->call('checkConnection')
            ->assertSet('status', 'ok')
            ->assertSet('serverName', 'HomeSide')
            ->assertNavigatedTo('/lists');

        $this->assertSame('https://casa.example.com', (new SettingsRepository)->server()?->baseUrl);
    }

    public function test_a_server_error_is_reported_and_nothing_is_saved(): void
    {
        Http::fake(['*/api/v1/health' => Http::response('', 500)]);

        Native::test(ServerSetup::class)
            ->set('server', 'https://casa.example.com')
            ->call('checkConnection')
            ->assertSet('status', 'error');

        $this->assertNull((new SettingsRepository)->server());
    }

    public function test_an_unreachable_server_reports_a_network_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        Native::test(ServerSetup::class)
            ->set('server', 'https://casa.example.com')
            ->call('checkConnection')
            ->assertSet('status', 'error');
    }

    public function test_an_insecure_public_url_is_rejected_without_a_request(): void
    {
        Http::fake();

        Native::test(ServerSetup::class)
            ->set('server', 'http://homeside.cloud')
            ->call('checkConnection')
            ->assertSet('status', 'error');

        Http::assertNothingSent();
    }

    public function test_a_non_homeside_response_is_rejected(): void
    {
        Http::fake(['*/api/v1/health' => Http::response(['hello' => 'world'])]);

        Native::test(ServerSetup::class)
            ->set('server', 'https://casa.example.com')
            ->call('checkConnection')
            ->assertSet('status', 'error');

        $this->assertNull((new SettingsRepository)->server());
    }
}
