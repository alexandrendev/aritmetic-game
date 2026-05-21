#!/bin/sh
set -e

DC="docker compose --env-file .env.prod -f docker-compose.prod.yml"

# ── Pré-requisito ────────────────────────────────────────────────────────────
if [ ! -f .env.prod ]; then
    echo "Erro: .env.prod não encontrado."
    echo "Crie o arquivo com base no .env.prod.example antes de continuar."
    exit 1
fi

# ── Build e start ────────────────────────────────────────────────────────────
echo "==> Build das imagens..."
$DC build

echo "==> Subindo containers..."
$DC up -d

echo "==> Aguardando PHP estar pronto..."
until $DC exec -T php php -r "echo 'ok';" 2>/dev/null | grep -q ok; do
    sleep 2
done

# ── JWT ──────────────────────────────────────────────────────────────────────
echo "==> Gerando chaves JWT..."
$DC run --rm php php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction

# ── Seed ─────────────────────────────────────────────────────────────────────
echo "==> Fazendo seed dos avatares..."
$DC run --rm php php bin/console app:seed:avatars

echo ""
echo "Setup concluído."
