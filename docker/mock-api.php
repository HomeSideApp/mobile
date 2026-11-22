<?php
// Backend falso de HomeSide para probar la app en el emulador.
//
// Sirve para dos cosas: arrancar la app sin depender de un servidor real, y ver
// **exactamente** qué manda (método, ruta, `Idempotency-Key` y cuerpo de cada
// request) cuando algo no llega como se espera. Cortar la red y devolverla se
// hace con el modo avión del emulador.
//
//   docker cp docker/mock-api.php homeside-mobile-emulator-1:/tmp/mock.php
//   docker compose --profile emulator exec -T emulator \
//     sh -c 'php -S 0.0.0.0:8080 -t /tmp /tmp/mock.php'
//
// Después, en la app: servidor `http://10.0.2.2:8080`, cualquier email y
// cualquier código de 6 dígitos (el mock no valida credenciales).
//
// Ojo: `10.0.2.2` dentro de un emulador que corre en un contenedor apunta al
// **contenedor**, no al host de Docker. Por eso el mock va adentro.

$log = '/tmp/mock.log';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');

file_put_contents($log, sprintf(
    "%s %-6s %s idem=%s\n%s\n",
    date('H:i:s'),
    $method,
    $uri,
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '-',
    $body === '' ? '' : $body,
), FILE_APPEND);

header('Content-Type: application/json');

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

$listId = '11111111-1111-1111-1111-111111111111';
$now = '2026-11-22T10:00:00Z';

$items = [
    ['id' => 'aaaaaaaa-1111-1111-1111-111111111111', 'list_id' => $listId, 'product_id' => 'p1',
     'custom_name' => null, 'quantity' => 1, 'unit' => 'un', 'is_checked' => false, 'notes' => null,
     'icon' => null, 'image_url' => null, 'store_id' => null, 'category_id' => null,
     'updated_at' => $now, 'product' => ['id' => 'p1', 'name' => 'Leche', 'icon' => null]],
    ['id' => 'aaaaaaaa-2222-2222-2222-222222222222', 'list_id' => $listId, 'product_id' => 'p2',
     'custom_name' => null, 'quantity' => 2, 'unit' => 'un', 'is_checked' => false, 'notes' => null,
     'icon' => null, 'image_url' => null, 'store_id' => null, 'category_id' => null,
     'updated_at' => $now, 'product' => ['id' => 'p2', 'name' => 'Pan', 'icon' => null]],
];

$list = ['id' => $listId, 'name' => 'Compra offline', 'household_id' => null,
         'created_at' => $now, 'updated_at' => $now, 'items' => $items];

$user = ['id' => 'u1', 'name' => 'Dev Emulador', 'email' => 'dev@homeside.test',
         'locale' => 'es-ES', 'active_household_id' => null];

if ($uri === '/api/v1/health' || $uri === '/api/v1/version') {
    out(['status' => 'ok', 'version' => '1.0.0-test']);
}

if ($uri === '/api/v1/auth/login') {
    out(['requires_otp' => true, 'temp_token' => 'temp-token-123', 'token' => null, 'user' => null]);
}

if ($uri === '/api/v1/auth/login/otp' || $uri === '/api/v1/auth/recovery-code') {
    out(['token' => 'tok-abc-123', 'user' => $user]);
}

if ($uri === '/api/v1/me') {
    out($user);
}

if ($uri === '/api/v1/households') {
    out([]);
}

if ($uri === '/api/v1/lists') {
    out([['id' => $listId, 'name' => 'Compra offline', 'household_id' => null,
          'created_at' => $now, 'updated_at' => $now]]);
}

if (str_starts_with($uri, '/api/v1/lists/') && substr_count($uri, '/') === 4) {
    out($list);
}

if ($uri === '/api/v1/categories' || str_starts_with($uri, '/api/v1/products/recent')
    || str_starts_with($uri, '/api/v1/stores') || str_starts_with($uri, '/api/v1/households/')) {
    out([]);
}

if ($uri === '/api/v1/sync/changes') {
    out(['cursor' => 'cursor-1', 'has_more' => false,
         'changes' => ['lists' => [$list], 'list_items' => $items, 'households' => []],
         'deleted' => ['lists' => [], 'list_items' => [], 'economy_transactions' => []]]);
}

if ($uri === '/api/v1/sync/push') {
    $payload = json_decode($body, true) ?: [];
    $results = [];

    foreach (($payload['operations'] ?? []) as $op) {
        $results[] = ['op_id' => $op['op_id'] ?? '0', 'status' => 'applied', 'body' => []];
    }

    out(['results' => $results]);
}

out([]);
