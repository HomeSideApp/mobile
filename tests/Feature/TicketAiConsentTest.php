<?php

namespace Tests\Feature;

use App\NativeComponents\ExpenseTicket;
use App\NativeComponents\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * El permiso de IA para tickets, en la app.
 *
 * El ticket no es un error cuando falta el permiso: es una pregunta. Y la
 * respuesta se guarda en la cuenta, así que se prende desde el ticket y se
 * apaga desde el perfil.
 *
 * Los tres escenarios viven en un solo caso a propósito: `Http::fake()` acumula
 * stubs dentro del archivo y un caso por escenario se pisaba con el anterior.
 */
class TicketAiConsentTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    public function test_the_ticket_asks_once_persists_the_answer_and_the_profile_can_undo_it(): void
    {
        $this->connect();

        $consented = false;

        Http::fake(function ($request) use (&$consented) {
            $url = $request->url();

            if ($request->method() === 'PATCH' && str_contains($url, '/me/profile')) {
                $consented = ($request->data()['allow_cloud_ai'] ?? null) === true;

                return Http::response(['data' => $this->me(['allow_cloud_ai' => $consented])]);
            }

            if (str_contains($url, '/retry')) {
                return Http::response(['data' => ['id' => 'import-1', 'status' => 'queued']]);
            }

            if (str_contains($url, '/economy/imports/') && ! str_contains($url, '/retry')) {
                // El primer import no puede leer el ticket: pide permiso.
                return Http::response(['data' => [
                    'id' => 'import-1',
                    'status' => 'failed',
                    'error' => ['code' => 'ai_consent_required', 'message' => 'provider runs on cloud'],
                ]]);
            }

            return Http::response(['data' => $this->me(['allow_cloud_ai' => $consented])]);
        });

        // 1. El ticket pregunta en vez de mostrar un error técnico.
        $ticket = Native::test(ExpenseTicket::class, platform: 'android');
        $ticket->set('importId', 'import-1');
        $ticket->set('status', 'processing');
        $ticket->call('refreshImport');

        $ticket->assertSet('consentRequired', true)->assertSet('error', null);

        // 2. Permitir guarda el permiso y relanza el análisis.
        $ticket->call('allowCloudAi');

        $this->assertTrue($consented, 'La app tiene que guardar el permiso en la cuenta.');
        $this->assertSame(
            1,
            count(array_filter(
                Http::recorded()->all(),
                fn (array $pair): bool => str_contains($pair[0]->url(), '/retry'),
            )),
            'Y tiene que relanzar el análisis del ticket.',
        );

        $ticket->assertSet('consentRequired', false);

        // 3. Se revoca desde el perfil.
        $profile = Native::test(Profile::class, platform: 'android');

        $profile->assertSet('allowCloudAi', true);
        $profile->call('toggleCloudAi');
        $profile->assertSet('allowCloudAi', false);

        $this->assertFalse($consented, 'Revocar desde el perfil tiene que llegar al servidor.');
    }
}
