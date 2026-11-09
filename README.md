# HomeSide Mobile

Cliente nativo iOS/Android de [HomeSide](https://homeside.cloud) construido con
**NativePHP for Mobile v4 + SuperNative (EDGE)**: SwiftUI y Jetpack Compose
generados desde Blade, sin webview.

App **de usuario**. La administración sigue viviendo en la web de cada
instancia.

- Consume la API `GET/POST /api/v1/*` de HomeSide.
- Servidor por defecto `https://homeside.cloud`, reemplazable por cualquier
  instancia self-hosted.
- Tema derivado de `homeside/DESIGN.md` (`config/native-ui.php`).
- Los colores de los cards de listas vienen del API (`category.color`).

## Requisitos del host

Solo **Docker** y **Docker Compose**. PHP, Composer, Node, JDK y el Android SDK
viven dentro de la imagen `docker/Dockerfile`.

```bash
cp compose.env.example compose.env   # UID/GID del host + IP para Jump
make build                           # ~5-8 min la primera vez
make bootstrap                       # Laravel 13 + NativePHP + tema
make test                            # suite dentro del contenedor
```

Para una imagen sin Android SDK (iteración rápida en PHP): `make build-php-only`.

## Comandos

| Comando | Qué hace |
| --- | --- |
| `make sh` | shell en el contenedor |
| `make test` | PHPUnit completo |
| `make pint` | formato |
| `make serve` | `artisan serve` en `:8000` |
| `make jump IP=192.168.1.42` | preview en teléfono con la app Jump |
| `make android` | `native:run android` (emulador o dispositivo) |
| `make adb-connect HOST=host.docker.internal:5555` | conectar a un emulador del host |
| `make plugin-list` | plugins NativePHP activos |

## Probar la UI nativa

Tres caminos. **No hace falta máquina virtual** (el emulador corre en el
contenedor con `/dev/kvm`).

### 1. Emulador headless en Docker (recomendado)

```bash
make emulator-build     # imagen con emulador + Android 15 (~2 GB extra, una vez)
make emulator-up        # arranca y espera el boot (healthcheck sys.boot_completed)
make android-emulator   # compila, instala y abre la app en el emulador
make emulator-logs      # logs en vivo
make emulator-down      # apagar (libera ~3 GB de RAM)
```

Cómo se cablea: el contenedor `emulator` levanta `adb -a nodaemon server` en
`5037` y el emulador headless (SwiftShader, sin ventana). El contenedor `app`
se conecta con `ADB_SERVER_SOCKET=tcp:emulator:5037`, así `native:run` ve el
dispositivo sin publicar puertos al host.

Requisitos: `/dev/kvm` en el host (Linux con virtualización). En macOS el
emulador no corre en Docker: usá el de Android Studio + `make adb-connect`.

### 2. Emulador del host o teléfono real

```bash
make adb-connect HOST=host.docker.internal:5555   # emulador en el host
make adb-connect HOST=192.168.1.50:5555           # teléfono por Wi-Fi (adb tcpip 5555)
make android                                      # compila e instala
```

### 3. Jump

`make jump IP=<tu-ip-lan>`. Aviso: Jump renderiza en su **WebView**, así que
**no muestra la UI nativa** de SuperNative; sirve para validar lógica PHP y el
cliente HTTP, no el aspecto.

iOS se compila **solo en macOS** (Xcode 26+).

## Memoria y zona horaria

- **Zona horaria**: el Makefile detecta la del host y la inyecta como `TZ`. El
  entrypoint la propaga a PHP (`date.timezone`), así `date`, los logs y
  `now()` del contenedor coinciden con tu reloj. Forzar: `make test TZ=UTC`.
- **Gradle**: por defecto `GRADLE_HEAP=2048` (el upstream trae `-Xmx4096m` con
  `parallel=true`, que con el emulador arriba termina en OOM-kill del daemon).
  `scripts/android-build.sh` parchea `gradle.properties` y `local.properties`
  antes de compilar. Bajar a `1536` en máquinas con poca RAM.
- **SDK persistente**: `/opt/android-sdk` vive en el volumen `android-sdk`, así
  NDK/CMake/platform 36 se instalan una sola vez. `make clean-sdk` lo resetea.

## Estado

- **Fase 0 (hecha)**: entorno Docker, scaffold, tema, cliente HTTP, pantalla de
  servidor con health check, tabs y tests.
- **Fase 1 (hecha)**: login con OTP, listas personales y del hogar, detalle con
  cards por color, alta de ítems con buscador, quick-create y perfil con cierre
  de sesión.
- **Pendiente**: cache offline con cola de mutaciones (T-16) y contadores por
  lista, que necesitan `GET /v1/lists` unificado en el backend (T-90).

Backlog detallado: `../odd/tasks/homeside-mobile.md`.

## Generar un APK para instalar

```bash
make emulator-up                  # solo si vas a instalar en el emulador
make android-emulator             # compila, firma en debug e instala

# o compilar e instalar en un teléfono real conectado por adb:
make android
```

El APK queda en `nativephp/android/app/build/outputs/apk/debug/app-debug.apk`.
Copialo a `dist/` para compartirlo:

```bash
mkdir -p dist && cp nativephp/android/app/build/outputs/apk/debug/app-debug.apk \
    dist/homeside-0.1.0-debug.apk
```

Es un build **debug** firmado con la keystore de desarrollo: sirve para
instalarlo a mano (hay que permitir "orígenes desconocidos" en Android), no para
publicarlo en la tienda. Para Play/App Store hace falta `native:package` con una
keystore propia.

## Identidad visual

`public/icon.png`, `public/splash.png` y `public/splash-dark.png` se generan
desde el mark canónico de HomeSide (`resources/brand/logo.png`):

```bash
php scripts/generate-brand-assets.php
```

- **Icono**: 1024x1024 opaco sobre `#FBFCFA` (iOS rechaza alfa; el launcher
  adaptativo de Android usa la misma tonalidad de fondo para que el recorte de
  la máscara no se note).
- **Splash**: 960x1440 en claro (`#FBFCFA`) y oscuro (`#0B2F2F`).
- NativePHP regenera los mipmaps y las densidades del splash en cada build.

## Identidad de la app

| Ajuste | Valor |
| --- | --- |
| `NATIVEPHP_APP_ID` | `cloud.homeside.app` |
| Deep link | `homeshop://` |
| Arranque | `/server-setup` |
| Versión | `0.1.0` |

Los mails de HomeSide (reset de contraseña, verificación, set-password) ya
apuntan a la app mediante `MOBILE_APP_URL`; los deep links se implementan en la
Fase 2.
