<?php

namespace Tests\Concerns;

use App\Services\Settings\SettingsRepository;
use App\Support\ServerUrl;

trait InteractsWithHomeside
{
    protected function connect(?string $token = 'session-token', string $server = 'https://homeside.cloud'): void
    {
        $settings = new SettingsRepository;
        $settings->setServer(ServerUrl::fromUserInput($server));

        if ($token !== null) {
            $settings->setToken($token);
        }
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    protected function me(array $user = []): array
    {
        return [
            'id' => 'user-1',
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'locale' => 'es-ES',
            'two_factor_enabled' => true,
            'active_household_id' => 'household-1',
            'operation_authorization' => ['households' => []],
            ...$user,
        ];
    }
}
