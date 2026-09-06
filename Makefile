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

restart:
	docker compose restart

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

# SCHEDULE & QUEUE
sr:
	$(exec) php artisan schedule:run

sw:
	$(exec) php artisan schedule:work

qw:
	$(exec) php artisan queue:work

# HORIZON
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

logs-octane:
	docker compose logs -f php

logs-reverb:
	docker compose logs -f reverb

logs-horizon:
	docker compose logs -f horizon

restart-reverb:
	docker compose restart reverb

restart-octane:
	docker compose restart php

restart-horizon:
	docker compose restart horizon

recreate:
	docker-compose up -d --force-recreate

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

# BUCKET (play.min.io) é redundante, ja está configurado no docker compose.
setup-bucket:
	docker run --rm minio/mc alias set play https://play.min.io Q3AM3UQ867SPQQA43P2F zuf+tfteSlswRu7BJ86wekitnifILbZam1KYY3TG
	docker run --rm minio/mc mb play/pandypost-dev --ignore-existing
	docker run --rm minio/mc anonymous set download play/pandypost-dev

# teste para ver se está funcionando.
test-s3:
	docker exec pandypost-php php artisan tinker --execute="Illuminate\Support\Facades\Storage::disk('s3')->put('teste-bucket.txt', 'Bucket funcionando perfeitamente!'); echo 'URL: ' . Illuminate\Support\Facades\Storage::disk('s3')->url('teste-bucket.txt') . PHP_EOL;"
