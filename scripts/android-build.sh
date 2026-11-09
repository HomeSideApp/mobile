#!/usr/bin/env bash
#
# Build Android de la app con el presupuesto de memoria ajustado al contenedor.
#
# `nativephp/android/` es efímero (lo regenera `native:install`), así que en vez
# de editar archivos generados a mano se parchea justo antes de compilar:
#
#   - local.properties: ruta del SDK (NativePHP la deja vacía si no hay
#     NATIVEPHP_ANDROID_SDK_LOCATION)
#   - gradle.properties: heap del daemon y paralelismo. El default del upstream
#     es -Xmx4096m con parallel=true, que en una laptop con Docker + emulador
#     termina en OOM-kill del daemon ("Gradle build daemon disappeared").
#
# Uso: scripts/android-build.sh [android|ios] [udid] [flags de native:run]
#
set -euo pipefail

cd /app

GRADLE_HEAP="${GRADLE_HEAP:-2048}"
SDK_PATH="${NATIVEPHP_ANDROID_SDK_LOCATION:-/opt/android-sdk}"

android_dir="nativephp/android"
gradle_props="${android_dir}/gradle.properties"
local_props="${android_dir}/local.properties"

if [ -d "$android_dir" ]; then
    printf 'sdk.dir=%s\n' "$SDK_PATH" > "$local_props"

    # Fondo del icono adaptativo: el de la plantilla es blanco puro y el icono
    # de marca está compuesto sobre el cálido del tema, así que se igualan para
    # que el cuadrado del foreground no se note dentro de la máscara.
    launcher_background="$android_dir/app/src/main/res/drawable/ic_launcher_background.xml"
    if [ -f "$launcher_background" ]; then
        cat > "$launcher_background" <<'XML'
<?xml version="1.0" encoding="utf-8"?>
<shape xmlns:android="http://schemas.android.com/apk/res/android"
       android:shape="rectangle">
    <solid android:color="#FBFCFA"/>
</shape>
XML
    fi

    if [ -f "$gradle_props" ]; then
        sed -i -E \
            "s|^org\.gradle\.jvmargs=.*|org.gradle.jvmargs=-Xmx${GRADLE_HEAP}m -XX:MaxMetaspaceSize=512m -Dfile.encoding=UTF-8|" \
            "$gradle_props"

        if grep -q '^org.gradle.parallel=' "$gradle_props"; then
            sed -i -E 's|^org\.gradle\.parallel=.*|org.gradle.parallel=false|' "$gradle_props"
        else
            printf 'org.gradle.parallel=false\n' >> "$gradle_props"
        fi

        echo "==> Gradle: -Xmx${GRADLE_HEAP}m, parallel=false, sdk.dir=${SDK_PATH}"
    fi
fi

exec php artisan native:run "$@"
