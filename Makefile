# HomeSide Mobile — entorno de desarrollo en Docker
#
# Todo corre dentro del contenedor: PHP, Composer, Node, JDK y Android SDK.
# El host solo necesita Docker + Docker Compose.
#
#   make build      # construye la imagen (una vez)
#   make bootstrap  # crea el esqueleto Laravel + NativePHP
#   make test       # corre la suite dentro del contenedor
#   make jump       # preview en un teléfono real con la app Jump

SHELL := /bin/bash
.RECIPEPREFIX = >

COMPOSE := docker compose --env-file compose.env
SERVICE := app

# Emulador headless en un perfil aparte.
COMPOSE_EMULATOR := $(COMPOSE) --profile emulator

# Zona horaria: se toma la del host (Linux/macOS) para que el contenedor no
# muestre una hora distinta a la del desarrollador. Se puede forzar con
# `make ... TZ=America/Argentina/Buenos_Aires`.
HOST_TZ := $(shell cat /etc/timezone 2>/dev/null || readlink -f /etc/localtime 2>/dev/null | sed 's|.*/zoneinfo/||')

# /dev/kvm mantiene el GID del host dentro del contenedor.
KVM_GID ?= $(shell stat -c '%g' /dev/kvm 2>/dev/null || echo 986)
export KVM_GID
TZ := $(if $(strip $(TZ)),$(strip $(TZ)),$(if $(strip $(HOST_TZ)),$(strip $(HOST_TZ)),UTC))
export TZ

.DEFAULT_GOAL := help

help: ## Muestra los comandos disponibles
> @grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

build: ## Construye la imagen de desarrollo
> $(COMPOSE) build

build-php-only: ## Imagen sin Android SDK (más rápida de construir)
> WITH_ANDROID=0 $(COMPOSE) build

up: ## Levanta el contenedor en segundo plano
> $(COMPOSE) up -d

down: ## Detiene el contenedor
> $(COMPOSE) down

sh: ## Shell interactiva dentro del contenedor
> $(COMPOSE) exec $(SERVICE) bash

bootstrap: ## Crea el esqueleto Laravel 13 + NativePHP (idempotente)
> $(COMPOSE) run --rm $(SERVICE) bash scripts/bootstrap-app.sh

install: ## composer install
> $(COMPOSE) run --rm $(SERVICE) composer install

test: ## Corre la suite PHP dentro del contenedor
> $(COMPOSE) run --rm $(SERVICE) php artisan test --compact

pint: ## Formatea con Pint
> $(COMPOSE) run --rm $(SERVICE) ./vendor/bin/pint

serve: ## artisan serve en :8000
> $(COMPOSE) run --rm --service-ports $(SERVICE) php artisan serve --host=0.0.0.0 --port=8000

jump: ## Preview en el teléfono con la app Jump (define JUMP_IP en compose.env)
> @test -n "$(JUMP_IP)" || (echo "Falta JUMP_IP (IP LAN del host) en compose.env"; exit 1)
> $(COMPOSE) run --rm --service-ports $(SERVICE) php artisan native:jump --ip=$(JUMP_IP)

jump-portable: ## Igual que jump pero pidiendo la IP por parámetro: make jump-portable IP=192.168.1.42
> $(COMPOSE) run --rm --service-ports $(SERVICE) php artisan native:jump --ip=$(IP)

android: ## Compila y corre en el emulador/dispositivo Android visible por adb
> $(COMPOSE) run --rm --service-ports $(SERVICE) bash scripts/android-build.sh android

emulator-build: build ## Construye la imagen del emulador headless (build previo incluido)
> $(COMPOSE_EMULATOR) build emulator

emulator-up: ## Arranca el emulador headless y espera a que termine el boot
> $(COMPOSE_EMULATOR) up -d --wait emulator

emulator-status: ## Estado del emulador (boot completado, dispositivo adb)
> $(COMPOSE_EMULATOR) exec -T emulator sh -c 'adb devices -l; echo "boot_completed=$$(adb shell getprop sys.boot_completed 2>/dev/null | tr -d "\r")"'

emulator-logs: ## Logs del emulador en vivo
> $(COMPOSE_EMULATOR) logs -f emulator

emulator-down: ## Detiene el emulador
> $(COMPOSE_EMULATOR) down

android-emulator: ## Corre la app en el emulador headless del compose
> $(COMPOSE_EMULATOR) run --rm -e ADB_SERVER_SOCKET=tcp:emulator:5037 $(SERVICE) bash scripts/android-build.sh android

plugin-list: ## Lista los plugins NativePHP registrados
> $(COMPOSE) run --rm $(SERVICE) php artisan native:plugin:list

adb-connect: ## Conecta a un emulador del host: make adb-connect HOST=host.docker.internal:5555
> $(COMPOSE) run --rm $(SERVICE) adb connect $(or $(HOST),host.docker.internal:5555)

adb-devices: ## Lista dispositivos/emuladores vistos por el contenedor
> $(COMPOSE) run --rm $(SERVICE) adb devices -l

clean: ## Borra contenedor, imagen y volúmenes del proyecto
> $(COMPOSE) down -v --rmi local

clean-sdk: ## Borra el volumen del Android SDK (fuerza reinstalar NDK/CMake en el próximo build)
> docker volume rm -f homeside-mobile_android-sdk

.PHONY: help build build-php-only up down sh bootstrap install test pint serve jump jump-portable android emulator-build emulator-up emulator-status emulator-logs emulator-down android-emulator plugin-list adb-connect adb-devices clean
