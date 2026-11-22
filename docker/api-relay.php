<?php
// Relay TCP transparente hacia un backend real.
//
// El emulador corre dentro de un contenedor, así que para Android `10.0.2.2` es
// el **contenedor**, no el host de Docker. Y el contenedor no ve el backend
// desde su red por defecto (`homeside-mobile_default`). Conectándolo a la red
// de Sail, el backend real queda a un nombre de distancia:
//
//   docker network connect homeside_sail homeside-mobile-emulator-1
//   php docker/api-relay.php tcp://0.0.0.0:8080 laravel.test:80
//
// y la app apunta a `http://10.0.2.2:8080`. Reenvía byte a byte: no hay mock ni
// traducción, así que lo que se prueba es el servidor de verdad.

$listen = $argv[1] ?? 'tcp://0.0.0.0:8080';
$target = $argv[2] ?? 'laravel.test:80';

$server = stream_socket_server($listen, $errno, $errstr);

if ($server === false) {
    fwrite(STDERR, "no pude escuchar en $listen: $errstr\n");
    exit(1);
}

fwrite(STDERR, "relay $listen -> $target\n");

while (true) {
    $client = @stream_socket_accept($server, -1);

    if ($client === false) {
        continue;
    }

    $upstream = @stream_socket_client('tcp://'.$target, $errno, $errstr, 5);

    if ($upstream === false) {
        fclose($client);

        continue;
    }

    foreach ([$client, $upstream] as $socket) {
        stream_set_blocking($socket, false);
    }

    $pairs = ['c' => $client, 'u' => $upstream];

    while (true) {
        $read = array_values($pairs);
        $write = null;
        $except = null;

        if (@stream_select($read, $write, $except, 5) === false) {
            break;
        }

        foreach ($pairs as $side => $socket) {
            if (! in_array($socket, $read, true)) {
                continue;
            }

            $data = @fread($socket, 65536);

            if ($data === '' || $data === false) {
                break 2;
            }

            $other = $side === 'c' ? $upstream : $client;

            if (@fwrite($other, $data) === false) {
                break 2;
            }
        }
    }

    fclose($client);
    fclose($upstream);
}
