<?php

namespace Tests\Feature;

use App\NativeComponents\Lists;
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

    public function test_the_tab_layout_offers_the_active_household_as_a_nav_action(): void
    {
        $this->connect();
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
        ]);

        $bar = (new AppTabsLayout)->navBar(new Lists);

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

        $bar = (new AppTabsLayout)->navBar(new Lists);

        // Sin hogar no hay selector: no se muestra una acción que lleva a una
        // lista vacía.
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
