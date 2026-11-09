# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# HomeSide Mobile — imagen de desarrollo
#
# Todo lo que la app necesita para compilar, testear y correr sin instalar
# nada en el host: PHP 8.5 + Composer, Node 24, JDK 17 y Android SDK + adb.
#
# iOS queda fuera por definición: Xcode solo corre en macOS.
#
#   docker compose build
#   docker compose run --rm app bash
# ---------------------------------------------------------------------------

ARG PHP_VERSION=8.5
ARG NODE_VERSION=24

FROM node:${NODE_VERSION}-bookworm-slim AS node

FROM php:${PHP_VERSION}-cli-bookworm

ARG UID=1000
ARG GID=1000
ARG WITH_ANDROID=1
ARG ANDROID_API=35
ARG ANDROID_BUILD_TOOLS=35.0.0
ARG CMDLINE_TOOLS=13114758
ARG TZ=UTC

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/home/dev/.composer \
    TZ=${TZ} \
    ANDROID_HOME=/opt/android-sdk \
    ANDROID_SDK_ROOT=/opt/android-sdk \
    GRADLE_USER_HOME=/home/dev/.gradle \
    PATH=/usr/lib/jvm/default-java/bin:/opt/android-sdk/cmdline-tools/latest/bin:/opt/android-sdk/platform-tools:/opt/android-sdk/build-tools/35.0.0:/opt/android-sdk/emulator:/usr/local/bin:$PATH

# ---------------------------------------------------------------------------
# Dependencias de sistema + extensiones PHP
# ---------------------------------------------------------------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        unzip \
        zip \
        jq \
        rsync \
        procps \
        tini \
        tzdata \
        default-jdk-headless \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
        libsqlite3-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_sqlite \
        sockets \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# ---------------------------------------------------------------------------
# Zona horaria (alineada con el host en build y en runtime)
# ---------------------------------------------------------------------------
RUN ln -snf "/usr/share/zoneinfo/${TZ}" /etc/localtime \
    && echo "${TZ}" > /etc/timezone \
    && printf 'date.timezone = %s\n' "${TZ}" > /usr/local/etc/php/conf.d/zz-timezone.ini \
    && chown "${UID}:${GID}" /usr/local/etc/php/conf.d/zz-timezone.ini

# JAVA_HOME estable (default-jdk-headless resuelve a openjdk-17 en bookworm)
RUN if [ ! -e /usr/lib/jvm/default-java ]; then \
        ln -s "$(dirname "$(dirname "$(readlink -f "$(command -v javac)")")")" /usr/lib/jvm/default-java; \
    fi
ENV JAVA_HOME=/usr/lib/jvm/default-java

# ---------------------------------------------------------------------------
# Composer + Node
# ---------------------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# ---------------------------------------------------------------------------
# Usuario no-root alineado con el UID/GID del host (bind mounts sin root)
# ---------------------------------------------------------------------------
RUN set -eux; \
    if ! getent group "${GID}" >/dev/null; then groupadd -g "${GID}" dev; fi; \
    if ! getent passwd "${UID}" >/dev/null; then \
        useradd -m -u "${UID}" -g "${GID}" -s /bin/bash dev; \
    fi; \
    mkdir -p /app /home/dev/.composer /home/dev/.gradle; \
    chown -R "${UID}:${GID}" /app /home/dev

# ---------------------------------------------------------------------------
# Android SDK (opcional: WITH_ANDROID=0 para una imagen solo-PHP más liviana)
# ---------------------------------------------------------------------------
RUN if [ "${WITH_ANDROID}" = "1" ]; then set -eux; \
        mkdir -p "${ANDROID_HOME}/cmdline-tools"; \
        curl -fsSL -o /tmp/cmdline-tools.zip \
            "https://dl.google.com/android/repository/commandlinetools-linux-${CMDLINE_TOOLS}_latest.zip"; \
        unzip -q /tmp/cmdline-tools.zip -d "${ANDROID_HOME}/cmdline-tools"; \
        mv "${ANDROID_HOME}/cmdline-tools/cmdline-tools" "${ANDROID_HOME}/cmdline-tools/latest"; \
        yes | sdkmanager --licenses > /dev/null; \
        sdkmanager --install \
            "platform-tools" \
            "platforms;android-${ANDROID_API}" \
            "build-tools;${ANDROID_BUILD_TOOLS}"; \
        rm -f /tmp/cmdline-tools.zip; \
        chown -R "${UID}:${GID}" "${ANDROID_HOME}"; \
    else \
        echo "Android SDK omitido (WITH_ANDROID=0)"; \
    fi

# PATH también para shells de login (bash -lc), que resetean el entorno
RUN printf '%s\n' \
      'export ANDROID_HOME=/opt/android-sdk' \
      'export ANDROID_SDK_ROOT=/opt/android-sdk' \
      'export JAVA_HOME=/usr/lib/jvm/default-java' \
      'export PATH=$JAVA_HOME/bin:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools:$ANDROID_HOME/build-tools/35.0.0:$ANDROID_HOME/emulator:$PATH' \
      > /etc/profile.d/android-sdk.sh

COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

WORKDIR /app
USER dev

ENTRYPOINT ["/usr/local/bin/docker-entrypoint"]
CMD ["sleep", "infinity"]
