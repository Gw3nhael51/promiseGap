.DEFAULT_GOAL := help
.PHONY: help up down restart logs ps install db-create db-migrate db-test db-check cache lint test serve worker test-ai eval

help: ## Display available commands
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-18s\033[0m %s\n", $$1, $$2}'

## --- Docker & Services ---
up: ## Start Docker containers (PostgreSQL pgvector, Mailpit)
	docker compose up -d

down: ## Stop Docker containers
	docker compose down

restart: ## Restart Docker containers
	docker compose restart

logs: ## Stream Docker container logs
	docker compose logs -f

ps: ## List running containers
	docker compose ps

## --- Database ---
db-create: ## Create database if it does not exist
	php bin/console doctrine:database:create --if-not-exists

db-migrate: ## Execute pending Doctrine migrations
	php bin/console doctrine:migrations:migrate --no-interaction

db-test: ## Setup test database and execute migrations
	APP_ENV=test php bin/console doctrine:database:create --if-not-exists
	APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction

db-check: ## Verify database connection and pgvector extension
	php bin/console dbal:run-sql "SELECT extname, extversion FROM pg_extension WHERE extname = 'vector';"

## --- Application & Quality ---
install: ## Install composer dependencies and setup assets
	composer install
	php bin/console assets:install public --symlink --relative
	php bin/console importmap:install

cache: ## Clear Symfony cache
	php bin/console cache:clear

lint: ## Run container, twig, and yaml linters
	php bin/console lint:container
	php bin/console lint:twig templates/
	php bin/console lint:yaml config/

test: ## Run test suite via PHPUnit
	php bin/phpunit

serve: ## Start Symfony local web server
	symfony serve -d

worker: ## Consume async messages (document text extraction)
	php bin/console messenger:consume async -vv

test-ai: ## Verify Meta Muse Spark AI connectivity and cache telemetry
	php bin/console app:test-ai

eval: ## Run the 24-scenario RAG benchmark suite (Precision & Recall)
	php bin/console app:eval-benchmark
