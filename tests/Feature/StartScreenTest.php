<?php

namespace Tests\Feature;

use App\NativeComponents\Start;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * El arranque decide entre setup, login y listas. Antes la app abría siempre en
 * el setup, así que había que confirmar el servidor en cada arranque.
 */
class StartScreenTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    public function test_without_a_server_it_goes_to_the_setup_screen(): void
    {
        Native::test(Start::class)->assertReplacedWith('/server-setup');
    }

    public function test_with_a_server_but_no_session_it_goes_to_login(): void
    {
        $this->connect(token: null);

        Native::test(Start::class)->assertReplacedWith('/login');
    }

    public function test_with_a_session_it_goes_straight_to_the_lists(): void
    {
        $this->connect();

        Native::test(Start::class)->assertReplacedWith('/lists');
    }
}
