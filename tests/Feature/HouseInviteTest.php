<?php

namespace Tests\Feature;

use App\NativeComponents\House;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * Invitar desde la app: por email o compartiendo un link del servidor.
 */
class HouseInviteTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function fakeHouse(array $overrides = []): void
    {
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
            '*/api/v1/households/invitations' => Http::response(['data' => []]),
            '*/api/v1/households/configuration' => Http::response(['data' => ['modules' => []]]),
            '*/api/v1/households/household-1/dashboard' => Http::response(['data' => ['enabled_modules' => []]]),
            '*/api/v1/households/household-1/invite' => Http::response(['data' => ['id' => 'invitation-9']], 201),
            '*/api/v1/households/household-1/invite-links' => Http::response(['data' => [
                'id' => 'link-1',
                'url' => 'https://homeside.cloud/invite/abc123',
            ]], 201),
            '*/api/v1/households/household-1' => Http::response(['data' => [
                'id' => 'household-1',
                'name' => 'Casa de Ana',
                'members' => [],
                ...$overrides,
            ]]),
        ]);
    }

    public function test_it_invites_by_email(): void
    {
        $this->connect();
        $this->fakeHouse();

        $screen = Native::test(House::class)
            ->call('openInvite')
            ->set('inviteEmail', 'nuevo@example.com')
            ->call('sendInvite');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v1/households/household-1/invite')
            && $request['email'] === 'nuevo@example.com');

        // La hoja se cierra y queda la confirmación: sin eso el usuario no sabe
        // si la invitación salió.
        $this->assertFalse($screen->get('inviteOpen'));
        $this->assertStringContainsString('nuevo@example.com', (string) $screen->get('inviteNotice'));
    }

    public function test_an_invalid_email_never_reaches_the_server(): void
    {
        $this->connect();
        $this->fakeHouse();

        $screen = Native::test(House::class)
            ->call('openInvite')
            ->set('inviteEmail', 'no-es-un-email')
            ->call('sendInvite');

        $this->assertNotNull($screen->get('inviteError'));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/invite'));
    }

    public function test_the_link_is_shared_with_the_system_sheet(): void
    {
        $this->connect();
        $this->fakeHouse();

        $bridge = Native::fakeBridge();

        $screen = Native::test(House::class)->call('shareInviteLink');

        // La app no compone URLs de invitación: comparte la que devuelve el
        // servidor, a través del handler que aporta `nativephp/mobile-share`.
        $call = $bridge->callsTo('Share.Url')[0] ?? null;
        $this->assertNotNull($call, 'Se esperaba la hoja de compartir');

        $params = is_array($call['params'] ?? null)
            ? $call['params']
            : (json_decode((string) ($call['params'] ?? '{}'), true) ?: []);

        $this->assertSame('https://homeside.cloud/invite/abc123', $params['url'] ?? null);
        $this->assertNotNull($screen->get('inviteNotice'));
    }
}
