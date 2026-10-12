.DEFAULT_GOAL := help

##@ Desarrollo

.PHONY: up
up: ## Levanta BFF, Postgres y Keycloak
	docker compose up -d --build

.PHONY: down
down: ## Para todo
	docker compose down

.PHONY: logs
logs: ## Logs de PHP
	docker compose logs -f php

.PHONY: bash
bash: ## Shell en el contenedor de PHP
	docker compose exec php bash

.PHONY: install
install: ## composer install
	docker compose exec php composer install

##@ Cuentas

.PHONY: account
account: ## Crea una cuenta y le manda el correo de contraseña. Uso: make account EMAIL=ana@x.com [NAME=Ana]
	docker compose exec php php bin/console eter:account:create $(EMAIL) $(if $(NAME),--name="$(NAME)")

.PHONY: keycloak-restart
keycloak-restart: ## Recrea Keycloak para que lea .env.local y le aplica la configuración
	docker compose up -d --force-recreate keycloak
	@until curl -sf http://localhost:8281/realms/eter >/dev/null; do sleep 3; done
	$(MAKE) keycloak-setup

.PHONY: keycloak-setup
keycloak-setup: ## Aplica al realm ya creado lo que el import sólo hace la primera vez (correo, nombre)
	docker compose exec keycloak sh /opt/keycloak/data/import/configure-realm.sh

##@ Base de datos

.PHONY: migrate
migrate: ## Ejecuta las migraciones
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: db-reset
db-reset: ## Borra y recrea la base de datos (¡destructivo!)
	docker compose exec php php bin/console doctrine:database:drop --force
	docker compose exec php php bin/console doctrine:database:create
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

##@ Juego

.PHONY: balance
balance: ## Simula combates de cada clase contra cada jefe (para equilibrar)
	docker compose exec php php bin/console eter:balance

.PHONY: test
test: ## Tests
	docker compose exec php php bin/phpunit

##@ Ayuda

.PHONY: help
help:
	@awk 'BEGIN {FS = ":.*##"; printf "\nUso:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)
