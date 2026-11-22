<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\App;

abstract class TestCase extends BaseTestCase
{
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
