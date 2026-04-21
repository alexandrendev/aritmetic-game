# Guia rápido de requests (GameSession + Pusher)

Este guia mostra como:

1. criar usuário e obter JWT,
2. criar guest,
3. criar sessão e iniciar jogo,
4. validar eventos no Pusher.

---

## 1) Pré-requisitos

No `.env` da API (`app/.env`), configure:

```env
PUSHER_ENABLED=1
PUSHER_APP_ID=SEU_APP_ID
PUSHER_APP_KEY=SEU_APP_KEY
PUSHER_APP_SECRET=SEU_APP_SECRET
PUSHER_APP_CLUSTER=SEU_CLUSTER
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=1
```

Suba a stack e prepare o transport do Messenger:

```bash
docker compose up -d --build
docker compose run --rm php php bin/console messenger:setup-transports --no-interaction
```

Deixe o worker rodando (terminal separado), porque ele fecha rodada por timeout:

```bash
docker compose run --rm php php bin/console messenger:consume async -vv
```

---

## 2) Variáveis para os requests

```bash
BASE_URL="http://localhost:8080"
```

---

## 3) Criar usuário + login (JWT)

### 3.1 Registrar

```bash
curl -s -X POST "$BASE_URL/api/register" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "player01",
    "email": "player01@example.com",
    "password": "123456"
  }'
```

### 3.2 Login

> `identifier` pode ser username ou email.

```bash
curl -s -X POST "$BASE_URL/api/login" \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "player01",
    "password": "123456"
  }'
```

Copie o `token` retornado e exporte:

```bash
TOKEN="COLE_SEU_TOKEN_AQUI"
```

### 3.3 Descobrir `userId`

```bash
curl -s "$BASE_URL/api/me" \
  -H "Authorization: Bearer $TOKEN"
```

Guarde o `id` retornado como `USER_ID`.

---

## 4) Criar guest

### 4.1 Listar avatares

```bash
curl -s "$BASE_URL/files/avatars"
```

Escolha um `id` de avatar e defina:

```bash
AVATAR_ID=1
```

### 4.2 Criar guest

```bash
curl -s -X POST "$BASE_URL/guests" \
  -H "Content-Type: application/json" \
  -d "{
    \"nickname\": \"Guest One\",
    \"avatarId\": $AVATAR_ID
  }"
```

Guarde o `id` retornado como `GUEST_ID`.

---

## 5) Criar GameSession e adicionar guest

### 5.1 Criar sessão

```bash
curl -s -X POST "$BASE_URL/api/game-sessions" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'
```

Guarde o `id` retornado como `SESSION_ID`.

### 5.2 Adicionar participante

```bash
curl -s -X POST "$BASE_URL/api/game-sessions/$SESSION_ID/guests" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"guestId\": $GUEST_ID
  }"
```

Guarde o `id` retornado como `SESSION_GUEST_ID`.

---

## 6) Iniciar jogo (com janela de resposta)

```bash
curl -s -X POST "$BASE_URL/api/game-sessions/$SESSION_ID/start" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "target": 12,
    "totalRounds": 5,
    "responseWindowMs": 10000
  }'
```

Eventos esperados no start:

- `game.session.started`
- `game.round.started`
- `game.question.generated`

---

## 7) Responder pergunta

```bash
curl -s -X POST "$BASE_URL/api/game-sessions/$SESSION_ID/answer" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"gameSessionGuestId\": $SESSION_GUEST_ID,
    \"answer\": 24,
    \"timeMs\": 2500
  }"
```

Eventos possíveis:

- `game.answer.received`
- `game.participant.updated`
- `game.participant.eliminated`
- `game.round.finished`
- `game.round.started` (se abriu próxima automaticamente)
- `game.question.generated` (próxima pergunta)
- `game.session.finished` (se terminou)

---

## 8) Teste de timeout/não resposta

Depois do `start`, **não envie `answer`** e espere passar `responseWindowMs`.

O backend (via Messenger) fecha a rodada automaticamente e aplica penalidade:

- sem resposta => perde 1 vida
- resposta fora da janela => perde 1 vida
- resposta errada => perde 1 vida

---

## 9) Onde ver os eventos

Use o **Pusher Dashboard > Debug Console** do app configurado.

Canais usados:

- `private-game-session-{SESSION_ID}`
- `private-user-{USER_ID}`

Eventos publicados:

- `game.session.created`
- `game.session.started`
- `game.question.generated`
- `game.answer.received`
- `game.participant.updated`
- `game.round.finished`
- `game.round.started`
- `game.participant.eliminated`
- `game.session.finished`

---

## 10) Observações

- O endpoint `POST /api/game-sessions/{id}/next-round` está deprecado (retorna `410`), porque agora o backend avança automaticamente.
- Para assinatura de canais `private-*` diretamente no frontend, você ainda precisa do endpoint de auth do Pusher no backend.
