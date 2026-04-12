include .env
exec = docker exec -it $(CONTAINER_NAME)
composer:
	 exec composer install

npm:
	 exec npm install

packages:
	 exec composer install && exec npm install

buildup:
	 docker compose up -d --build && docker compose exec php composer install && docker compose exec php npm install

down:
	 docker compose down

build:
	 docker compose build

up:
	 docker compose up -d

e:
	 $(exec) bash

optimize: 
	$(exec) php artisan optimize:clear

rollback:
	$(exec) php artisan migrate:rollback

migrate:
	$(exec) php artisan migrate

seed:
	$(exec) php artisan db:seed

ms:
	$(exec) php artisan migrate:fresh --seed
wipe:
	$(exec) php artisan db:wipe

tinker:
	$(exec) php artisan tinker


# JOB ETC

# Schedule run (one time)
sr:
	$(exec) php artisan schedule:run

# Schedule work (daemon)
sw:
	$(exec) php artisan schedule:work

qw:
	$(exec) php artisan queue:work

# HORIZON (gerenciador de filas Redis)
horizon:
	$(exec) php artisan horizon

horizon-pause:
	$(exec) php artisan horizon:pause

horizon-continue:
	$(exec) php artisan horizon:continue

horizon-terminate:
	$(exec) php artisan horizon:terminate

horizon-status:
	$(exec) php artisan horizon:status

ngrok:
	sudo ngrok http 9000

test:
	$(exec) php artisan test

# PROD
setup-ssl:
	CF_API_TOKEN=$(CF_API_TOKEN) CF_HOSTNAMES=$(CF_HOSTNAMES) ./docker/scripts/setup-origin-cert.sh

prod-up:
	docker compose -f docker-compose.prod.yml up -d

prod-down:
	docker compose -f docker-compose.prod.yml down

prod-pull:
	docker compose -f docker-compose.prod.yml pull

prod-logs:
	docker compose -f docker-compose.prod.yml logs -f