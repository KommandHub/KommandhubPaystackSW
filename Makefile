.PHONY: help up down build restart shell plugin-list test test-coverage cs cs-fix analyse fixture-load resync prepare

CONTAINER := shopware
PLUGIN_DIR := custom/static-plugins/KommandhubPaystackSW

# Plugins installed via composer
STATIC_PLUGINS := \
	kommandhub/foundation-sw:KommandhubFoundationSW \
	kommandhub/paystack-sw:KommandhubPaystackSW

# Only plugins that should be copied into custom/static-plugins
STATIC_COPY_PLUGINS := \
	kommandhub/foundation-sw:KommandhubFoundationSW

# Composer install list (includes all)
COMPOSER_PLUGINS := $(foreach p,$(STATIC_PLUGINS),$(word 1,$(subst :, ,$(p))))

define CHECK_READY
@if [ -z "$$(docker compose ps $(CONTAINER) --status running --quiet)" ]; then \
	echo "Error: Container '$(CONTAINER)' is not running. Please run 'make up' first."; \
	exit 1; \
fi; \
HEALTH=$$(docker compose ps $(CONTAINER) --format '{{.Health}}'); \
if [ "$$HEALTH" != "healthy" ] && [ -n "$$HEALTH" ]; then \
	echo "Waiting for container '$(CONTAINER)' to be healthy..."; \
	while [ "$$(docker compose ps $(CONTAINER) --format '{{.Health}}')" != "healthy" ]; do \
		printf "."; \
		sleep 1; \
	done; \
	echo " Ready!"; \
fi
endef

define EXEC
docker compose exec $(CONTAINER) bash -c "$(1)"
endef

define EXEC_IN_PLUGIN
$(call EXEC,cd $(PLUGIN_DIR) && $(1))
endef

help:
	@echo "Available commands:"
	@echo "  up                - Start the shopware container"
	@echo "  down              - Stop the shopware container"
	@echo "  build             - Rebuild the shopware container"
	@echo "  restart           - Restart the environment"
	@echo "  shell             - Open a shell session in the shopware container"
	@echo "  plugin-list       - List all plugins"
	@echo "  test              - Run phpunit tests"
	@echo "  test-coverage     - Run phpunit tests with coverage report"
	@echo "  cs                - Run php-cs-fixer checks"
	@echo "  cs-fix            - Run php-cs-fixer fix"
	@echo "  analyse           - Run phpstan analysis"
	@echo "  fixture-load      - Load fixtures"
	@echo "  resync            - Sync config directory into the root project"
	@echo "  prepare           - Full project preparation"

up:
	docker compose up -d --build
	$(MAKE) prepare

down:
	docker compose down -v

build:
	docker compose build

restart: down up

shell:
	$(CHECK_READY)
	docker compose exec $(CONTAINER) bash

plugin-list:
	$(CHECK_READY)
	docker compose exec $(CONTAINER) bin/console plugin:list

test:
	$(CHECK_READY)
	$(call EXEC_IN_PLUGIN,php ../../../bin/phpunit -c phpunit.dist.xml --testdox --color=always $${FILTER})

test-coverage:
	$(CHECK_READY)
	$(call EXEC_IN_PLUGIN,php ../../../bin/phpunit -c phpunit.dist.xml --coverage-text --color=always)

cs:
	$(CHECK_READY)
	$(call EXEC_IN_PLUGIN,./vendor/bin/php-cs-fixer fix --dry-run --diff)

cs-fix:
	$(CHECK_READY)
	$(call EXEC_IN_PLUGIN,./vendor/bin/php-cs-fixer fix)

analyse:
	$(CHECK_READY)
	$(call EXEC_IN_PLUGIN,./vendor/bin/phpstan analyse src -c phpstan.dist.neon --memory-limit=1G)

fixture-load:
	$(CHECK_READY)
	docker compose exec $(CONTAINER) bin/console fixture:load --no-interaction

resync:
	@echo "Resyncing config directory..."
	$(CHECK_READY)
	docker compose exec $(CONTAINER) cp -R $(PLUGIN_DIR)/tests/Setup/config/. config/

prepare:
	@echo ""
	@echo "Preparing the project..."
	$(CHECK_READY)
	
	docker compose exec $(CONTAINER) rm -rf custom/plugins/*

	@echo "Installing required plugins via composer..."
	docker compose exec $(CONTAINER) composer require $(COMPOSER_PLUGINS) --no-interaction

	@echo "Copying vendor plugins to static-plugins (excluding main plugin)..."
	@$(foreach plugin,$(STATIC_COPY_PLUGINS), \
		VENDOR_DIR=$(word 1,$(subst :, ,$(plugin))); \
		TARGET_DIR=$(word 2,$(subst :, ,$(plugin))); \
		echo "  $$VENDOR_DIR -> $$TARGET_DIR"; \
		docker compose exec $(CONTAINER) mkdir -p custom/static-plugins/$$TARGET_DIR; \
		docker compose exec $(CONTAINER) cp -R vendor/$$VENDOR_DIR/. custom/static-plugins/$$TARGET_DIR/; \
	)

	@echo "Installing plugin dependencies..."
	$(call EXEC_IN_PLUGIN,composer install --no-interaction)

	# $(MAKE) resync
