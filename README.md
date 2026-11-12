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

## Idioma

Dos locales, los mismos del backend (`AppLocale`): **es-ES** y **en-US**. Los
textos de la app viven en `lang/{locale}/app.php`; nada de copy hardcodeada en
las pantallas.

Hay dos fuentes de verdad y `LocaleService` las concilia:

| Fuente | Alcance |
|---|---|
| **El dispositivo** (`settings`) | Recuerda la elección sin sesión: la pantalla de conexión y el login ya salen en el idioma correcto |
| **La cuenta** (`user.locale`, vía `/me`) | Manda cuando hay sesión, porque **el backend traduce el catálogo** (categorías y productos) con ese valor |

Cambiar el idioma en **Perfil** lo aplica al instante, lo guarda en el
dispositivo y, si hay sesión, hace `PATCH /me/profile` con `name`, `email` y
`locale` (el endpoint exige el perfil completo). Si ese PATCH falla, la app se
queda con el idioma local y avisa: no se pierde la elección.

Por defecto se usa `APP_LOCALE` (`es-ES`), con `en-US` como fallback.

```bash
php artisan test --filter=LocaleTest   # 9 casos
```

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

## Actualizaciones y canales

La app se actualiza fuera de la tienda: consulta un **manifiesto JSON** publicado
junto a cada APK y, si hay algo nuevo, ofrece el link de descarga. El usuario
elige el canal en **Perfil**.

| Canal | Fuente | Cuándo |
|---|---|---|
| **Desarrollo** (por defecto) | release rodante `dev-latest` | cada push a `develop` |
| **Estable** | `releases/latest`, o sea el último tag `v*` | cuando creás un tag |

```bash
# cortar una versión estable
git tag v0.2.0 && git push origin v0.2.0
```

Versionado:

| | `version` | `version_code` |
|---|---|---|
| dev | `0.1.<run_number>-dev` | `github.run_number` |
| stable | el tag sin la `v` (`0.2.0`) | `github.run_number` |

`version_code` sale de `run_number` para que **siempre suba**: Android lo usa
para aceptar la instalación encima de la anterior y la app para comparar.

> **Ojo con `native:package`**: incrementa `NATIVEPHP_APP_VERSION_CODE` en `.env`
> antes de compilar (las tiendas exigen un build number mayor en cada subida).
> Por eso el manifiesto se genera leyendo el `.env` que quedó **dentro del APK**,
> no el número que le pedimos, y el CI falla si no coinciden. Con el número de
> entrada, la app quedaba un build por delante de lo publicado y nunca veía una
> actualización.

Manifiestos (assets del release, no la API de GitHub — sin token limita a 60
requests/hora por IP):

```
dev     https://github.com/HomeSideApp/mobile/releases/download/dev-latest/latest.json
stable  https://github.com/HomeSideApp/mobile/releases/latest/download/latest.json
```

Reglas del chequeo (`AppUpdateChecker`): una consulta cada 24 h, botón manual
que la saltea, nunca ofrece bajar de versión, y cualquier fallo se muestra como
"no pudimos consultar" sin romper la pantalla. Un `min_version_code` mayor al
instalado marca la actualización como obligatoria (la pantalla bloqueante llega
en la fase B).

### Instalador dentro de la app (opcional)

Por defecto `Perfil` abre el APK en el navegador. Si el plugin
[`homeside/apk-updater`](https://github.com/HomeSideApp/NativePHP-APK-Updater)
está instalado, la app descarga, verifica el SHA-256 e invoca el instalador de
Android sin salir de la pantalla:

```bash
composer config repositories.apk-updater vcs https://github.com/HomeSideApp/NativePHP-APK-Updater.git
composer require homeside/apk-updater:dev-develop
php artisan native:plugin:register homeside/apk-updater
```

Mientras no haya un tag, la app sigue `dev-develop` del repo. El `type` del
paquete tiene que ser `nativephp-plugin`: con `library` se instala y autoloada,
pero el registro de NativePHP no lo ve y el APK se compila sin su Kotlin.

Ya está instalado en `composer.json` como repositorio **git** (no `vcs`): el
driver `git` clona directo y no pasa por la API de GitHub, que sin token limita
a 60 requests por hora y por IP — el motivo por el que el `require` fallaba con
un error de ssh que no tenía nada que ver.

El código está escrito para las dos situaciones (`Profile::inAppUpdater()`): si
la clase no existe —o el dispositivo no es Android— cae al navegador.
`tests/Feature/InAppUpdateTest.php` se saltea solo si el paquete no está.

> Cuidado al publicar el provider: `vendor:publish --tag=nativephp-plugins-provider --force`
> borra los plugins ya registrados (`nativephp/mobile-ui` incluido).

## APK automático en cada push a `develop`

`.github/workflows/android-dev.yml` corre en tres jobs:

1. **¿Cambió código?** — compara el push contra el commit anterior y decide si
   el binario puede ser distinto.
2. **Tests** — PHP 8.5, extensiones, `composer install` y `php artisan test`.
   Corre en todo push y PR: un cambio de tests o de CI también merece verde.
3. **APK** — solo si los tests pasaron **y** cambió código, o si lo disparás a
   mano. Publica en `dev-latest` (rama `develop`) o como release estable
   (tag `v*`), siempre con su `latest.json`.

### Qué cuenta como cambio de código

| Dispara el APK | No lo dispara |
| --- | --- |
| `app/`, `bootstrap/`, `config/`, `database/`, `lang/`, `public/`, `resources/`, `routes/` | `tests/`, `README.md`, `docs/` |
| `composer.json`, `composer.lock`, `artisan` | `.github/`, `docker/`, `Makefile`, `compose.yaml` |
| `nativephp.lock`, `.env.example` | `phpunit.xml`, `scripts/` |

El primer push de una rama y un force-push también construyen (no hay con qué
comparar). Si el diff no se puede calcular, construye igual: es preferible un
APK de más que uno que falta.

### Disparo manual

`Actions → Android dev build → Run workflow` con la opción **Construir y
publicar el APK** (por defecto activada). Sirve para reconstruir sin tocar
código, o para correr solo los tests desactivándola.

> El proyecto requiere **PHP 8.5**: `nativephp/mobile` arrastra `endroid/qr-code`,
> que ya pide `^8.5`. La imagen de desarrollo y el CI usan esa versión.

El APK se puede bajar de dos lugares:

| Lugar | URL |
| --- | --- |
| Release rodante (link estable) | `https://github.com/HomeSideApp/mobile/releases/download/dev-latest/homeside-dev.apk` |
| Artifact del run | pestaña *Actions* → run → *Artifacts* → `homeside-apk-<sha>` |

También se publica `homeside-dev.apk.sha256` y un resumen en la página del run
con el commit y la keystore usada.

### Firma y actualizaciones

El APK se firma con una keystore de desarrollo. Para que `adb install -r`
funcione entre builds, cargá estos secretos del repo:

| Secreto | Contenido |
| --- | --- |
| `ANDROID_KEYSTORE_BASE64` | tu keystore PKCS12 en base64: `base64 -w0 dev.p12` |
| `ANDROID_KEYSTORE_PASSWORD` | la contraseña (alias `homeside`) |

Sin esos secretos el workflow genera una keystore efímera: el APK instala
igual, pero cada build firma distinto y Android va a pedir desinstalar antes
de actualizar.

> Nota: NativePHP imprime "Build output is unsigned" aunque el APK esté firmado;
> es un falso positivo de `jarsigner` (solo ve firmas v1). El workflow verifica
> con `apksigner`, que es la fuente de verdad.

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
- **`logo-mark.png`**: el mark recortado y transparente, para las pantallas
  (cabecera de conexión y login). Las pantallas lo referencian como
  `src="logo-mark.png"`: un path relativo se resuelve contra el `public/`
  empaquetado en la app, así que no depende de red ni del servidor elegido.
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
