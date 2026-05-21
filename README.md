# aritmetic-game

API Symfony 7.4 (PHP 8.3) para um jogo de aritmética em tempo real: participantes respondem operações de multiplicação dentro de uma janela de tempo, perdem vidas quando erram ou estouram o tempo, e o backend coordena rodadas e ranking via REST + eventos realtime (Pusher). Estado de cada partida é persistido (PostgreSQL + Doctrine), o fechamento de rodada por timeout roda em background via Symfony Messenger, e a autenticação usa JWT (access + refresh).

---

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

### 6)Rodar migrations

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

---

## Rodar o setup de produção localmente

Use este modo para testar com a imagem do frontend publicada no GHCR e o nginx proxy, sem precisar do `ng serve`.

### 1) Criar o `.env.prod`

Copie o exemplo e preencha os valores:

```bash
cp .env.prod.example .env.prod
```

> Já existe um `.env.prod` com valores locais prontos se você só quiser testar na máquina.

### 2) Subir os containers

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml -f docker-compose.local.yml up -d
```

O app ficará disponível em `http://localhost:8090`.

> `docker-compose.local.yml` adiciona o serviço `db` (ausente no compose de prod, que assume banco externo) e respeita `PROXY_PORT` definida no `.env.prod` para evitar conflito com a porta 80 da máquina.

Migrations rodam automaticamente no startup do container `php`.

### 3) Derrubar

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml -f docker-compose.local.yml down
```

---

## Sobre a aplicação

### Ideia geral

Cada jogador entra como um **Guest** (apelido + avatar) e participa de uma sessão de jogo. Em cada rodada, o motor sorteia uma operação `multiplier × target`:

- `multiplier`: 1–10
- `target`: 1–30 (define a dificuldade — `easy` 1–10, `medium` 11–20, `hard` 21–30)

O participante tem 3 vidas e perde uma quando erra, responde fora da janela ou não responde antes do fechamento automático. A sessão termina quando sobra um vencedor, quando todos perdem as vidas, ou ao atingir o número total de rodadas.

### Dois modos de partida

| Modo | Entidade raiz | Multiplayer realtime | Ferramentas | Status |
|---|---|---|---|---|
| **Game Session** | `GameSession` | Sim (Pusher) | Não | Estável |
| **Battle Room** | `BattleRoom` + `BattlePlayer` | Sim, com matchmaking por código de sala | Sim (`hint`, `eliminate`, `skip`) — 1 uso por jogador | Em evolução |

### Arquitetura em uma frase

`Controller → Service → Doctrine` para o caminho síncrono; `EventDispatcher → EventSubscriber → PusherPublisher` para os eventos realtime; `Messenger (transport: async, doctrine)` para fechar rodadas por timeout.

### Stack

- PHP 8.3 / Symfony 7.4
- Doctrine ORM 3 + PostgreSQL 16
- Lexik JWT + gesdinet refresh token
- Pusher (canais privados)
- Symfony Messenger (Doctrine transport)
- Pest 4 + Mockery (testes)

---

## Funcionalidades

- **Auth**: registro, login JSON, `/me`, refresh token rotativo.
- **Guests**: cadastro de jogador com nickname e avatar; perfil de fraqueza (`weaknessProfile`) usado pelo algoritmo de seleção de questões.
- **Avatares**: catálogo público (`/files/avatars`) + CRUD admin (`/api/admin/files`) com upload.
- **Game Session (single owner)**: criar/listar/atualizar/excluir, buscar por código curto, iniciar, responder, avançar rodada, finalizar. Criação bloqueada com 409 se já existir sessão ativa do mesmo usuário. Listagem enriquecida com `participantsCount`, `participants` e `createdAt`.
- **Battle Room (multiplayer)**: criar sala com código, entrar, marcar `ready`, iniciar partida, responder, avançar rodada, usar ferramenta, chat por sala.
- **Algoritmo de questões**: 70% das perguntas vêm de operações em que os jogadores erram mais (peso por taxa de erro × tempo médio); 30% aleatórias para variedade.
- **Realtime**: eventos publicados em canais Pusher por sessão, por usuário e por sala — frontend reidrata por `GET` em caso de reconexão.
- **Timeout automático**: ao iniciar rodada, o backend agenda mensagem atrasada no Messenger; ao expirar, fecha a rodada, penaliza quem não respondeu e abre a próxima.

---

## Endpoints

Prefixo: a maioria sob `/api`. Tudo exige `Authorization: Bearer <jwt>`, exceto `register`, `login` e `token/refresh`.

### Auth

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/register` | Cria usuário (`username`, `email`, `password`) |
| POST | `/api/login` | JSON login → `token` + `refresh_token` |
| POST | `/api/token/refresh` | Renova access token |
| GET | `/api/me` | Dados do usuário autenticado |

### Guests e arquivos

| Método | Rota | Descrição |
|---|---|---|
| POST | `/guests` | Cria guest (nickname + avatar) |
| GET | `/files/avatars` | Lista avatares disponíveis |
| GET | `/api/admin/files` | (admin) lista arquivos |
| POST | `/api/admin/files` | (admin) upload de avatar |
| GET | `/api/admin/files/{id}` | (admin) detalhe |
| POST/PATCH | `/api/admin/files/{id}` | (admin) substituir/editar |
| DELETE | `/api/admin/files/{id}` | (admin) remover |

### Game Session (motor single-owner)

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/game-sessions` | Cria sessão |
| GET | `/api/game-sessions` | Lista sessões do dono |
| GET | `/api/game-sessions/{id}` | Detalhe |
| GET | `/api/game-sessions/code/{code}` | Buscar por código curto |
| PUT/PATCH | `/api/game-sessions/{id}` | Atualizar |
| DELETE | `/api/game-sessions/{id}` | Remover |
| POST | `/api/game-sessions/{id}/start` | Inicia rodada 1 |
| POST | `/api/game-sessions/{id}/answer` | Submete resposta da rodada atual |
| POST | `/api/game-sessions/{id}/next-round` | Avança rodada manualmente |
| POST | `/api/game-sessions/{id}/finish` | Finaliza sessão |

#### Participantes da sessão

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/game-sessions/{sessionId}/guests` | Lista participantes |
| POST | `/api/game-sessions/{sessionId}/guests` | Adiciona participante |
| GET | `/api/game-sessions/{sessionId}/guests/{id}` | Detalhe |
| PUT/PATCH | `/api/game-sessions/{sessionId}/guests/{id}` | Atualiza |
| DELETE | `/api/game-sessions/{sessionId}/guests/{id}` | Remove |

### Battle Room (multiplayer com ferramentas)

| Método | Rota | Descrição |
|---|---|---|
| POST | `/battle/rooms` | Cria sala (gera código) |
| GET | `/battle/rooms/{id}` | Detalhe da sala |
| POST | `/battle/rooms/{id}/join` | Entra na sala |
| POST | `/battle/rooms/{id}/ready` | Marca/desmarca pronto |
| POST | `/battle/rooms/{id}/start` | Dono inicia a partida |
| POST | `/battle/rooms/{id}/answer` | Responde rodada atual |
| POST | `/battle/rooms/{id}/next-round` | Avança rodada |
| POST | `/battle/rooms/{id}/tool` | Usa ferramenta (`hint` \| `eliminate` \| `skip`) |
| POST | `/battle/rooms/{id}/chat` | Envia mensagem |
| GET | `/battle/rooms/{id}/chat` | Lista mensagens |

---

## Eventos realtime (Pusher)

**Canais**

- `private-game-session-{sessionId}` — eventos da partida.
- `private-user-{userId}` — eventos direcionados ao dono da sessão.
- `private-battle-room-{roomId}` — eventos de sala (incluindo chat).

**Eventos publicados**

`game.session.created` · `game.session.started` · `game.question.generated` · `game.answer.received` · `game.participant.updated` · `game.round.started` · `game.round.finished` · `game.participant.eliminated` · `game.participant.kicked` · `game.session.finished` · `battle.room.*` · `chat.message.sent`

Payload base contém `schemaVersion`, `occurredAt`, `sessionId` (ou `roomId`) + dados específicos.

> Como os canais são `private-*`, o cliente Pusher precisa do auth endpoint do bundle (assinatura via JWT do usuário). Detalhes em `docs/pusher-requests-guide.md`.

---

## Testes

A suíte usa [Pest](https://pestphp.com) (sobre PHPUnit) com Mockery para mocks. Os testes ficam em `app/tests/Unit/`.

```bash
# Rodar toda a suíte (saída verbose)
docker compose run --rm php php vendor/bin/pest --testdox

# Modo padrão (compacto)
docker compose run --rm php php vendor/bin/pest

# Filtrar por arquivo ou nome
docker compose run --rm php php vendor/bin/pest tests/Unit/Service/ToolServiceTest.php
docker compose run --rm php php vendor/bin/pest --filter="generateQuestion"
```

> Se ao adicionar testes/dependências de dev o `vendor/` ficar com permissões de root, ajuste com:
> `docker compose run --rm --user root --entrypoint sh php -c "chown -R 1000:1000 /var/www/html/vendor /var/www/html/composer.* /var/www/html/symfony.lock"`

---

## Documentação adicional

- `docs/game-session-flow.md` — fluxo completo do motor de sessão e como o frontend deve consumir.
- `docs/pusher-requests-guide.md` — autenticação de canais privados e exemplos.
- `docs/integration-guide.md` — passo a passo de integração ponta a ponta.
- `docs/swagger.yaml` — especificação OpenAPI dos endpoints.
- `docs/metricas.md` — métricas do projeto (tamanho, esforço, prazo, produtividade, qualidade).
- `docs/bruno/` — coleção [Bruno](https://www.usebruno.com) com requests prontos.

---

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
