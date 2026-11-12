<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AuthService;
use App\Services\Homeside\Session;
use App\Services\Settings\LocaleService;
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
        (new LocaleService)->apply();

        $user = (new Session)->user();

        $this->email = is_array($user) && is_string($user['email'] ?? null)
            ? $user['email']
            : '';
    }

    public function navTitle(): string
    {
        return __('app.login.title');
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
            $this->error = __('app.login.missing_credentials');

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
        $this->notice = __('app.login.otp_sent');
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
            $this->error = __('app.login.code_length');

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
        $this->notice = __('app.login.otp_resent');
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
                : __('app.login.cannot_enter'),
            HomesideException::RATE_LIMITED => $exception->retryAfter !== null
                ? __('app.errors.rate_limited_wait', ['seconds' => $exception->retryAfter])
                : __('app.errors.rate_limited'),
            default => $exception->getMessage(),
        };
    }

    public function render(): View
    {
        return view('native.login');
    }
}
