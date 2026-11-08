<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AuthService;
use App\Services\Homeside\Session;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Login en dos pasos, igual que el backend: credenciales → código OTP.
 *
 * El `temp_token` vive sólo en memoria del componente (5 minutos de vida); el
 * token de sesión lo persiste AuthService cifrado en el SQLite del dispositivo.
 */
final class Login extends NativeComponent
{
    public const string STEP_CREDENTIALS = 'credentials';

    public const string STEP_OTP = 'otp';

    public string $email = '';

    public string $password = '';

    public string $code = '';

    public string $step = self::STEP_CREDENTIALS;

    public bool $busy = false;

    public ?string $error = null;

    public ?string $notice = null;

    public int $resendIn = 0;

    private ?string $tempToken = null;

    public function mount(): void
    {
        $user = (new Session)->user();

        $this->email = is_array($user) && is_string($user['email'] ?? null)
            ? $user['email']
            : '';
    }

    public function navTitle(): string
    {
        return 'Entrar';
    }

    public function submitCredentials(?string $submittedPassword = null): void
    {
        if ($this->busy) {
            return;
        }

        if (is_string($submittedPassword) && $submittedPassword !== '') {
            $this->password = $submittedPassword;
        }

        $this->error = null;
        $this->notice = null;

        if (trim($this->email) === '' || $this->password === '') {
            $this->error = 'Completá tu email y tu contraseña.';

            return;
        }

        $this->busy = true;

        try {
            $result = (new AuthService)->login($this->email, $this->password);
        } catch (HomesideException $exception) {
            $this->error = $this->messageFor($exception);
            $this->busy = false;

            return;
        }

        $this->tempToken = $result['temp_token'];
        $this->password = '';
        $this->code = '';
        $this->step = self::STEP_OTP;
        $this->notice = 'Te mandamos el código a tu app de autenticación.';
        $this->busy = false;
    }

    public function submitOtp(?string $submittedCode = null): void
    {
        if ($this->busy || $this->tempToken === null) {
            return;
        }

        if (is_string($submittedCode) && $submittedCode !== '') {
            $this->code = $submittedCode;
        }

        $code = preg_replace('/\s+/', '', $this->code) ?? '';

        $this->error = null;
        $this->notice = null;

        if (strlen($code) < 6) {
            $this->error = 'El código tiene 6 dígitos.';

            return;
        }

        $this->busy = true;

        try {
            (new AuthService)->loginWithOtp($this->tempToken, $code);
        } catch (HomesideException $exception) {
            $this->error = $this->messageFor($exception);
            $this->busy = false;

            if ($exception->requiresLogin()) {
                $this->backToCredentials();
            }

            return;
        }

        $this->busy = false;
        $this->code = '';
        $this->tempToken = null;

        $this->navigate('/lists');
    }

    public function resend(): void
    {
        if ($this->busy || $this->tempToken === null || $this->resendIn > 0) {
            return;
        }

        $this->error = null;

        try {
            $this->tempToken = (new AuthService)->resendOtp($this->tempToken);
        } catch (HomesideException $exception) {
            $this->error = $this->messageFor($exception);

            return;
        }

        $this->resendIn = 30;
        $this->notice = 'Código reenviado. Tenés 5 minutos para usarlo.';
    }

    public function backToCredentials(): void
    {
        $this->step = self::STEP_CREDENTIALS;
        $this->tempToken = null;
        $this->code = '';
        $this->notice = null;
    }

    /**
     * Cambiar de servidor: invalida la sesión y vuelve a la pantalla de conexión.
     */
    public function changeServer(): void
    {
        $this->navigate('/server-setup');
    }

    private function messageFor(HomesideException $exception): string
    {
        return match ($exception->reason) {
            HomesideException::FORBIDDEN => $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'Tu cuenta no puede entrar todavía.',
            HomesideException::RATE_LIMITED => $exception->retryAfter !== null
                ? sprintf('Demasiados intentos. Esperá %d segundos.', $exception->retryAfter)
                : 'Demasiados intentos. Esperá un momento.',
            default => $exception->getMessage(),
        };
    }

    public function render(): View
    {
        return view('native.login');
    }
}
