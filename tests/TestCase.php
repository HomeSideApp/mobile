<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fake HTTP con la pizarra limpia.
     *
     * `Http::fake()` **agrega** stubs a los que ya había: una segunda llamada
     * dentro del mismo test deja la primera regla (muchas veces la que simula
     * que no hay red) matcheando antes que la nueva, y el test falla por un
     * motivo que no tiene nada que ver con lo que prueba.
     *
     * Este helper descarta la instancia del cliente para que cada llamada
     * defina **todo** el stubbing. Úsalo siempre que necesites re-stubbear
     * dentro de un mismo test; el test `HttpFakeGuardTest` lo verifica.
     *
     * @param  array<string, mixed>|callable  $stubs
     */
    protected function fakeHttp(array|callable $stubs): void
    {
        $this->app->forgetInstance(Factory::class);
        Http::clearResolvedInstances();

        Http::fake($stubs);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // La suite verifica los textos en español, así que lo fija acá en vez de
        // heredarlo del `.env` local: sin esto, cambiar el idioma de fábrica
        // (que ahora es inglés) rompe decenas de aserciones por un motivo que
        // no tiene nada que ver con lo que prueban. El default de fábrica tiene
        // su propio test en `LocaleTest`.
        config(['app.locale' => 'es-ES', 'homeside.locale' => 'es-ES']);
        App::setLocale('es-ES');
    }
}
