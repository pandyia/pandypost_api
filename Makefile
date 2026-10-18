include .env
exec = docker exec -it $(CONTAINER_NAME)

.DEFAULT_GOAL := help

help:
	@echo "PANDYPOST - comandos do make (uso: make <comando>)"
	@echo ""
	@echo "SUBIR E DERRUBAR"
	@echo "  up               Sobe os containers"
	@echo "  down             Derruba os containers (dados são mantidos)"
	@echo "  build            Reconstrói as imagens"
	@echo "  buildup          Build + composer install + sobe tudo (primeira vez)"
	@echo "  restart          Reinicia todos os containers"
	@echo ""
	@echo "RECARREGAR (escolha pelo que você mudou)"
	@echo "  reload-code      Mudou código PHP: recarrega Octane e Horizon"
	@echo "  reload-env       Mudou .env ou docker compose.yml: recria php, horizon, reverb e nginx"
	@echo "  reload-restart   Container travado: reinicia sem recriar (não relê o .env)"
	@echo "  reload-all       Recria TODOS os serviços (não apaga dados)"
	@echo "  optimize         Limpa os caches do Laravel"
	@echo ""
	@echo "BANCO DE DADOS"
	@echo "  migrate          Roda as migrations pendentes"
	@echo "  seed             Roda os seeders"
	@echo "  rollback         Desfaz o último lote de migrations (cuidado)"
	@echo "  ms               migrate:fresh --seed: APAGA tudo e recria (cuidado)"
	@echo "  wipe             Apaga todas as tabelas (cuidado)"
	@echo ""
	@echo "DIA A DIA"
	@echo "  test             Roda os testes (Pest)"
	@echo "  docs             Gera a documentação Scribe (/docs) e recarrega o Octane"
	@echo "  tinker           Abre o console do Laravel"
	@echo "  e                Abre um terminal dentro do container php"
	@echo "  packages         composer install no container"
	@echo ""
	@echo "FILAS E AGENDADOR"
	@echo "  horizon-status   Mostra se o Horizon está rodando"
	@echo "  horizon-pause / horizon-continue / horizon-terminate"
	@echo "  qw               queue:work    sw: schedule:work    sr: schedule:run"
	@echo ""
	@echo "LOGS"
	@echo "  logs-octane | logs-horizon | logs-reverb"
	@echo ""
	@echo "OUTROS"
	@echo "  restart-octane | restart-horizon | restart-reverb   Reinicia só um serviço"
	@echo "  test-s3          Testa se o bucket S3 está funcionando"
	@echo "  ngrok            Expõe a porta 9000 com ngrok"
	@echo "  prod-up | prod-down | prod-pull | prod-logs   Produção (cuidado)"

composer:
	exec composer install

npm:
	exec npm install

packages:
	$(exec) composer install

buildup:
	docker compose build && docker compose run --rm --no-deps --entrypoint "" php composer install && docker compose up -d

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

# Mudou código PHP: recarrega workers do Octane e reinicia o Horizon (o supervisor o sobe de novo)
reload-code:
	$(exec) php artisan octane:reload
	$(exec) php artisan horizon:terminate

# Mudou .env ou docker compose.yml: recria php, horizon, reverb e nginx (relê o env_file)
reload-env:
	docker compose up -d --force-recreate php horizon reverb nginx

# Container travado ou estranho: reinicia sem recriar (não relê .env)
reload-restart:
	docker compose restart php horizon reverb nginx

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
	docker compose up -d --force-recreate

ngrok:
	ngrok http 9000

test:
	$(exec) php artisan test

# Gera a documentação Scribe (/docs) e recarrega o Octane para servir a versão nova
docs:
	$(exec) php artisan scribe:generate --no-interaction
	$(exec) php artisan octane:reload

# PROD
setup-ssl:
	CF_API_TOKEN=$(CF_API_TOKEN) CF_HOSTNAMES=$(CF_HOSTNAMES) ./docker/scripts/setup-origin-cert.sh

prod-up:
	docker compose -f docker compose.prod.yml up -d

prod-down:
	docker compose -f docker compose.prod.yml down

prod-pull:
	docker compose -f docker compose.prod.yml pull

prod-logs:
	docker compose -f docker compose.prod.yml logs -f

# BUCKET (play.min.io) é redundante, ja está configurado no docker compose.
setup-bucket:
	docker run --rm minio/mc alias set play https://play.min.io Q3AM3UQ867SPQQA43P2F zuf+tfteSlswRu7BJ86wekitnifILbZam1KYY3TG
	docker run --rm minio/mc mb play/pandypost-dev --ignore-existing
	docker run --rm minio/mc anonymous set download play/pandypost-dev

# teste para ver se está funcionando.
test-s3:
	docker exec pandypost-php php artisan tinker --execute="Illuminate\Support\Facades\Storage::disk('s3')->put('teste-bucket.txt', 'Bucket funcionando perfeitamente!'); echo 'URL: ' . Illuminate\Support\Facades\Storage::disk('s3')->url('teste-bucket.txt') . PHP_EOL;"
