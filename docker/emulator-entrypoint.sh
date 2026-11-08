#!/usr/bin/env bash
#
# Arranque del emulador Android headless.
#
# 1. levanta el servidor adb escuchando en todas las interfaces (5037), así el
#    contenedor `app` puede usarlo con ADB_SERVER_SOCKET=tcp:emulator:5037
# 2. arranca el AVD sin ventana, con SwiftShader (sin GPU real)
#
set -euo pipefail

AVD_NAME="${AVD_NAME:-homeside}"
MEMORY="${EMULATOR_MEMORY:-2048}"
CORES="${EMULATOR_CORES:-2}"

echo "==> adb server (0.0.0.0:5037)"
adb -a -P 5037 nodaemon server &
ADB_PID=$!

echo "==> esperando adb"
for _ in $(seq 1 60); do
    if adb devices >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

echo "==> emulador '${AVD_NAME}' (headless, ${MEMORY}MB, ${CORES} cores)"
exec "${ANDROID_HOME}/emulator/emulator" \
    -avd "${AVD_NAME}" \
    -no-window \
    -no-audio \
    -no-boot-anim \
    -no-snapshot \
    -gpu swiftshader_indirect \
    -memory "${MEMORY}" \
    -cores "${CORES}" \
    -netdelay none \
    -netspeed full \
    -port 5554
