# 🚀 PandyPost - Setup do Projeto (LOCALHOST)

## 📋 Pré-requisitos

- PHP instalado
- Composer
- SQLite
- Conta no Mailtrap (para envio de email). Configurar apenas username e password no env

---

# ⚙️ Instalação do Projeto

Execute os comandos na ordem:

```bash
# criar o banco SQLite
crie o arquivo:
database/database.sqlite

# instalar dependências
composer install --ignore-platform-reqs

# copiar variáveis de ambiente
cp .env.local .env

# gerar chave da aplicação
php artisan key:generate

# rodar migrations + seeds
php artisan migrate --seed

# iniciar servidor
php artisan serve

# para envio de email
php artisan queue:work

# limpar cache
php artisan cache:clear

# limpar configuração
php artisan config:clear

# reiniciar filas
php artisan queue:restart

# apagar todo o banco
php artisan db:wipe

# Documentação do Postman
https://documenter.getpostman.com/view/19909270/2sBXc8oi7R

# Rodando o projeto com Docker

make buildup
make packages

# Instalação do ngrok linux/wsl
ngrok:
	wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.zip
	unzip ngrok-v3-stable-linux-amd64.zip
	sudo mv ngrok /usr/local/bin/ngrok
	sudo chmod +x /usr/local/bin/ngrok

* make ngrok


# JOB ETC

php artisan horizon ou make horizon

# Schedule run (one time)
sr:
	$(exec) php artisan schedule:run

# Schedule work (daemon)
sw:
	$(exec) php artisan schedule:work

qw:
	$(exec) php artisan queue:work

ngrok:
	sudo ngrok http 9000



# Makefile

Este Makefile é responsável por automatizar tarefas comuns, como buildar a imagem do Docker, rodar testes e mais. Ele utiliza variáveis de ambiente definidas no arquivo `.env` para obter informações como o nome do container e a porta utilizada.

## Comandos

* `make`: Executa todos os comandos do Makefile.
* `make build`: Builda a imagem do Docker com o nome definido na variável `CONTAINER_NAME`.
* `make e`: Executa o comando `docker exec -it <nome-do-container> bash`, permitindo que você acesse o container como se estivesse dentro dele.
* `make optimize`: Executa o comando `php artisan optimize:clear`, removendo todas as informações de otimização do Laravel.
* `make rollback`: Executa o comando `php artisan migrate:rollback`, revertendo todas as migrações do banco de dados.
* `make migrate`: Executa o comando `php artisan migrate`, executando todas as migrações do banco de dados.
* `make seed`: Executa o comando `php artisan db:seed`, populando o banco de dados com dados de exemplo.
* `make ms`: Executa o comando `php artisan migrate:fresh --seed`, executando todas as migrações do banco de dados e populando o banco de dados com dados de exemplo.
* `make wipe`: Executa o comando `php artisan db:wipe`, removendo todas as informações do banco de dados.
* `make tinker`: Executa o comando `php artisan tinker`, populando o banco de dados com informações de exemplo para testes.
* `make crd`: Executa o comando `composer run dev`, executando todos os comandos do Composer.
* `make schedule`: Executa o comando `php artisan schedule:work`, executando todas as tarefas agendadas do Laravel.

```
