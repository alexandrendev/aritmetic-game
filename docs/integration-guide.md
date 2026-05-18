# Guia de Integração — Aritmetic Game

## Visão geral

O jogo é baseado em multiplicação. Todos os jogadores recebem a mesma questão por rodada e têm uma janela de tempo para responder. Quem errar perde uma vida; quem ficar sem vida é eliminado. A sessão termina quando um único jogador restar vivo ou o número máximo de rodadas for atingido.

O backend usa REST para comandos e **Pusher** para eventos em tempo real. O frontend não precisa ficar fazendo polling — os eventos Pusher mantêm todos os clientes sincronizados.

---

## Papéis

| Papel | Autenticação | O que faz |
|-------|-------------|-----------|
| **Host** | JWT (Bearer) | Cria a sala, adiciona e expulsa participantes, inicia e encerra a sessão |
| **Guest** | Sem autenticação | Cria seu perfil, busca a sala pelo código, entra, responde perguntas |

---

## Fluxo completo

### 1. Host cria a sessão

```
POST /api/game-sessions
Authorization: Bearer {token}

{
  "name": "Sala do joãozinho",
  "difficulty": "easy"   // easy | medium | hard (pode ser sobrescrito no start)
}
```

Resposta (201 Created):
```json
{
  "id": 42,
  "name": "Sala do joãozinho",
  "code": "A0C79B1154",
  "status": "waiting",
  "difficulty": "easy",
  "createdAt": "2026-05-15T12:00:00+00:00",
  "participantsCount": 0,
  "participants": [],
  "state": null
}
```

**409 Conflict** — se o usuário já tiver uma sessão com status `waiting` ou `playing`:
```json
{
  "message": "You already have an active game session.",
  "activeSession": { /* objeto da sessão ativa, mesmo formato acima */ }
}
```

O frontend deve redirecionar ao lobby da `activeSession` ao receber 409.

Eventos Pusher disparados: `game.session.created` em `private-game-session-42` e `private-user-7`.

---

### 2. Guest cria seu perfil

```
POST /guests

{
  "nickname": "pedroca",
  "avatarId": 2
}
```

Resposta:
```json
{
  "id": 15,
  "nickname": "pedroca",
  "avatar": {
    "id": 2,
    "url": "https://aritmetic.shardweb.app/images/avatars/avatar2.png"
  }
}
```

Guardar o `id` — será o `guestId` para entrar na sessão.

---

### 3. Guest busca a sessão pelo código

```
GET /api/game-sessions/code/A0C79B1154
```

Resposta: objeto completo da sessão (mesmo formato do passo 1).

---

### 4. Host (ou guest) adiciona o guest à sessão

```
POST /api/game-sessions/{sessionId}/guests

{
  "guestId": 15
}
```

Resposta:
```json
{
  "id": 88,
  "gameSessionId": 42,
  "guest": {
    "id": 15,
    "nickname": "pedroca"
  },
  "score": 0,
  "lives": 3,
  "isAlive": true
}
```

O `id` retornado é o `sessionGuestId` — identificador do participante dentro desta sessão. O guest deve guardá-lo para responder.

Evento Pusher disparado: `game.participant.updated`.

---

### 5. Host inicia a sessão

```
POST /api/game-sessions/{id}/start
Authorization: Bearer {token}

{
  "target": 12,           // opcional, 1-30; omitir para sortear
  "totalRounds": 10,      // opcional, padrão 10, máximo 100
  "responseWindowMs": 10000  // opcional, padrão 15000, min 3000, max 120000
}
```

Resposta:
```json
{
  "status": "started",
  "round": 1,
  "totalRounds": 10,
  "question": { /* veja estrutura de questão abaixo */ },
  "session": { /* objeto da sessão */ }
}
```

Eventos Pusher disparados em sequência:
1. `game.session.started`
2. `game.round.started`
3. `game.question.generated`

---

### 6. Guest responde

```
POST /api/game-sessions/{sessionId}/answer

{
  "gameSessionGuestId": 88,
  "answer": 24,
  "timeMs": 3200
}
```

- `answer`: inteiro com a resposta calculada
- `timeMs`: tempo em ms que o guest levou para responder (medido no frontend)

Resposta:
```json
{
  "correct": true,
  "pointsEarned": 89,
  "timedOut": false,
  "livesRemaining": 3,
  "isAlive": true,
  "roundFinished": false
}
```

Se `roundFinished: true`, a resposta também carrega `next` com o resultado do fechamento (próxima rodada ou sessão encerrada).

Eventos Pusher disparados:
- `game.answer.received`
- `game.participant.updated`
- `game.participant.eliminated` (se lives chegou a 0)
- Se última resposta ou timeout: `game.round.finished` → `game.round.started` + `game.question.generated` (ou `game.session.finished`)

---

### 7. Fechamento automático de rodada

O backend agenda um fechamento via Symfony Messenger com delay de `responseWindowMs + 100ms`. Quando o timer dispara, quem não respondeu leva penalidade automaticamente.

**O worker precisa estar rodando:**
```bash
php bin/console messenger:consume async -vv
```

---

### 8. Host expulsa um participante (opcional)

```
DELETE /api/game-sessions/{sessionId}/guests/{id}
Authorization: Bearer {token}
```

- `{id}` é o `sessionGuestId` (o `id` retornado ao adicionar o participante, não o `guestId`).
- Funciona em qualquer status de sessão (`waiting` ou `playing`).
- O participante é **removido do banco** antes do evento ser publicado.
- Resposta: `{ "message": "Game session guest deleted." }`

Evento Pusher disparado: `game.participant.kicked`

---

### 9. Encerramento manual (opcional)

```
POST /api/game-sessions/{id}/finish
Authorization: Bearer {token}

{
  "reason": "manual"  // opcional
}
```

---

## Estrutura da questão (`question`)

```json
{
  "id": "q-r1-a3f0c12b",
  "round": 1,
  "target": 12,
  "multiplier": 7,
  "operation": "7x12",
  "correctAnswer": 84,
  "options": [69, 84, 91, 78],
  "timeoutMs": 10000
}
```

- `operation` é a expressão exibida ao jogador
- `options` são 4 alternativas embaralhadas (múltipla escolha)
- `correctAnswer` está incluso no payload — o frontend pode mostrar a resposta correta após a rodada; **não use para validar no cliente**, pois o backend valida

---

## Sistema de pontuação

Fórmula: `100 × (0.5 + 0.5 × timeBonus)`

Onde `timeBonus = (responseWindowMs - timeMs) / responseWindowMs` (clampeado entre 0 e 1).

| Tempo de resposta | Pontos aproximados |
|-------------------|--------------------|
| Imediato          | ~100 pts           |
| Metade do tempo   | ~75 pts            |
| No limite         | ~50 pts            |
| Errou / timeout   | 0 pts, -1 vida     |

---

## Vidas e eliminação

- Cada participante começa com **3 vidas**
- Perde 1 vida ao:
  - Errar a resposta
  - Enviar resposta após o timeout
  - Não responder até o fechamento da rodada
- Ao chegar em 0 vidas: `isAlive = false`, participante não responde mais
- Sessão termina automaticamente quando restar ≤ 1 jogador vivo

---

## Endpoints de referência rápida

### Sem autenticação (guest flow)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/guests` | Criar perfil de guest |
| `GET` | `/files/avatars` | Listar avatares disponíveis |
| `GET` | `/api/game-sessions/code/{code}` | Buscar sessão pelo código |
| `GET` | `/api/game-sessions/{sessionId}/guests` | Listar participantes da sessão |
| `POST` | `/api/game-sessions/{sessionId}/guests` | Entrar na sessão |
| `GET` | `/api/game-sessions/{sessionId}/guests/{id}` | Detalhar participante |
| `POST` | `/api/game-sessions/{sessionId}/answer` | Responder pergunta |

### Com autenticação (host — Bearer JWT)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/api/register` | Registrar conta |
| `POST` | `/api/login` | Login (retorna JWT + refresh token) |
| `POST` | `/api/token/refresh` | Renovar token |
| `GET` | `/api/me` | Dados do usuário autenticado |
| `GET` | `/api/game-sessions` | Listar sessões do host |
| `POST` | `/api/game-sessions` | Criar sessão |
| `GET` | `/api/game-sessions/{id}` | Detalhar sessão |
| `PATCH` | `/api/game-sessions/{id}` | Atualizar sessão |
| `DELETE` | `/api/game-sessions/{id}` | Remover sessão |
| `POST` | `/api/game-sessions/{id}/start` | Iniciar jogo |
| `POST` | `/api/game-sessions/{id}/finish` | Encerrar jogo manualmente |
| `PATCH` | `/api/game-sessions/{sessionId}/guests/{id}` | Atualizar participante |
| `DELETE` | `/api/game-sessions/{sessionId}/guests/{id}` | Expulsar participante (dispara `game.participant.kicked`) |

---

## Eventos Pusher

### Canais

| Canal | Quem assina |
|-------|-------------|
| `private-game-session-{sessionId}` | Todos os participantes e o host |
| `private-user-{userId}` | Somente o host |

> Canais `private-*` exigem autenticação de canal via endpoint de auth do Pusher configurado no backend.

---

### `game.session.created`

Canais: sessão + usuário

```json
{
  "sessionId": 42,
  "session": {
    "id": 42,
    "status": "waiting",
    "difficulty": "easy",
    "userId": 7,
    "state": null
  }
}
```

---

### `game.session.started`

Canais: sessão + usuário

```json
{
  "sessionId": 42,
  "session": { /* objeto da sessão */ },
  "round": 1,
  "totalRounds": 10,
  "target": 12,
  "question": { /* objeto da questão */ }
}
```

---

### `game.round.started`

Canal: sessão

```json
{
  "sessionId": 42,
  "round": 2,
  "question": { /* objeto da questão */ }
}
```

---

### `game.question.generated`

Canal: sessão

```json
{
  "sessionId": 42,
  "question": { /* objeto da questão */ }
}
```

> Disparado junto com `game.round.started`. Use um ou outro para renderizar a questão.

---

### `game.answer.received`

Canal: sessão

```json
{
  "sessionId": 42,
  "participant": {
    "id": 88,
    "guestId": 15,
    "nickname": "pedroca",
    "score": 89,
    "lives": 3,
    "isAlive": true
  },
  "answerResult": {
    "correct": true,
    "pointsEarned": 89,
    "submittedAnswer": 84,
    "timeMs": 3200
  }
}
```

---

### `game.participant.updated`

Canal: sessão

```json
{
  "sessionId": 42,
  "participant": {
    "id": 88,
    "guestId": 15,
    "nickname": "pedroca",
    "score": 89,
    "lives": 3,
    "isAlive": true
  }
}
```

Disparado após qualquer mudança de estado do participante (resposta, penalidade, entrada na sala).

---

### `game.participant.eliminated`

Canal: sessão

```json
{
  "sessionId": 42,
  "participant": {
    "id": 88,
    "guestId": 15,
    "nickname": "pedroca",
    "score": 0,
    "lives": 0,
    "isAlive": false
  }
}
```

---

### `game.participant.kicked`

Canal: sessão

```json
{
  "sessionId": 42,
  "participant": {
    "id": 88,
    "guestId": 15,
    "nickname": "pedroca",
    "score": 45,
    "lives": 2,
    "isAlive": true
  }
}
```

Disparado pelo host ao remover um participante via `DELETE /api/game-sessions/{sessionId}/guests/{id}`. Diferente de `eliminated` (participante permanece na lista com `isAlive: false`), aqui o participante **deve ser removido da lista** no frontend.

O cliente que foi kickado pode detectar isso comparando `participant.id` com o `guest-session-id` armazenado localmente.

---

### `game.round.finished`

Canal: sessão

```json
{
  "sessionId": 42,
  "roundSummary": {
    "round": 1,
    "answersCount": 3,
    "closeReason": "all_answered",
    "participants": [
      {
        "id": 88,
        "guestId": 15,
        "nickname": "pedroca",
        "score": 89,
        "lives": 3,
        "isAlive": true
      }
    ]
  }
}
```

`closeReason`: `"all_answered"` | `"timeout"`

---

### `game.session.finished`

Canais: sessão + usuário

```json
{
  "sessionId": 42,
  "session": { /* objeto da sessão */ },
  "reason": "last_alive",
  "ranking": [
    {
      "position": 1,
      "id": 88,
      "guestId": 15,
      "nickname": "pedroca",
      "score": 340,
      "lives": 2,
      "isAlive": true
    },
    {
      "position": 2,
      "id": 89,
      "guestId": 16,
      "nickname": "zico",
      "score": 120,
      "lives": 0,
      "isAlive": false
    }
  ]
}
```

`reason`: `"last_alive"` | `"total_rounds"` | `"manual"`

Ranking ordenado por: score DESC → lives DESC → id ASC.

---

## Reconexão / refresh de página

Ao reconectar, reidratar o estado com:

```
GET /api/game-sessions/{id}          → estado da sessão (rodada atual, questão, etc.)
GET /api/game-sessions/{sessionId}/guests  → lista de participantes com score/lives
```

Esses dois endpoints garantem recuperação completa mesmo se eventos Pusher tiverem sido perdidos.

---

## Estado da sessão (`state`)

Campo JSON persistido em `GameSession.state`. Estrutura durante o jogo:

```json
{
  "target": 12,
  "round": 3,
  "totalRounds": 10,
  "responseWindowMs": 10000,
  "question": { /* questão atual */ },
  "answers": {
    "88": {
      "answer": 84,
      "correct": true,
      "pointsEarned": 89,
      "timeMs": 3200,
      "timedOut": false,
      "lateAnswer": false,
      "answeredAt": "2026-05-06T12:34:56+00:00"
    },
    "89": {
      "answer": null,
      "correct": false,
      "pointsEarned": 0,
      "timeMs": null,
      "timedOut": true,
      "noResponse": true,
      "answeredAt": "2026-05-06T12:35:06+00:00"
    }
  },
  "roundStartedAt": "2026-05-06T12:34:50+00:00",
  "startedAt": "2026-05-06T12:30:00+00:00",
  "roundClosedAt": "2026-05-06T12:35:06+00:00",
  "roundCloseReason": "timeout"
}
```

Após finalização, o `state` também inclui:

```json
{
  "finishedAt": "2026-05-06T12:45:00+00:00",
  "finishReason": "total_rounds",
  "ranking": [ /* array de participantes ordenados */ ]
}
```
