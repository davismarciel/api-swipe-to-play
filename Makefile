# STP API Makefile

.PHONY: setup start stop restart logs shell test migrate seed

# First time setup (runs build, install, keys, migrate, seed)
setup:
	docker-compose build
	docker-compose up -d
	@echo "Waiting for containers to start..."
	@sleep 10
	docker exec -it stp_api composer install
	docker exec -it stp_api php artisan key:generate
	docker exec -it stp_api php artisan jwt:secret
	docker exec -it stp_api php artisan migrate --force
	docker exec -it stp_api php artisan db:seed --force
	@echo "✅ Setup complete! Access at http://localhost:8000"

# Regular start (just up)
start:
	docker-compose up -d
	@echo "✅ Application started!"

# Stop containers
stop:
	docker-compose down

# Restart containers
restart: stop start

# View logs
logs:
	docker-compose logs -f

# Enter api shell
shell:
	docker exec -it stp_api sh

# Run tests
test:
	docker exec -it stp_api php artisan test

# Run migrations
migrate:
	docker exec -it stp_api php artisan migrate

# Run seeds
seed:
	docker exec -it stp_api php artisan db:seed

