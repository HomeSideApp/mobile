<?php

namespace Tests\Feature;

use App\Exceptions\HomesideException;
use App\Models\Setting;
use App\Services\Settings\SettingsRepository;
use App\Support\ServerUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_has_no_server_and_no_session(): void
    {
        $settings = new SettingsRepository;

        $this->assertNull($settings->server());
        $this->assertFalse($settings->isConfigured());
        $this->assertFalse($settings->isAuthenticated());
    }

    public function test_the_server_round_trips(): void
    {
        $settings = new SettingsRepository;
        $settings->setServer(ServerUrl::fromUserInput('https://casa.example.com'));

        $this->assertSame('https://casa.example.com', $settings->server()?->baseUrl);
        $this->assertTrue($settings->isConfigured());
    }

    public function test_the_token_is_encrypted_at_rest(): void
    {
        $settings = new SettingsRepository;
        $settings->setToken('1|plain-text-token');

        $raw = (string) DB::table('settings')->where('key', 'auth.token')->value('value');

        $this->assertNotSame('1|plain-text-token', $raw);
        $this->assertSame('1|plain-text-token', $settings->token());
        $this->assertSame('1|plain-text-token', Crypt::decryptString($raw));
    }

    public function test_changing_the_server_forgets_the_previous_session(): void
    {
        $settings = new SettingsRepository;
        $settings->setServer(ServerUrl::fromUserInput('https://a.example.com'));
        $settings->setToken('token-a');

        $settings->setServer(ServerUrl::fromUserInput('https://b.example.com'));

        $this->assertSame('https://b.example.com', $settings->server()?->baseUrl);
        $this->assertNull($settings->token());
    }

    public function test_forgetting_the_token_only_clears_the_session(): void
    {
        $settings = new SettingsRepository;
        $settings->setServer(ServerUrl::fromUserInput('https://casa.example.com'));
        $settings->setToken('token');

        $settings->forgetToken();

        $this->assertNull($settings->token());
        $this->assertNotNull($settings->server());
    }

    public function test_a_token_that_cannot_be_decrypted_is_discarded(): void
    {
        Setting::query()->create(['key' => 'auth.token', 'value' => 'not-a-valid-payload']);

        $settings = new SettingsRepository;

        $this->assertNull($settings->token());
        $this->assertSame(0, Setting::query()->where('key', 'auth.token')->count());
    }

    public function test_a_corrupt_server_value_is_discarded(): void
    {
        Setting::query()->create(['key' => 'server.url', 'value' => 'ftp://nope']);

        $settings = new SettingsRepository;

        $this->assertNull($settings->server());
        $this->assertFalse($settings->isConfigured());
    }

    public function test_the_client_requires_a_server(): void
    {
        $this->expectException(HomesideException::class);

        (new SettingsRepository)->client();
    }

    public function test_the_client_carries_the_stored_token(): void
    {
        $settings = new SettingsRepository;
        $settings->setServer(ServerUrl::fromUserInput('https://casa.example.com'));
        $settings->setToken('token');

        $client = $settings->client();

        $this->assertSame('https://casa.example.com/api/v1', $client->server()->apiBase);
    }

    public function test_reset_clears_everything(): void
    {
        $settings = new SettingsRepository;
        $settings->setServer(ServerUrl::fromUserInput('https://casa.example.com'));
        $settings->setToken('token');

        $settings->reset();

        $this->assertNull($settings->server());
        $this->assertNull($settings->token());
        $this->assertSame(0, Setting::query()->count());
    }
}
