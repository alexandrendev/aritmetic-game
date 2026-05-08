# Test guide — fluxos de ponta a ponta

Guia operacional para validar a API manualmente. Contém duas trilhas:

1. **Trilha A — Game Session** (single owner): coberta pela coleção Bruno.
2. **Trilha B — Battle Room + Chat** (multiplayer): ainda sem `.bru`, JSON cru abaixo.

> Pré-requisito: containers de pé (`docker compose up -d`), worker rodando (`messenger:consume async`) e seed de avatares feito. Ver README, seções 3, 7 e 8.

---

## Setup comum

### Bruno (modo interativo)

1. Abra a pasta `docs/bruno/` no Bruno desktop.
2. Selecione o ambiente `local` (`baseUrl = http://localhost:8080`).
3. Após `auth/login`, copie `token` para o `accessToken` do ambiente e `refresh_token` para `refreshToken`.

### curl (modo manual)

```bash
BASE=http://localhost:8080

# Substitua após o login
TOKEN='eyJ0eXAi...'
H_JSON='Content-Type: application/json'
H_AUTH="Authorization: Bearer $TOKEN"
```

### Bruno CLI (modo automatizado)

Bruno tem um CLI que executa a coleção em ordem e gera **relatório HTML** (não é um cliente interativo — só um log do que rodou):

```bash
npx -y @usebruno/cli run docs/bruno --env local --reporter-html /tmp/bruno-report.html
```

Útil pra smoke-test em CI. Pra disparar requisições "clicando", use o desktop.

---

## Trilha A — Game Session (single owner)

Modo onde **um dono** controla a partida e adiciona Guests como participantes. Sem chat, sem ferramentas.

### Ordem das requests (Bruno)

| # | Pasta/Request | O que valida | Salvar variável |
|---|---|---|---|
| 1 | `auth/register` | Cria usuário | — |
| 2 | `auth/login` | Retorna `token` + `refresh_token` | `accessToken`, `refreshToken` |
| 3 | `auth/me` | JWT funciona | `userId` |
| 4 | `file/List available avatars` | Catálogo de avatar | `avatarId` |
| 5 | `guest/Create new guest` | Cria perfil do jogador | `guestId` |
| 6 | `game-session/Create game session` | Cria sessão | `sessionId` |
| 7 | `game-session/Add guest to game session` | Adiciona participante | `sessionGuestId` |
| 8 | `game-session/Start game session` | Sorteia target, gera rodada 1 | — |
| 9 | `game-session/Answer current question` | Submete resposta | — |

### O que observar

- Resposta de **8** traz `state.question` com `operation`, `correctAnswer`, `options`, `timeoutMs`.
- Resposta de **9** atualiza `score` e `lives` do `GameSessionGuest`.
- Se o worker do Messenger estiver no ar, a rodada **fecha sozinha** após `responseWindowMs` ms — confirme com `GET /api/game-sessions/{id}` e veja `state.round` avançar.
- Eventos Pusher (se `PUSHER_ENABLED=1`): canal `private-game-session-{sessionId}`. Ver `docs/pusher-requests-guide.md` para auth do canal.

---

## Trilha B — Battle Room + Chat (multiplayer)

Modo com matchmaking por código de sala, ferramentas (`hint`, `eliminate`, `skip`) e chat. **Mínimo 2 jogadores** para iniciar.

> Estes endpoints ainda não estão na coleção Bruno. Use curl ou crie os `.bru` em `docs/bruno/battle/`.

### Pré-requisitos

Você precisa de **2 guests** já criados (passos 1–5 da Trilha A, repetidos para um segundo usuário). Salve `guestA` e `guestB`.

### Sequência

#### 1) Criar a sala (jogador A)

```bash
curl -X POST $BASE/battle/rooms \
  -H "$H_JSON" -H "$H_AUTH" \
  -d '{
    "guestId": <guestA>,
    "totalRounds": 5,
    "maxPlayers": 4
  }'
```

> `totalRounds` precisa estar em `[5, 10, 15, 20, 25, 30]`. `maxPlayers` é normalizado para o intervalo `[2, 60]`. O criador entra automaticamente como primeiro player.

Resposta inclui `id` (= `roomId`) e `players[].id` (= `playerIdA`).

#### 2) Segundo jogador entra

```bash
curl -X POST $BASE/battle/rooms/<roomId>/join \
  -H "$H_JSON" -H "$H_AUTH" \
  -d '{ "guestId": <guestB> }'
```

Resposta retorna `playerId` (= `playerIdB`).

> Erros esperados: `400 "A sala está cheia."` ou `400 "A sala já está em andamento."`.

#### 3) Ambos marcam pronto

```bash
curl -X POST $BASE/battle/rooms/<roomId>/ready \
  -H "$H_JSON" -H "$H_AUTH" \
  -d '{ "playerId": <playerIdA> }'

curl -X POST $BASE/battle/rooms/<roomId>/ready \
  -H "$H_JSON" -H "$H_AUTH" \
  -d '{ "playerId": <playerIdB> }'
```

Procure `"allReady": true` na resposta da segunda chamada.

#### 4) Dono inicia a partida

```bash
curl -X POST $BASE/battle/rooms/<roomId>/start \
  -H "$H_AUTH"
```

Resposta traz `question.operation` (ex.: `"7x6"`) e `question.options`. Salve `operation`.

#### 5) Submeter resposta

```bash
curl -X POST $BASE/battle/rooms/<roomId>/answer \
  -H "$H_JSON" -H "$H_AUTH" \
  -d '{
    "playerId": <playerIdA>,
    "operation": "<operation>",
    "answer": 42,
    "timeMs": 3500,
    "usedTool": false
  }'
```

`timeMs` vai contra a janela de 15 000 ms (`BattleService::TIME_LIMIT_MS`); `usedTool=true` aplica penalidade de 0,5x na pontuação.

#### 6) (Opcional) Usar uma ferramenta antes de responder

```bash
curl -X POST $BASE/battle/rooms/<roomId>/tool \
  -H "$H_JSON" -H "$H_AUTH" \
  -d '{
    "playerId": <playerIdA>,
    "tool": "hint",
    "operation": "<operation>"
  }'
```

`tool` aceita `hint` (intervalo do resultado), `eliminate` (reduz a 2 opções) ou `skip`. Cada ferramenta é **1 uso por jogador, por sala** — segunda tentativa retorna `400 "já foi usada"`.

#### 7) Avançar rodada

```bash
curl -X POST $BASE/battle/rooms/<roomId>/next-round \
  -H "$H_AUTH"
```

Quando todos responderem (ou só sobrar um vivo), o backend já avança automaticamente; este endpoint é o force-advance manual.

#### 8) Chat da sala

Enviar:

```bash
curl -X POST $BASE/battle/rooms/<roomId>/chat \
  -H "$H_JSON" -H "$H_AUTH" \
  -d '{
    "guestId": <guestA>,
    "message": "boa sorte!",
    "type": "message"
  }'
```

Listar:

```bash
curl -X GET $BASE/battle/rooms/<roomId>/chat -H "$H_AUTH"
```

#### 9) Inspecionar estado a qualquer momento

```bash
curl $BASE/battle/rooms/<roomId> -H "$H_AUTH"
```

Retorna `status` (`waiting` / `in_progress` / `finished`), `currentRound`, e `players[]` com `lives`, `score`, `tools` disponíveis.

---

## Casos de borda úteis para testar

| Cenário | Como reproduzir | Esperado |
|---|---|---|
| Sala cheia | Subir `maxPlayers=2` e tentar 3º `join` | `400 "A sala está cheia."` |
| Iniciar com 1 jogador | `start` antes do 2º join | `400` da `BattleService` |
| Resposta errada esgota vida | Errar 3 respostas | Status do player vai para `ghost`, `lives=0` |
| Reuso de ferramenta | Chamar `/tool` com mesma `tool` 2x | `400 "já foi usada"` |
| Player eliminado tentando ferramenta | Errar 3x e chamar `/tool` | `400 "Jogador eliminado..."` |
| Resposta fora da janela | Mandar `timeMs > 15000` | Conta como erro, perde 1 vida |
| Token inválido | Remover header `Authorization` | `401` |

---

## Próximos passos para a coleção Bruno

Para fechar a paridade da Trilha B no Bruno, criar:

```
docs/bruno/battle/
  ├── folder.bru
  ├── Create room.bru
  ├── Join room.bru
  ├── Set ready.bru
  ├── Start battle.bru
  ├── Submit answer.bru
  ├── Use tool.bru
  ├── Next round.bru
  └── Get room.bru
docs/bruno/chat/
  ├── folder.bru
  ├── Send message.bru
  └── List messages.bru
```

Cada request deve usar `auth: inherit` e variáveis `{{baseUrl}}`, `{{roomId}}`, `{{playerIdA}}`, `{{playerIdB}}` salvas via `script:post-response` (padrão dos `.bru` em `game-session/`).
