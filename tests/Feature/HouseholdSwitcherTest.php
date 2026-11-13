<?php

namespace Tests\Feature;

use App\NativeComponents\Expenses;
use App\NativeComponents\House;
use App\NativeComponents\Lists;
use App\NativeComponents\Profile;
use App\NativeComponents\Recipes;
use App\NativeLayouts\AppTabsLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * El selector vive en la barra superior que arma el layout de pestañas: si esa
 * acción no se agrega, la lista de hogares es inalcanzable desde la app.
 */
class HouseholdSwitcherTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    /**
     * El layout agrega la acción en la barra y esa acción invoca un método de la
     * pantalla que se está mostrando. Una pestaña nueva sin el trait deja el ⋮
     * muerto y en silencio — pasó con Gastos.
     */
    public function test_every_tab_screen_can_open_the_switcher(): void
    {
        $tabs = [
            'lists' => Lists::class,
            'recipes' => Recipes::class,
            'expenses' => Expenses::class,
            'house' => House::class,
            'profile' => Profile::class,
        ];

        foreach ($tabs as $uri => $component) {
            $this->assertTrue(
                method_exists($component, 'openHouseholds'),
                "La pestaña [{$uri}] no implementa openHouseholds(): el selector de la barra no va a responder ahí.",
            );
        }
    }

    public function test_the_tab_layout_offers_the_active_household_as_a_nav_action(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
        ]);

        $screen = new Lists;
        $bar = (new AppTabsLayout)->navBar($screen);
        $props = $bar->toRootProps();

        // El nombre del hogar es el contexto y va de título; la pantalla queda
        // de subtítulo (traducido, como cualquier otro texto de la app). El
        // selector sigue accesible como acción.
        $this->assertSame('Casa de Ana', $props['title'] ?? null);
        $this->assertSame($screen->navTitle(), $props['subtitle'] ?? null);
        $this->assertCount(1, $bar->getActions());
        $this->assertCount(1, $bar->actionElements());
    }

    public function test_without_a_resolvable_household_the_bar_stays_clean(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me(['active_household_id' => null])]),
            '*/api/v1/households' => Http::response(['data' => []]),
        ]);

        $screen = new Lists;
        $bar = (new AppTabsLayout)->navBar($screen);
        $props = $bar->toRootProps();

        // Sin hogar no hay selector: no se muestra una acción que lleva a una
        // lista vacía, y la barra vuelve a mostrar el nombre de la pantalla.
        $this->assertSame($screen->navTitle(), $props['title'] ?? null);
        $this->assertCount(0, $bar->getActions());
    }

    public function test_without_a_session_the_bar_does_not_throw(): void
    {
        $this->connect(token: null);
        Http::fake();

        $bar = (new AppTabsLayout)->navBar(new Lists);

        $this->assertCount(0, $bar->getActions());
    }
}
