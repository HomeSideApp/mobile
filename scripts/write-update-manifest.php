<?php

/**
 * Escribe el manifiesto de actualización que consume la app.
 *
 * Se publica como asset del release de GitHub junto al APK, para que la app
 * tenga una fuente estable por canal sin pegarle a la API de GitHub.
 *
 * Uso:
 *   php scripts/write-update-manifest.php \
 *     --channel=dev \
 *     --version=0.1.42-dev \
 *     --version-code=42 \
 *     --apk-url=https://github.com/HomeSideApp/mobile/releases/download/dev-latest/homeside-dev.apk \
 *     --sha256=<hex> \
 *     --output=dist/latest.json \
 *     [--notes="texto"] [--min-version-code=0]
 */

declare(strict_types=1);

$options = getopt('', [
    'channel:',
    'version:',
    'version-code:',
    'apk-url:',
    'sha256:',
    'output:',
    'notes::',
    'min-version-code::',
]);

foreach (['channel', 'version', 'version-code', 'apk-url', 'sha256', 'output'] as $required) {
    if (! isset($options[$required]) || trim((string) $options[$required]) === '') {
        fwrite(STDERR, "write-update-manifest: falta --{$required}\n");
        exit(1);
    }
}

$channel = (string) $options['channel'];

if (! in_array($channel, ['dev', 'stable'], true)) {
    fwrite(STDERR, "write-update-manifest: canal desconocido '{$channel}' (dev|stable)\n");
    exit(1);
}

$manifest = [
    'channel' => $channel,
    'version' => (string) $options['version'],
    'version_code' => (int) $options['version-code'],
    'min_version_code' => (int) ($options['min-version-code'] ?? 0),
    'apk_url' => (string) $options['apk-url'],
    'sha256' => strtolower((string) $options['sha256']),
    'notes' => isset($options['notes']) ? trim((string) $options['notes']) : '',
    'published_at' => gmdate('Y-m-d\TH:i:s\Z'),
];

$output = (string) $options['output'];
$directory = dirname($output);

if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
    fwrite(STDERR, "write-update-manifest: no pudimos crear {$directory}\n");
    exit(1);
}

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($json === false || file_put_contents($output, $json."\n") === false) {
    fwrite(STDERR, "write-update-manifest: no pudimos escribir {$output}\n");
    exit(1);
}

fwrite(STDOUT, "  manifiesto {$channel} -> {$output}\n");
