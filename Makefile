# xismarket — common Docker/Laravel commands
# Usage: make <target>   (requires Docker Desktop running)

.PHONY: up down build restart logs sh artisan composer test migrate fresh seed npm

up:           ## Start the stack
	docker compose up -d

down:         ## Stop the stack
	docker compose down

build:        ## Rebuild the app image
	docker compose build --no-cache

restart:      ## Restart the stack
	docker compose restart

logs:         ## Tail app + nginx logs
	docker compose logs -f app nginx

sh:           ## Shell into the app container
	docker compose exec app bash

artisan:      ## Run an artisan command, e.g. make artisan c="migrate"
	docker compose exec app php artisan $(c)

composer:     ## Run composer, e.g. make composer c="require spatie/laravel-permission"
	docker compose exec app composer $(c)

test:         ## Run the test suite
	docker compose exec app php artisan test

migrate:      ## Run migrations
	docker compose exec app php artisan migrate

fresh:        ## Drop + re-migrate + seed
	docker compose exec app php artisan migrate:fresh --seed

seed:         ## Run seeders
	docker compose exec app php artisan db:seed

npm:          ## Run npm in the node container, e.g. make npm c="run dev"
	docker compose run --rm node npm $(c)
