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

    private function path(?string $householdId, string $endpoint): string
    {
        $base = $householdId !== null && $householdId !== ''
            ? '/households/'.$householdId.'/economy'
            : '/economy/me';

        return $base.'/'.$endpoint;
    }
}
