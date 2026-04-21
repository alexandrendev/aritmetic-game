# aritmetic-game

## Startup guide

### 1) Instalar dependências

```bash
docker compose run --rm composer install
```

### 2) Configurar variáveis de ambiente

Use o arquivo `app/.env` como base. Para customizações locais, crie `app/.env.local`.

Variáveis principais:

```env
APP_ENV=dev
APP_SECRET=troque_este_valor

DATABASE_URL=postgresql://root:root@db:5432/app?serverVersion=16&charset=utf8

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=defina_uma_passphrase_forte

PUSHER_ENABLED=1
PUSHER_APP_ID=seu_app_id
PUSHER_APP_KEY=seu_app_key
PUSHER_APP_SECRET=seu_app_secret
PUSHER_APP_CLUSTER=us2

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=1
```

Se não for testar realtime, pode deixar:

```env
PUSHER_ENABLED=0
```

### 3) Subir os containers

```bash
docker compose up -d --build
```

### 4) Gerar chaves JWT (obrigatório)

```bash
docker compose run --rm php php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction
```

### 5) Criar estrutura do transport assíncrono (Messenger)

```bash
docker compose run --rm php php bin/console messenger:setup-transports --no-interaction
```

### 6) (Opcional) Rodar migrations

```bash
docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction
```

### 7) Seed de avatares

```bash
docker compose run --rm php php bin/console app:seed:avatars
```

### 8) Worker do Messenger (necessário para timeout automático de rodada)

Rode em outro terminal:

```bash
docker compose run --rm php php bin/console messenger:consume async -vv
```

## Comandos úteis

### Symfony

```bash
docker compose run --rm php php bin/console <comando>
```

Exemplo:

```bash
docker compose run --rm php php bin/console cache:clear
```

### Composer

```bash
docker compose run --rm composer <comando>
```

Exemplo:

```bash
docker compose run --rm composer require pusher/pusher-php-server
```
