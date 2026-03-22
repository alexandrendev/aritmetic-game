# aritmetic-game

## Subir o projeto

```bash
docker compose up -d --build
```

## Rodar comandos do Symfony

```bash
docker compose exec php php bin/console <comando>
```

Exemplo:

```bash
docker compose exec php php bin/console cache:clear
```

## Rodar comandos do Composer

```bash
docker compose run --rm composer <comando>
```

Exemplos:

```bash
docker compose run --rm composer install
docker compose run --rm composer require symfony/orm-pack
```
