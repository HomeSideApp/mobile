<?php

namespace Tests\Feature;

use App\NativeComponents\House;
use App\NativeComponents\ListCreate;
use App\NativeComponents\ListDetail;
use App\NativeComponents\Lists;
use App\NativeComponents\Login;
use App\NativeComponents\Profile;
use App\NativeComponents\Recipes;
use App\NativeComponents\ServerSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Edge\NativeRouter;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

/**
 * Las rutas nativas sólo se registran en contexto nativo, en tests o en
 * comandos `native:*` (ver NativeServiceProvider::shouldLoadMobileRoutes).
 * Por eso este test es la verificación de que el cableado existe y renderiza.
 */
class NativeRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_tab_screen_renders_under_the_tabs_layout(): void
    {
        Native::visit('/lists')->assertScreen(Lists::class);
        Native::visit('/recipes')->assertScreen(Recipes::class);
        Native::visit('/house')->assertScreen(House::class);
        Native::visit('/profile')->assertScreen(Profile::class);
    }

    public function test_the_server_setup_screen_renders_outside_the_tabs(): void
    {
        Native::visit('/server-setup')->assertScreen(ServerSetup::class);
    }

    public function test_the_login_screen_renders_outside_the_tabs(): void
    {
        Native::visit('/login')->assertScreen(Login::class);
    }

    public function test_the_pushed_list_screens_render_with_a_stack_chrome(): void
    {
        Native::visit('/lists/create')->assertScreen(ListCreate::class);
        Native::visit('/lists/list-1')->assertScreen(ListDetail::class);
        Native::visit('/households/household-1/lists/list-1')->assertScreen(ListDetail::class);
    }

    public function test_an_unknown_native_uri_is_not_resolved(): void
    {
        $this->assertNull(NativeRouter::resolve('/does-not-exist'));
    }
}
