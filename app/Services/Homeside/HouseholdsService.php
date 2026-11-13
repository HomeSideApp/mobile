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
     * Cache del listado para no pedirlo dos veces en el mismo render.
     *
     * **Ojo con el alcance**: el runtime de NativePHP es persistente y el
     * servicio es un singleton del contenedor, así que este memo puede vivir
     * más que un request. Se invalida en cada mutación (`create`, `switchTo`,
     * `acceptInvitation`, `declineInvitation`) con `forget()`; sin eso la lista
     * quedaba vieja justo después de aceptar una invitación.
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

    /**
     * Descarta el listado cacheado. Se llama en cada operación que cambia la
     * pertenencia a hogares.
     */
    public function forget(): void
    {
        $this->households = null;
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
     * Crea un hogar.
     *
     * La API cambia `active_household_id` por su cuenta, así que el que llama no
     * necesita un switch posterior. Puede responder 402 cuando el plan no
     * permite más hogares.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        $created = Session::data(
            $this->session->client()->post('/households', $attributes),
        );

        // Un hogar nuevo cambia la lista: el memo tiene que morir acá.
        $this->forget();

        return $created;
    }

    /**
     * Cambia el hogar activo en el servidor.
     *
     * No toca el memo a propósito: el id activo sale de `/me`, que se vuelve a
     * pedir en el próximo request. Cambiar de hogar es un cambio de contexto,
     * no un detalle de caché.
     */
    public function switchTo(string $householdId): void
    {
        Session::data(
            $this->session->client()->post('/households/switch/'.$householdId),
        );

        $this->forget();
    }

    /**
     * Invitaciones pendientes dirigidas al email del usuario.
     *
     * @return array<int, array<string, mixed>>
     */
    public function invitations(): array
    {
        return array_values(Session::data(
            $this->session->client()->get('/households/invitations'),
        ));
    }

    public function acceptInvitation(string $invitationId): void
    {
        Session::data(
            $this->session->client()->post('/households/invitations/'.$invitationId.'/accept'),
        );

        $this->forget();
    }

    /**
     * Rechazar es cancelar la propia invitación.
     *
     * No hay endpoint de "reject": `CancelInvitation` autoriza por email, así
     * que el invitado puede cancelar la suya con el mismo DELETE con el que un
     * admin cancela una enviada.
     */
    public function declineInvitation(string $invitationId): void
    {
        Session::data(
            $this->session->client()->delete('/households/invitations/'.$invitationId),
        );

        $this->forget();
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
