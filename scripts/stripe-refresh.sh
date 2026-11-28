#!/usr/bin/env bash
# Atualiza o STRIPE_WEBHOOK_SECRET do .env (SÓ modo teste e SÓ ambiente local).
# Lê o STRIPE_SECRET que já está no .env, descobre o secret do webhook do Stripe CLI,
# grava no .env e recarrega os containers.
#
# Uso: make stripe-refresh
set -euo pipefail
cd "$(dirname "$0")/.."

ENV_FILE=.env

[ -f "$ENV_FILE" ] || { echo "ERRO: .env não encontrado."; exit 1; }

get_env() {
  grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d ' \r"'"'"
}

app_env=$(get_env APP_ENV)
if [ "$app_env" != "local" ]; then
  echo "ERRO: APP_ENV=$app_env. Este comando só roda em ambiente local."
  exit 1
fi

KEY=$(get_env STRIPE_SECRET)
case "$KEY" in
  sk_test_*|rk_test_*) ;;
  "") echo "ERRO: STRIPE_SECRET está vazio no .env."; exit 1 ;;
  *) echo "ERRO: STRIPE_SECRET não é uma chave de teste (sk_test_). Chave live não é aceita aqui."; exit 1 ;;
esac

echo "→ Descobrindo o secret do webhook do Stripe CLI..."
secret=$(docker compose run --rm --no-deps stripe listen --api-key "$KEY" --print-secret 2>&1 | grep -o 'whsec_[A-Za-z0-9]*' | head -1 || true)
if [ -z "$secret" ]; then
  echo "ERRO: não consegui obter o secret do webhook. A STRIPE_SECRET do .env está correta e ativa?"
  exit 1
fi

SECRET="$secret" python3 -I - "$ENV_FILE" <<'PY'
import os, re, sys
path = sys.argv[1]
line = "STRIPE_WEBHOOK_SECRET=" + os.environ["SECRET"]
text = open(path).read()
if re.search(r"(?m)^STRIPE_WEBHOOK_SECRET=", text):
    text = re.sub(r"(?m)^STRIPE_WEBHOOK_SECRET=.*$", lambda _: line, text)
else:
    text = text.rstrip("\n") + "\n" + line + "\n"
open(path, "w").write(text)
PY
echo "→ .env atualizado (STRIPE_WEBHOOK_SECRET)."

echo "→ Recriando o container do Stripe CLI e recarregando a API..."
docker compose up -d --force-recreate stripe >/dev/null
docker exec pandypost-php php artisan config:clear >/dev/null
docker exec pandypost-php php artisan octane:reload >/dev/null
echo "OK: webhook local pronto."
