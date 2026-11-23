<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Vigila la trampa de `Http::fake()`, que ya rompió varios tests.
 *
 * La primera llamada gana para todas las URLs, así que un test que re-stubea
 * dentro del mismo método termina probando el escenario anterior. Mientras el
 * helper `fakeHttp()` existe para ese caso, este test impide que vuelva a
 * colarse una segunda llamada cruda.
 */
class HttpFakeGuardTest extends TestCase
{
    public function test_no_test_method_re_stubs_http_with_the_raw_facade(): void
    {
        $offenders = [];

        foreach (glob(base_path('tests/{Feature,Unit}/*.php'), GLOB_BRACE) ?: [] as $file) {
            foreach ($this->methodsOf((string) file_get_contents($file), basename($file)) as $method => $body) {
                if (substr_count($this->withoutComments($body), 'Http::fake(') > 1) {
                    $offenders[] = basename($file).'::'.$method;
                }
            }
        }

        $this->assertSame([], $offenders, 'Usá $this->fakeHttp() para re-stubbear dentro de un test: '.implode(', ', $offenders));
    }

    /**
     * El cuerpo sin comentarios.
     *
     * Los comentarios que explican la trampa nombran `Http::fake()`, y contarlos
     * marcaba como infractor al test que justamente la evita.
     */
    private function withoutComments(string $body): string
    {
        $code = '';

        foreach (token_get_all('<?php '.$body) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /**
     * @return array<string, string> nombre del método => cuerpo
     */
    private function methodsOf(string $source, string $file): array
    {
        if (basename($file) === 'HttpFakeGuardTest.php') {
            return [];
        }

        // Se corta en **cualquier** método, no sólo en los de test: si no, el
        // cuerpo de un helper privado (`noSignal()`) cae dentro del test
        // anterior y el conteo da un falso positivo.
        $methods = [];
        $parts = preg_split('/(?=^\s{4}(?:public|protected|private)\s+function\s)/m', $source) ?: [];

        foreach ($parts as $part) {
            if (preg_match('/^\s{4}(?:public|protected|private)\s+function\s+(test_\w+)/', $part, $m) === 1) {
                $methods[$m[1]] = $part;
            }
        }

        return $methods;
    }
}
