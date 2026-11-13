<?php

namespace App\Services\Homeside;

/**
 * Hogares del usuario y **hogar activo**.
 *
 * El hogar activo condiciona el contenido de casi toda la app: las listas, y más
 * adelante las recetas y los gastos. Vive en `/me` (`active_household_id`) y el
 * nombre sólo se resuelve cruzando ese id contra `GET /households`, así que el
 * cruce se hace acá y no en cada pantalla: había dos copias de esa lógica
 * (`Lists` y `ListCreate`) y una tercera habría sido cuestión de tiempo.
 *
 * Un `active_household_id` que no está en la lista se trata como **sin hogar**:
 * pasa cuando a alguien lo sacaron del hogar o el hogar se borró desde otro
 * dispositivo. Devolver el id muerto haría que todas las pantallas pidan datos a
 * un recurso que ya no existe.
 */
final class HouseholdsService
{
    /**
     * Cache por instancia: una pantalla pide hogar activo y nombre, y sin esto
     * serían dos `GET /households` por render para el mismo dato.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $households = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $moduleCatalog = null;

    public function __construct(
        private readonly Session $session = new Session,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function all(array $query = []): array
    {
        if ($query === [] && $this->households !== null) {
            return $this->households;
        }

        $fetched = array_values(Session::data(
            $this->session->client()->get('/households', $query),
        ));

        if ($query === []) {
            $this->households = $fetched;
        }

        return $fetched;
    }

    public function activeId(): ?string
    {
        $me = (new AuthService)->me();
        $id = $me['active_household_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function active(): ?array
    {
        $id = $this->activeId();

        if ($id === null) {
            return null;
        }

        foreach ($this->all() as $household) {
            if ((string) ($household['id'] ?? '') === $id) {
                return $household;
            }
        }

        return null;
    }

    /**
     * Detalle de un hogar: miembros, capacidades y datos de cabecera.
     *
     * @return array<string, mixed>
     */
    public function find(string $householdId): array
    {
        return Session::data(
            $this->session->client()->get('/households/'.$householdId),
        );
    }

    /**
     * Contadores y **módulos habilitados** del hogar.
     *
     * `enabled_modules` sale de acá y no de `find()`: el resource del hogar
     * expone `economy_module_enabled` pero no el resto de los módulos, y deducir
     * un módulo apagado por un 403 es tarde y confuso.
     *
     * @return array<string, mixed>
     */
    public function dashboard(string $householdId): array
    {
        return Session::data(
            $this->session->client()->get('/households/'.$householdId.'/dashboard'),
        );
    }

    /**
     * Catálogo de módulos que entiende el servidor, con etiquetas ya traducidas
     * por la API. Se usa para nombrar los módulos del hogar sin hardcodear
     * `economy`/`recipes` en la app: si el servidor agrega uno, aparece solo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function moduleCatalog(): array
    {
        if ($this->moduleCatalog !== null) {
            return $this->moduleCatalog;
        }

        $payload = Session::data(
            $this->session->client()->get('/households/configuration'),
        );

        $modules = $payload['modules'] ?? [];

        return $this->moduleCatalog = is_array($modules)
            ? array_values(array_filter($modules, 'is_array'))
            : [];
    }

    public function name(?string $householdId): ?string
    {
        if ($householdId === null || $householdId === '') {
            return null;
        }

        foreach ($this->all() as $household) {
            if ((string) ($household['id'] ?? '') === $householdId) {
                $name = $household['name'] ?? null;

                return is_string($name) ? $name : null;
            }
        }

        return null;
    }
}
