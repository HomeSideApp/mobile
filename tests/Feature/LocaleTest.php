<?php

namespace Tests\Feature;

use App\NativeComponents\Lists;
use App\NativeComponents\Profile;
use App\Services\Settings\LocaleService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    public function test_the_default_locale_comes_from_config(): void
    {
        config(['app.locale' => 'es-ES']);

        $this->assertSame('es-ES', (new LocaleService)->current());
    }

    public function test_choosing_a_locale_persists_and_applies_it(): void
    {
        $service = new LocaleService;

        $this->assertTrue($service->set('en-US'));

        $this->assertSame('en-US', $service->current());
        $this->assertSame('en-US', App::getLocale());
        // Otro proceso lo lee del SQLite del dispositivo.
        $this->assertSame('en-US', (new LocaleService)->current());
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        $service = new LocaleService;

        $this->assertFalse($service->set('fr-FR'));
        $this->assertSame('es-ES', $service->current());
    }

    public function test_the_account_locale_wins_when_there_is_a_session(): void
    {
        $service = new LocaleService;
        $service->set('es-ES');

        $this->assertTrue($service->syncFromUser(['locale' => 'en-US']));
        $this->assertSame('en-US', $service->current());
        $this->assertSame('en-US', App::getLocale());

        // Sin cambios, no vuelve a tocar nada.
        $this->assertFalse($service->syncFromUser(['locale' => 'en-US']));
    }

    public function test_a_user_without_a_locale_does_not_change_anything(): void
    {
        $service = new LocaleService;

        $this->assertFalse($service->syncFromUser([]));
        $this->assertFalse($service->syncFromUser(['locale' => 'fr-FR']));
        $this->assertSame('es-ES', $service->current());
    }

    public function test_the_profile_switches_the_language_and_saves_it_to_the_account(): void
    {
        $this->connect();
        config(['nativephp.version_code' => 50, 'nativephp.version' => '0.1.50-dev']);

        Http::fake([
            '*/releases/*/latest.json' => Http::response([
                'channel' => 'dev', 'version' => '0.1.50-dev', 'version_code' => 50,
            ]),
            '*/api/v1/me' => Http::response(['data' => $this->me(['locale' => 'es-ES'])]),
            '*/api/v1/me/profile' => Http::response(['data' => $this->me(['locale' => 'en-US'])]),
        ]);

        Native::test(Profile::class, platform: 'android')
            ->assertSee('Estás al día.')
            ->call('setLocale', 'en-US')
            ->assertSet('appLocale', 'en-US')
            ->assertSee('You are up to date.')
            ->assertSee('Language');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/me/profile')
            && $request->method() === 'PATCH'
            && $request['locale'] === 'en-US'
            && $request['email'] === 'ana@example.com');
    }

    public function test_the_profile_renders_both_language_options(): void
    {
        config(['nativephp.version_code' => 50]);
        Http::fake(['*/releases/*/latest.json' => Http::response('', 404)]);

        Native::test(Profile::class, platform: 'android')
            ->assertSet('appLocale', 'es-ES')
            ->assertSee('Español')
            ->assertSee('English');
    }

    public function test_without_a_session_the_language_changes_only_on_the_device(): void
    {
        config(['nativephp.version_code' => 50]);
        Http::fake(['*/releases/*/latest.json' => Http::response('', 404)]);

        Native::test(Profile::class, platform: 'android')
            ->call('setLocale', 'en-US')
            ->assertSet('appLocale', 'en-US')
            ->assertSet('languageNotice', null);

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/me/profile'));
        $this->assertSame('en-US', (new LocaleService)->current());
    }

    public function test_the_account_locale_applies_after_reading_the_profile(): void
    {
        $this->connect();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me(['locale' => 'en-US', 'active_household_id' => null])]),
            '*/api/v1/households' => Http::response(['data' => []]),
            '*/api/v1/lists*' => Http::response(['data' => []]),
        ]);

        Native::test(Lists::class, platform: 'android')
            ->assertSee('No lists yet');

        $this->assertSame('en-US', (new LocaleService)->current());
    }

    public function test_the_login_session_keeps_the_locale_of_the_device(): void
    {
        // Antes de leer el perfil no hay locale del servidor: manda el local.
        $service = new LocaleService;
        $service->set('en-US');

        $this->assertSame('en-US', $service->current());
        $this->assertSame('en-US', (new SettingsRepository)->get(LocaleService::SETTING_KEY));
    }
}
