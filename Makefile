.DEFAULT_GOAL := help

# Alias SSH del VPS de producción (en ~/.ssh/config).
PROD_SSH ?= emkaibot

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

.PHONY: account-prod
account-prod: ## Igual que `account`, pero en PRODUCCIÓN (por SSH al VPS). Uso: make account-prod EMAIL=ana@x.com [NAME=Ana]
	@test -n "$(EMAIL)" || (echo "Falta EMAIL. Uso: make account-prod EMAIL=ana@x.com [NAME=Ana]" && exit 1)
	@ssh $(PROD_SSH) 'c=$$(docker ps -q --filter "label=com.docker.compose.service=php" --filter "label=com.docker.compose.project" | while read id; do docker inspect -f "{{index .Config.Labels \"com.docker.compose.project\"}} {{.Name}}" $$id; done | grep "^aetheria-bff" | head -1 | cut -d/ -f2); \
		test -n "$$c" || { echo "No encuentro el contenedor php de producción (¿está desplegado?)"; exit 1; }; \
		docker exec $$c php bin/console eter:account:create $(EMAIL) $(if $(NAME),--name="$(NAME)")'

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
