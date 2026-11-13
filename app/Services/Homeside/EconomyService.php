<?php

namespace App\Services\Homeside;

/**
 * Economía: personales (`/economy/me`) y de hogar
 * (`/households/{household}/economy`), igual que las listas.
 *
 * La API devuelve importes en **unidades menores**: el formateo es de la vista.
 */
final class EconomyService
{
    public function __construct(
        private readonly Session $session = new Session,
    ) {}

    /**
     * Resumen del período: totales del mes, serie mensual y agrupaciones.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function overview(?string $householdId = null, array $query = []): array
    {
        return Session::data(
            $this->session->client()->get($this->path($householdId, 'overview'), $query),
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function transactions(?string $householdId = null, array $query = []): array
    {
        return array_values(Session::data(
            $this->session->client()->get($this->path($householdId, 'transactions'), $query),
        ));
    }

    /**
     * Alta de movimiento. Con `idempotencyKey` el servidor no duplica si el
     * usuario toca dos veces.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(?string $householdId, array $payload, ?string $idempotencyKey = null): array
    {
        return Session::data(
            $this->session->client()->post($this->path($householdId, 'transactions'), $payload, $idempotencyKey),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(?string $householdId, string $transactionId, array $payload): array
    {
        return Session::data(
            $this->session->client()->put($this->path($householdId, 'transactions').'/'.$transactionId, $payload),
        );
    }

    public function destroy(?string $householdId, string $transactionId): void
    {
        Session::data(
            $this->session->client()->delete($this->path($householdId, 'transactions').'/'.$transactionId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function transaction(?string $householdId, string $transactionId): array
    {
        return Session::data(
            $this->session->client()->get($this->path($householdId, 'transactions').'/'.$transactionId),
        );
    }

    private function path(?string $householdId, string $endpoint): string
    {
        $base = $householdId !== null && $householdId !== ''
            ? '/households/'.$householdId.'/economy'
            : '/economy/me';

        return $base.'/'.$endpoint;
    }
}
