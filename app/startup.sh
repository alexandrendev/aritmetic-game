#!/bin/bash

# Entrar no diretório da aplicação
cd "$(dirname "$0")"

composer install

# Gerar chaves JWT
echo "Gerando chaves JWT..."
php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction

# Configurar transportes do Messenger
echo "Configurando transportes do Messenger..."
php bin/console messenger:setup-transports --no-interaction

# Rodar migrations
echo "Executando migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

# Seed de avatars
echo "Populando avatars..."
php bin/console app:seed:avatars --no-interaction

# Iniciar o worker em background
echo "Iniciando worker do Messenger..."
php bin/console messenger:consume async -vv &

# Iniciar o servidor web (Apache)
echo "Iniciando Apache..."
php -S 0.0.0.0:80 -t public
