<?php

namespace Tests\Feature;

use App\NativeComponents\Login;
use App\Services\Homeside\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

class LoginScreenTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    public function test_empty_credentials_are_rejected_without_touching_the_network(): void
    {
        Http::fake();

        Native::test(Login::class)
            ->call('submitCredentials')
            ->assertSet('step', Login::STEP_CREDENTIALS)
            ->assertSet('error', 'Completá tu email y tu contraseña.');

        Http::assertNothingSent();
    }

    public function test_a_valid_password_moves_to_the_otp_step(): void
    {
        $this->connect(token: null);

        Http::fake([
            '*/api/v1/auth/login' => Http::response(['temp_token' => 'temp-123']),
        ]);

        Native::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'secreto')
            ->call('submitCredentials')
            ->assertSet('step', Login::STEP_OTP)
            ->assertSee('Escribí el código de 6 dígitos');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://homeside.cloud/api/v1/auth/login'
            && $request['email'] === 'ana@example.com');
    }

    public function test_the_otp_starts_the_session_and_navigates_to_the_lists(): void
    {
        $this->connect(token: null);

        Http::fake([
            '*/api/v1/auth/login' => Http::response(['temp_token' => 'temp-123']),
            '*/api/v1/auth/login/otp' => Http::response([
                'token' => 'session-token',
                'user' => ['id' => 'user-1', 'name' => 'Ana', 'email' => 'ana@example.com'],
            ]),
        ]);

        Native::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'secreto')
            ->call('submitCredentials')
            ->set('code', '123456')
            ->call('submitOtp')
            ->assertNavigatedTo('/lists');

        $session = new Session;

        $this->assertTrue($session->isAuthenticated());
        $this->assertSame('session-token', $session->token());
        $this->assertSame('Ana', $session->user()['name']);
    }

    public function test_the_otp_is_sent_with_the_temporary_token(): void
    {
        $this->connect(token: null);

        Http::fake([
            '*/api/v1/auth/login' => Http::response(['temp_token' => 'temp-123']),
            '*/api/v1/auth/login/otp' => Http::response([
                'token' => 'session-token',
                'user' => ['id' => 'user-1', 'name' => 'Ana'],
            ]),
        ]);

        Native::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'secreto')
            ->call('submitCredentials')
            ->set('code', '123456')
            ->call('submitOtp');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/auth/login/otp')
            && $request->hasHeader('Authorization', 'Bearer temp-123')
            && $request['code'] === '123456');
    }

    public function test_a_wrong_password_shows_the_server_message(): void
    {
        $this->connect(token: null);

        Http::fake([
            '*/api/v1/auth/login' => Http::response(['message' => 'Credenciales incorrectas.'], 401),
        ]);

        Native::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'mal')
            ->call('submitCredentials')
            ->assertSet('step', Login::STEP_CREDENTIALS)
            ->assertSet('error', 'Credenciales incorrectas.');
    }

    public function test_an_account_without_two_factor_is_told_what_to_do(): void
    {
        $this->connect(token: null);

        Http::fake([
            '*/api/v1/auth/login' => Http::response([
                'message' => 'Tu cuenta no tiene autenticación de dos factores configurada. Contacta con un administrador.',
            ], 403),
        ]);

        Native::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'secreto')
            ->call('submitCredentials')
            ->assertSee('autenticación de dos factores');
    }

    public function test_rate_limiting_reports_the_wait(): void
    {
        $this->connect(token: null);

        Http::fake([
            '*/api/v1/auth/login' => Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '42']),
        ]);

        Native::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'secreto')
            ->call('submitCredentials')
            ->assertSet('error', 'Demasiados intentos. Esperá 42 segundos.');
    }

    public function test_a_short_code_is_rejected_locally(): void
    {
        $this->connect(token: null);

        Http::fake([
            '*/api/v1/auth/login' => Http::response(['temp_token' => 'temp-123']),
        ]);

        Native::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'secreto')
            ->call('submitCredentials')
            ->set('code', '12')
            ->call('submitOtp')
            ->assertSet('error', 'El código tiene 6 dígitos.')
            ->assertSet('step', Login::STEP_OTP);
    }

    public function test_back_to_credentials_clears_the_temporary_token(): void
    {
        $this->connect(token: null);

        Http::fake([
            '*/api/v1/auth/login' => Http::response(['temp_token' => 'temp-123']),
        ]);

        Native::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'secreto')
            ->call('submitCredentials')
            ->call('backToCredentials')
            ->assertSet('step', Login::STEP_CREDENTIALS)
            ->set('code', '123456')
            ->call('submitOtp')
            ->assertSet('step', Login::STEP_CREDENTIALS);
    }
}
