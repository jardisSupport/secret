<---qa tools----->: ## -----------------------------------------------------------------------
phpstan: ## Run PHPStan analysis
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli vendor/bin/phpstan analyse /app/src -c phpstan.neon
.PHONY: phpstan

phpcs: ## Run coding standards
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli vendor/bin/phpcs /app/src
.PHONY: phpcs
