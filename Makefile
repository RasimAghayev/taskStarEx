SHELL := /bin/sh
COMPOSE := docker compose

.PHONY: help env build test verify up down run logs shell shell-fe lint format lint-fe stan migrate seed key security clean

help:
	@echo "taskStarEx — available targets:"
	@echo "  make env       copy .env.example -> .env (root, src/be, src/fe) if missing"
	@echo "  make build     build all compose images"
	@echo "  make test      run the test stage for both src/be and src/fe (build fails if tests fail)"
	@echo "  make verify    test + build (the Docker-first gate)"
	@echo "  make up        verify, then start the stack (make up depends on verify)"
	@echo "  make down      stop the stack"
	@echo "  make run       up + follow logs"
	@echo "  make logs      follow logs of the running stack"
	@echo "  make shell     shell into a backend dev container (full source + composer + phpunit + pint)"
	@echo "  make shell-fe  shell into a frontend dev container"
	@echo "  make lint      backend: laravel/pint --test (check only, no changes)"
	@echo "  make format    backend: laravel/pint (applies fixes)"
	@echo "  make lint-fe   frontend: eslint (@nuxt/eslint flat config, check only)"
	@echo "  make stan      backend: larastan/phpstan static analysis"
	@echo "  make migrate   run database migrations against the pgsql service"
	@echo "  make seed      run database seeders against the pgsql service"
	@echo "  make key       print a fresh APP_KEY to paste into src/be/.env"
	@echo "  make security  composer audit + npm audit (best-effort, never blocks)"
	@echo "  make clean     stop the stack and remove volumes (DB data, storage) — needs CONFIRM=1"

env:
	@test -f .env || cp .env.example .env
	@test -f src/be/.env || cp src/be/.env.example src/be/.env
	@test -f src/fe/.env || cp src/fe/.env.example src/fe/.env

build:
	$(COMPOSE) build

# The test stage is wired directly into each Dockerfile: `docker build
# --target test` runs the real test suite as a build-time RUN step, so the
# build itself fails (non-zero exit) if any test fails. See docs/BASELINE.md
# for the currently-documented pre-existing failure (P0-1 manifest/lockfile
# drift, Phase 6's job) — that failure is expected and is not introduced by
# this Makefile.
test:
	docker build --target test -f php.dockerfile -t teststarex-be-test .
	docker build --target test -f node.dockerfile -t teststarex-fe-test .

verify: test
	$(COMPOSE) build

up: verify env
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

run: up
	$(COMPOSE) logs -f

logs:
	$(COMPOSE) logs -f

shell: env
	$(COMPOSE) --profile tools run --rm php-dev sh

shell-fe: env
	$(COMPOSE) --profile tools run --rm fe-dev sh

lint: env
	$(COMPOSE) --profile tools run --rm php-dev ./vendor/bin/pint --test

format: env
	$(COMPOSE) --profile tools run --rm php-dev ./vendor/bin/pint

lint-fe: env
	$(COMPOSE) --profile tools run --rm fe-dev sh -c "npx nuxt prepare && npm run lint"

stan: env
	$(COMPOSE) --profile tools run --rm php-dev ./vendor/bin/phpstan analyse --memory-limit=512M

migrate: env
	$(COMPOSE) --profile tools run --rm php-dev php artisan migrate --force

seed: env
	$(COMPOSE) --profile tools run --rm php-dev php artisan db:seed --force

key: env
	$(COMPOSE) --profile tools run --rm php-dev php artisan key:generate --show

security: env
	-$(COMPOSE) --profile tools run --rm php-dev composer audit
	-$(COMPOSE) --profile tools run --rm fe-dev npm audit

clean:
	@if [ "$(CONFIRM)" != "1" ]; then \
		echo "Refusing: this deletes the pgsql_data and be_storage volumes."; \
		echo "Re-run as: make clean CONFIRM=1"; \
		exit 1; \
	fi
	$(COMPOSE) down -v
