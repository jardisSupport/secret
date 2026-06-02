<---code generation->: ## -----------------------------------------------------------------------
generate: ## Generate domain code from simulations
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli php simulate-domain-builder.php
.PHONY: generate
