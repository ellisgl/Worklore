# Worklore dev shortcuts. Run `make` (or `make help`) to list targets.
# Everything runs inside the containers, so only Docker + make are needed on the host.

DC      := docker compose
# Allocate a TTY only when we have one (keeps colors locally, works in CI)
TTY     := $(shell [ -t 0 ] || echo -T)
PHP     := $(DC) exec $(TTY) php
CONSOLE := $(PHP) bin/console

.DEFAULT_GOAL := help
.PHONY: help init up down restart build ps logs sh console composer db-migrate psql test-db test stan cs cs-fix

help: ## List available targets
	@awk 'BEGIN {FS = ":.*## "} /^[a-zA-Z_-]+:.*## / {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo
	@echo "  Pass arguments with c=, e.g. make console c='debug:router'  |  make test c='--filter Refresh'"

# Fresh clones have no .env (it's git-ignored); start from the committed defaults.
.env:
	cp .env.dist .env
	@echo "Created .env from .env.dist; adjust it if needed."

init: .env up ## First-time setup: create .env, start the stack, install deps, migrate dev + test DBs
	$(PHP) composer install
	$(MAKE) db-migrate test-db

up: .env ## Start the stack (builds images if needed)
	$(DC) up -d --build --wait

down: ## Stop the stack
	$(DC) down

restart: down up ## Restart the stack

build: .env ## Rebuild images
	$(DC) build

ps: ## Show container status
	$(DC) ps

logs: ## Follow container logs
	$(DC) logs -f

sh: ## Shell in the PHP container
	$(PHP) sh

console: ## Run bin/console, e.g. make console c='app:user:create me@example.com --admin'
	$(CONSOLE) $(c)

composer: ## Run composer, e.g. make composer c='require symfony/uid'
	$(PHP) composer $(c)

db-migrate: ## Apply migrations to the dev database
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration

psql: ## psql shell on the dev database
	$(DC) exec postgres sh -c 'psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"'

test-db: ## Create (if missing) and migrate the test database
	$(CONSOLE) doctrine:database:create --env=test --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --env=test -n --allow-no-migration

test: test-db ## Prepare the test DB, then run PHPUnit, e.g. make test c='--filter Refresh'
	$(PHP) bin/phpunit $(c)

stan: ## Static analysis with PHPStan (level max), e.g. make stan c='--generate-baseline'
	$(CONSOLE) cache:warmup --env=dev >/dev/null
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G $(c)

cs: ## Check code style (PHP CS Fixer, dry run)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff $(c)

cs-fix: ## Fix code style in place
	$(PHP) vendor/bin/php-cs-fixer fix $(c)
