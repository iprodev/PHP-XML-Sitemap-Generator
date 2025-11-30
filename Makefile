.PHONY: help install test lint fix analyze clean docker-build docker-up docker-down

# Colors
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m # No Color

help: ## Show this help message
	@echo "$(BLUE)PHP XML Sitemap Generator Pro$(NC)"
	@echo "$(BLUE)================================$(NC)"
	@echo ""
	@echo "Available commands:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-20s$(NC) %s\n", $$1, $$2}'

install: ## Install dependencies
	@echo "$(BLUE)Installing dependencies...$(NC)"
	composer install
	@echo "$(GREEN)✓ Dependencies installed$(NC)"

install-dev: ## Install development dependencies
	@echo "$(BLUE)Installing development dependencies...$(NC)"
	composer install --dev
	@echo "$(GREEN)✓ Development dependencies installed$(NC)"

update: ## Update dependencies
	@echo "$(BLUE)Updating dependencies...$(NC)"
	composer update
	@echo "$(GREEN)✓ Dependencies updated$(NC)"

test: ## Run tests
	@echo "$(BLUE)Running tests...$(NC)"
	composer test
	@echo "$(GREEN)✓ Tests passed$(NC)"

test-coverage: ## Run tests with coverage
	@echo "$(BLUE)Running tests with coverage...$(NC)"
	composer test-coverage
	@echo "$(GREEN)✓ Coverage report generated$(NC)"

lint: ## Check code style
	@echo "$(BLUE)Checking code style...$(NC)"
	composer lint
	@echo "$(GREEN)✓ Code style is correct$(NC)"

fix: ## Fix code style
	@echo "$(BLUE)Fixing code style...$(NC)"
	composer phpcbf
	@echo "$(GREEN)✓ Code style fixed$(NC)"

analyze: ## Run static analysis
	@echo "$(BLUE)Running static analysis...$(NC)"
	composer analyze
	@echo "$(GREEN)✓ Static analysis complete$(NC)"

check: ## Run all checks (lint + test)
	@echo "$(BLUE)Running all checks...$(NC)"
	composer check
	@echo "$(GREEN)✓ All checks passed$(NC)"

clean: ## Clean cache and output directories
	@echo "$(BLUE)Cleaning...$(NC)"
	rm -rf cache/* output/* logs/* checkpoint.json
	@echo "$(GREEN)✓ Cleaned$(NC)"

clean-vendor: ## Remove vendor directory
	@echo "$(BLUE)Removing vendor directory...$(NC)"
	rm -rf vendor/
	@echo "$(GREEN)✓ Vendor removed$(NC)"

docker-build: ## Build Docker image
	@echo "$(BLUE)Building Docker image...$(NC)"
	docker build -t sitemap-generator-pro .
	@echo "$(GREEN)✓ Docker image built$(NC)"

docker-up: ## Start Docker services
	@echo "$(BLUE)Starting Docker services...$(NC)"
	docker-compose up -d
	@echo "$(GREEN)✓ Docker services started$(NC)"

docker-down: ## Stop Docker services
	@echo "$(BLUE)Stopping Docker services...$(NC)"
	docker-compose down
	@echo "$(GREEN)✓ Docker services stopped$(NC)"

docker-logs: ## Show Docker logs
	docker-compose logs -f

docker-shell: ## Open shell in app container
	docker-compose exec app sh

crawl: ## Run a basic crawl (URL required)
	@if [ -z "$(URL)" ]; then \
		echo "$(RED)Error: URL is required. Usage: make crawl URL=https://example.com$(NC)"; \
		exit 1; \
	fi
	@echo "$(BLUE)Starting crawl for $(URL)...$(NC)"
	php bin/sitemap --url=$(URL) --verbose

crawl-full: ## Run full-featured crawl (URL required)
	@if [ -z "$(URL)" ]; then \
		echo "$(RED)Error: URL is required. Usage: make crawl-full URL=https://example.com$(NC)"; \
		exit 1; \
	fi
	@echo "$(BLUE)Starting full-featured crawl for $(URL)...$(NC)"
	php bin/sitemap \
		--url=$(URL) \
		--cache-enabled \
		--db-enabled \
		--detect-changes \
		--seo-analysis \
		--verbose

init: ## Initialize project (install + setup)
	@echo "$(BLUE)Initializing project...$(NC)"
	@make install
	@mkdir -p output cache logs
	@cp .env.example .env 2>/dev/null || true
	@echo "$(GREEN)✓ Project initialized$(NC)"
	@echo "$(YELLOW)Don't forget to configure .env file$(NC)"

setup-cron: ## Setup cron job for scheduler
	@echo "$(BLUE)Setting up cron job...$(NC)"
	@echo "* * * * * cd $(PWD) && php bin/scheduler >> logs/scheduler.log 2>&1" | crontab -
	@echo "$(GREEN)✓ Cron job added$(NC)"

remove-cron: ## Remove cron job
	@echo "$(BLUE)Removing cron job...$(NC)"
	@crontab -l | grep -v "bin/scheduler" | crontab -
	@echo "$(GREEN)✓ Cron job removed$(NC)"

benchmark: ## Run benchmark test
	@echo "$(BLUE)Running benchmark...$(NC)"
	@time php bin/sitemap --url=https://example.com --max-pages=100 --verbose

db-create: ## Create database tables
	@echo "$(BLUE)Creating database tables...$(NC)"
	php -r "require 'vendor/autoload.php'; \
		\$$db = new IProDev\Sitemap\Database\Database('sqlite:./sitemap.db'); \
		\$$db->createTables(); \
		echo 'Tables created\n';"
	@echo "$(GREEN)✓ Database tables created$(NC)"

cache-clear: ## Clear cache
	@echo "$(BLUE)Clearing cache...$(NC)"
	rm -rf cache/*
	@echo "$(GREEN)✓ Cache cleared$(NC)"

version: ## Show version information
	@echo "$(BLUE)PHP XML Sitemap Generator Pro$(NC)"
	@echo "Version: $(GREEN)2.0.0$(NC)"
	@php --version | head -n 1

stats: ## Show project statistics
	@echo "$(BLUE)Project Statistics$(NC)"
	@echo "$(BLUE)==================$(NC)"
	@echo "PHP Files: $(shell find src -name '*.php' | wc -l)"
	@echo "Test Files: $(shell find tests -name '*.php' | wc -l)"
	@echo "Total Lines: $(shell find src -name '*.php' -exec wc -l {} + | tail -n 1 | awk '{print $$1}')"
	@echo "Classes: $(shell grep -r "^class " src | wc -l)"

# Default target
.DEFAULT_GOAL := help
