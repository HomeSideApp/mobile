#!/usr/bin/env bash
#
# Entrypoint del contenedor de desarrollo.
#
# Problema que resuelve: el contenedor arranca en UTC y el host no, así que
# `date`, los logs y PHP mostraban una hora distinta a la del desarrollador.
#
# Aquí se alinea la zona horaria en runtime con la del host (variable TZ que
# inyecta el Makefile) sin necesidad de root:
#   - el shell y `date` ya respetan TZ por entorno
#   - PHP necesita su ini, que se entrega con permisos del usuario dev
#
set -euo pipefail

TZ_VALUE="${TZ:-}"

if [ -z "$TZ_VALUE" ] && [ -f /etc/timezone ]; then
    TZ_VALUE="$(cat /etc/timezone)"
fi

TZ_VALUE="${TZ_VALUE:-UTC}"

if [ ! -f "/usr/share/zoneinfo/${TZ_VALUE}" ]; then
    echo "aviso: zona horaria '${TZ_VALUE}' desconocida; se usa UTC" >&2
    TZ_VALUE="UTC"
fi

export TZ="$TZ_VALUE"

# PHP: el archivo lo crea la imagen con dueño dev, así que se puede reescribir.
PHP_TIMEZONE_INI="/usr/local/etc/php/conf.d/zz-timezone.ini"
if [ -w "$PHP_TIMEZONE_INI" ] || [ -w "$(dirname "$PHP_TIMEZONE_INI")" ]; then
    echo "date.timezone = ${TZ_VALUE}" > "$PHP_TIMEZONE_INI"
fi

exec /usr/bin/tini -- "$@"
