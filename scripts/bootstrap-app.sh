#!/usr/bin/env bash
#
# Bootstrap idempotente del proyecto HomeSide Mobile.
# Se ejecuta DENTRO del contenedor de desarrollo: `make bootstrap`.
#
set -euo pipefail

cd /app

echo "==> PHP:      $(php -v | head -n1)"
echo "==> Composer: $(composer --version | head -n1)"
echo "==> Node:     $(node -v)"

if [[ ! -f artisan ]]; then
    echo "==> Creando esqueleto Laravel 13 en /tmp/skel"
    rm -rf /tmp/skel
    composer create-project "laravel/laravel:^13.0" /tmp/skel \
        --no-interaction --prefer-dist --no-scripts
    rsync -a --exclude '.git' /tmp/skel/ /app/
    rm -rf /tmp/skel
fi

if [[ ! -f .env ]]; then
    cp .env.example .env
    php artisan key:generate --no-interaction
fi

[[ -f database/database.sqlite ]] || touch database/database.sqlite

if [[ ! -f composer.lock ]]; then
    composer install --no-interaction --prefer-dist
fi

echo "==> Dependencias NativePHP"
if ! grep -q 'nativephp/mobile' composer.json; then
    composer require \
        "nativephp/mobile:^4.7" \
        "nativephp/mobile-ui:^0.9" \
        --no-interaction
fi

echo "==> Publicando provider de plugins y config del tema"
php artisan vendor:publish --tag=nativephp-plugins-provider --force
php artisan native:plugin:register nativephp/mobile-ui || true
php artisan vendor:publish --tag=native-ui-config --force || true

echo "==> SecureStorage disponible en core/UI?"
if php -r 'exit(class_exists("Native\\Mobile\\Facades\\SecureStorage") ? 0 : 1);'; then
    echo "    sí: Native\\Mobile\\Facades\\SecureStorage"
else
    echo "    no: se usará Crypt (APP_KEY por dispositivo) como fallback"
fi

echo "==> Plugins registrados"
php artisan native:plugin:list || true

echo "==> Listo. Siguiente: make test / make jump"
