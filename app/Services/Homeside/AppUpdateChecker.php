<?php

namespace App\Services\Homeside;

use App\Services\Settings\SettingsRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Búsqueda de actualizaciones del APK.
 *
 * Cada canal (`dev`, `stable`) publica un manifiesto JSON como asset de un
 * release de GitHub; la app compara su `version_code` contra el del manifiesto
 * y decide si hay algo nuevo.
 *
 * Reglas:
 *  - una consulta cada `check_interval_hours` (24 por defecto), salvo que el
 *    usuario pida "buscar ahora";
 *  - nunca se ofrece bajar de versión (un usuario en dev con código 50 no
 *    recibe el stable 40);
 *  - cualquier fallo se reporta como `unavailable`: el chequeo nunca rompe la
 *    pantalla ni bloquea la app.
 */
final class AppUpdateChecker
{
    public const string STATE_UNKNOWN = 'unknown';

    public const string STATE_UP_TO_DATE = 'up_to_date';

    public const string STATE_OPTIONAL = 'optional';

    public const string STATE_REQUIRED = 'required';

    public const string STATE_UNAVAILABLE = 'unavailable';

    private const string KEY_CHANNEL = 'updates.channel';

    private const string KEY_CHECKED_AT = 'updates.checked_at';

    private const string KEY_RESULT = 'updates.result';

    public function __construct(
        private readonly SettingsRepository $settings = new SettingsRepository,
    ) {}

    /**
     * @return array<string, array{manifest: string}>
     */
    public function channels(): array
    {
        /** @var array<string, array{manifest: string}> $channels */
        $channels = config('homeside.updates.channels', []);

        return $channels;
    }

    public function channel(): string
    {
        $stored = $this->settings->get(self::KEY_CHANNEL);
        $channels = $this->channels();

        if (is_string($stored) && array_key_exists($stored, $channels)) {
            return $stored;
        }

        $default = (string) config('homeside.updates.default_channel', 'dev');

        return array_key_exists($default, $channels) ? $default : (string) array_key_first($channels);
    }

    public function channelLabel(): string
    {
        $channel = $this->channel();

        return (string) __('app.updates.channels.'.$channel);
    }

    public function setChannel(string $channel): bool
    {
        if (! array_key_exists($channel, $this->channels())) {
            return false;
        }

        $this->settings->set(self::KEY_CHANNEL, $channel);

        // Un resultado del canal anterior no sirve para el nuevo.
        $this->forgetCache();

        return true;
    }

    public function currentVersion(): string
    {
        return (string) config('nativephp.version', '0.0.0');
    }

    public function currentVersionCode(): int
    {
        return (int) config('nativephp.version_code', 0);
    }

    /**
     * Estado de actualización del canal elegido.
     *
     * @return array{
     *     state: string,
     *     channel: string,
     *     channel_label: string,
     *     current_version: string,
     *     current_version_code: int,
     *     latest_version: ?string,
     *     latest_version_code: ?int,
     *     apk_url: ?string,
     *     sha256: ?string,
     *     notes: ?string,
     *     checked_at: ?string,
     *     message: ?string
     * }
     */
    public function status(bool $force = false): array
    {
        $channel = $this->channel();

        if (! $force) {
            $cached = $this->cachedStatus($channel);

            if ($cached !== null) {
                return $cached;
            }
        }

        $status = $this->fetch($channel);

        $this->remember($status);

        return $status;
    }

    /**
     * Último resultado conocido, sin tocar la red.
     *
     * Lo usa una pantalla que sólo quiere decidir si muestra un aviso: si
     * nunca se chequeó, devuelve `unknown` en vez de bloquear el render.
     *
     * @return array<string, mixed>
     */
    public function lastKnownStatus(): array
    {
        $raw = $this->settings->get(self::KEY_RESULT);

        if ($raw === null || $raw === '') {
            return $this->withCurrentVersion([
                'state' => self::STATE_UNKNOWN,
                'channel' => $this->channel(),
            ]);
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ($decoded['channel'] ?? null) !== $this->channel()) {
            return $this->withCurrentVersion([
                'state' => self::STATE_UNKNOWN,
                'channel' => $this->channel(),
            ]);
        }

        return $this->withCurrentVersion($decoded);
    }

    public function hasUpdate(array $status): bool
    {
        return in_array($status['state'], [self::STATE_OPTIONAL, self::STATE_REQUIRED], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cachedStatus(string $channel): ?array
    {
        $checkedAt = $this->settings->get(self::KEY_CHECKED_AT);
        $raw = $this->settings->get(self::KEY_RESULT);

        if ($checkedAt === null || $raw === null || $raw === '') {
            return null;
        }

        $interval = max(1, (int) config('homeside.updates.check_interval_hours', 24));
        $timestamp = strtotime($checkedAt);

        if ($timestamp === false || $timestamp + ($interval * 3600) < time()) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ($decoded['channel'] ?? null) !== $channel) {
            return null;
        }

        return $this->withCurrentVersion($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $channel): array
    {
        $url = $this->channels()[$channel]['manifest'] ?? null;

        if (! is_string($url) || $url === '') {
            return $this->unavailable($channel, __('app.updates.no_url'));
        }

        try {
            $response = Http::timeout((int) config('homeside.updates.timeout', 8))
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException) {
            return $this->unavailable($channel, __('app.updates.check_failed'));
        }

        if ($response->status() === 404) {
            return $this->unavailable($channel, __('app.updates.none_in_channel'));
        }

        if (! $response->successful()) {
            return $this->unavailable($channel, __('app.updates.server_error'));
        }

        $manifest = $response->json();

        if (! is_array($manifest) || ! is_numeric($manifest['version_code'] ?? null)) {
            return $this->unavailable($channel, __('app.updates.invalid_manifest'));
        }

        $latestCode = (int) $manifest['version_code'];
        $minimumCode = is_numeric($manifest['min_version_code'] ?? null) ? (int) $manifest['min_version_code'] : 0;
        $currentCode = $this->currentVersionCode();

        if ($currentCode < $minimumCode) {
            $state = self::STATE_REQUIRED;
            $message = __('app.updates.required');
        } elseif ($latestCode > $currentCode) {
            $state = self::STATE_OPTIONAL;
            $message = __('app.updates.available');
        } else {
            $state = self::STATE_UP_TO_DATE;
            $message = null;
        }

        return $this->withCurrentVersion([
            'state' => $state,
            'channel' => $channel,
            'channel_label' => $this->channelLabel(),
            'latest_version' => is_string($manifest['version'] ?? null) ? $manifest['version'] : null,
            'latest_version_code' => $latestCode,
            'min_version_code' => $minimumCode,
            'apk_url' => is_string($manifest['apk_url'] ?? null) ? $manifest['apk_url'] : null,
            'sha256' => is_string($manifest['sha256'] ?? null) ? strtolower($manifest['sha256']) : null,
            'notes' => is_string($manifest['notes'] ?? null) && $manifest['notes'] !== '' ? $manifest['notes'] : null,
            'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function withCurrentVersion(array $status): array
    {
        // El `state` guardado describe el momento del chequeo, no el de ahora:
        // después de instalar la actualización el código instalado cambió y el
        // resultado cacheado seguiría ofreciendo la versión que ya está puesta.
        $state = $this->resolveState($status);

        return [
            'state' => $state,
            'channel' => (string) ($status['channel'] ?? $this->channel()),
            'channel_label' => $this->channelLabel(),
            'current_version' => $this->currentVersion(),
            'current_version_code' => $this->currentVersionCode(),
            'latest_version' => $status['latest_version'] ?? null,
            'latest_version_code' => $status['latest_version_code'] ?? null,
            'apk_url' => $status['apk_url'] ?? null,
            'sha256' => $status['sha256'] ?? null,
            'notes' => $status['notes'] ?? null,
            'min_version_code' => is_numeric($status['min_version_code'] ?? null)
                ? (int) $status['min_version_code']
                : 0,
            'checked_at' => $status['checked_at'] ?? null,
            'message' => $this->resolveMessage($state, $status),
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function resolveState(array $status): string
    {
        $latest = $status['latest_version_code'] ?? null;

        // Sin versión publicada (canal vacío, error de red) se respeta el estado
        // guardado: no hay nada que comparar.
        if (! is_numeric($latest)) {
            return (string) ($status['state'] ?? self::STATE_UNKNOWN);
        }

        $current = $this->currentVersionCode();
        $minimum = is_numeric($status['min_version_code'] ?? null) ? (int) $status['min_version_code'] : 0;

        return match (true) {
            $current < $minimum => self::STATE_REQUIRED,
            (int) $latest > $current => self::STATE_OPTIONAL,
            default => self::STATE_UP_TO_DATE,
        };
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function resolveMessage(string $state, array $status): ?string
    {
        return match ($state) {
            self::STATE_REQUIRED => __('app.updates.required'),
            self::STATE_OPTIONAL => __('app.updates.available'),
            self::STATE_UP_TO_DATE => null,
            default => $status['message'] ?? null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $channel, string $message): array
    {
        return $this->withCurrentVersion([
            'state' => self::STATE_UNAVAILABLE,
            'channel' => $channel,
            'message' => $message,
            'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function remember(array $status): void
    {
        $this->settings->set(self::KEY_CHECKED_AT, (string) $status['checked_at']);
        $this->settings->set(self::KEY_RESULT, (string) json_encode([
            'state' => $status['state'],
            'channel' => $status['channel'],
            'latest_version' => $status['latest_version'],
            'latest_version_code' => $status['latest_version_code'],
            'min_version_code' => $status['min_version_code'] ?? 0,
            'apk_url' => $status['apk_url'],
            'sha256' => $status['sha256'],
            'notes' => $status['notes'],
            'checked_at' => $status['checked_at'],
            'message' => $status['message'],
        ]));
    }

    private function forgetCache(): void
    {
        $this->settings->set(self::KEY_RESULT, '');
        $this->settings->set(self::KEY_CHECKED_AT, '');
    }
}
