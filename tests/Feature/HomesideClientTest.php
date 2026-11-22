<?php

namespace Tests\Feature;

use App\Exceptions\HomesideException;
use App\Services\Homeside\HomesideClient;
use App\Support\ServerUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomesideClientTest extends TestCase
{
    private function client(?string $token = null): HomesideClient
    {
        return HomesideClient::for(ServerUrl::fromUserInput('https://homeside.cloud'), $token);
    }

    public function test_health_hits_the_versioned_endpoint_without_a_token(): void
    {
        Http::fake([
            'homeside.cloud/api/v1/health' => Http::response([
                'status' => 'ok',
                'app' => 'HomeSide',
                'environment' => 'production',
            ]),
        ]);

        $health = $this->client()->health();

        $this->assertSame('ok', $health['status']);
        $this->assertSame('HomeSide', $health['app']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://homeside.cloud/api/v1/health'
                && $request->method() === 'GET'
                && ! $request->hasHeader('Authorization');
        });
    }

    public function test_authenticated_calls_send_the_bearer_token_and_locale(): void
    {
        Http::fake([
            'homeside.cloud/api/v1/me' => Http::response(['data' => ['id' => 'abc']]),
        ]);

        $this->client('secret-token')->get('/me');

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Bearer secret-token')
                && $request->hasHeader('Accept-Language', (string) config('homeside.locale'));
        });
    }

    public function test_writes_carry_an_idempotency_key(): void
    {
        Http::fake([
            'homeside.cloud/api/v1/lists' => Http::response(['data' => ['id' => 'l1']], 201),
        ]);

        $this->client('secret-token')->post('/lists', ['name' => 'Compra']);

        Http::assertSent(function (Request $request): bool {
            $key = $request->header('Idempotency-Key');

            return count($key) === 1 && strlen((string) $key[0]) === 36;
        });
    }

    public function test_every_mutating_verb_carries_an_idempotency_key(): void
    {
        // El backend empezó a exigirla el 18/11 en TODAS las rutas de escritura
        // del alcance offline (`b14ed6e`). PUT y DELETE no la mandaban, así que
        // tildar un ítem, renombrar o borrar una lista respondían 422 con
        // `idempotency_key_required` — y la pantalla lo mostraba como "no pasa
        // nada".
        Http::fake([
            'homeside.cloud/api/v1/*' => Http::response(['data' => []], 200),
        ]);

        $client = $this->client('secret-token');

        $client->put('/lists/l1', ['name' => 'Otra']);
        $client->patch('/lists/l1', ['name' => 'Otra']);
        $client->delete('/lists/l1');
        $client->uploadFile('/lists/l1/items/i1', 'image_url', 'bytes', 'foto.jpg');

        $verbs = [];

        foreach (Http::recorded() as [$request]) {
            $verbs[$request->method()] = $request->header('Idempotency-Key');
        }

        $this->assertSame(['PUT', 'PATCH', 'DELETE', 'POST'], array_keys($verbs));

        foreach ($verbs as $method => $key) {
            $this->assertIsArray($key, "{$method} sin Idempotency-Key.");
            $this->assertSame(36, strlen((string) ($key[0] ?? '')), "{$method} con una clave mal formada.");
        }
    }

    public function test_a_network_failure_becomes_a_retryable_network_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        try {
            $this->client()->health();
            $this->fail('Expected a HomesideException.');
        } catch (HomesideException $exception) {
            $this->assertSame(HomesideException::NETWORK, $exception->reason);
            $this->assertTrue($exception->isRetryable());
            $this->assertStringContainsString('tardó demasiado', $exception->getMessage());
        }
    }

    public function test_a_401_requires_login_again(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        try {
            $this->client('expired')->get('/me');
            $this->fail('Expected a HomesideException.');
        } catch (HomesideException $exception) {
            $this->assertSame(HomesideException::UNAUTHORIZED, $exception->reason);
            $this->assertTrue($exception->requiresLogin());
        }
    }

    public function test_a_403_keeps_the_server_code(): void
    {
        Http::fake(['*' => Http::response(['code' => 'link_required', 'message' => 'Vinculá Google.'], 403)]);

        try {
            $this->client()->post('/auth/google', ['id_token' => 'x']);
            $this->fail('Expected a HomesideException.');
        } catch (HomesideException $exception) {
            $this->assertSame(HomesideException::FORBIDDEN, $exception->reason);
            $this->assertSame('link_required', $exception->serverCode);
        }
    }

    public function test_validation_errors_are_exposed_field_by_field(): void
    {
        Http::fake(['*' => Http::response([
            'message' => 'The given data was invalid.',
            'code' => 'validation_failed',
            'errors' => ['name' => ['El nombre es obligatorio.']],
        ], 422)]);

        try {
            $this->client()->post('/lists', []);
            $this->fail('Expected a HomesideException.');
        } catch (HomesideException $exception) {
            $this->assertSame(HomesideException::VALIDATION, $exception->reason);
            $this->assertSame('El nombre es obligatorio.', $exception->firstError());
        }
    }

    public function test_rate_limiting_reports_the_retry_after_seconds(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '42'])]);

        try {
            $this->client()->post('/auth/login', ['email' => 'a@b.c', 'password' => 'x']);
            $this->fail('Expected a HomesideException.');
        } catch (HomesideException $exception) {
            $this->assertSame(HomesideException::RATE_LIMITED, $exception->reason);
            $this->assertSame(42, $exception->retryAfter);
        }
    }

    public function test_server_errors_are_retryable(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        try {
            $this->client()->health();
            $this->fail('Expected a HomesideException.');
        } catch (HomesideException $exception) {
            $this->assertSame(HomesideException::SERVER, $exception->reason);
            $this->assertTrue($exception->isRetryable());
        }
    }

    public function test_a_204_returns_an_empty_payload(): void
    {
        Http::fake(['*' => Http::response('', 204)]);

        $this->assertSame([], $this->client()->delete('/lists/l1'));
    }
}
