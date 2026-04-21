# Game Session: fluxo do motor + consumo no frontend

## Visão geral

O motor da `GameSession` funciona em **comando + evento**, com progressão de rodada controlada no backend:

- **Comando (REST)**: frontend chama endpoint para executar ações (`start`, `answer`, `finish`).
- **Evento (Pusher)**: backend publica eventos para todos os clientes reagirem em tempo real.

As perguntas seguem a regra:

- `multiplier` sorteado de **1 a 10**
- `target` sorteado (ou enviado no start) de **1 a 30**
- operação final: `multiplier x target`
- dificuldade derivada do target:
  - `1..10` = `easy`
  - `11..20` = `medium`
  - `21..30` = `hard`

---

## Estado persistido (sim, está salvo)

O estado do jogo está persistido para facilitar gestão, replay e reconexão:

1. **`GameSession.state` (JSON)**  
   Guarda estado global da sessão/rodada, por exemplo:
   - `target`
   - `round`
   - `totalRounds`
   - `question`
   - `answers`
   - `startedAt`
   - `finishedAt`
   - `finishReason`
   - `ranking`

2. **`GameSessionGuest` (relacional por participante)**  
   Guarda estado individual:
   - `score`
   - `lives`
   - `isAlive`

---

## Endpoints principais da sessão

- `POST /api/game-sessions` — cria sessão
- `GET /api/game-sessions` — lista sessões do usuário autenticado
- `GET /api/game-sessions/{id}` — detalha sessão
- `PATCH /api/game-sessions/{id}` — atualiza sessão
- `DELETE /api/game-sessions/{id}` — remove sessão

### Ações do motor

- `POST /api/game-sessions/{id}/start`
- `POST /api/game-sessions/{id}/answer`
- `POST /api/game-sessions/{id}/finish`

### Participantes

- `GET /api/game-sessions/{sessionId}/guests`
- `POST /api/game-sessions/{sessionId}/guests`
- `GET /api/game-sessions/{sessionId}/guests/{id}`
- `PATCH /api/game-sessions/{sessionId}/guests/{id}`
- `DELETE /api/game-sessions/{sessionId}/guests/{id}`

---

## Fluxo do jogo (backend)

1. Cria sessão (`POST /api/game-sessions`).
2. Adiciona participantes (`POST /api/game-sessions/{id}/guests`).
3. Inicia (`POST /start`):
   - define/sorteia `target`,
   - calcula `difficulty`,
   - gera pergunta da rodada 1,
   - salva estado inicial no `state`.
4. Participante responde (`POST /answer`):
   - valida resposta e tempo,
   - atualiza `answers` no `state`,
   - atualiza `score/lives/isAlive` em `GameSessionGuest`.
5. **Fechamento de rodada é automático**:
   - backend fecha por timeout (`responseWindowMs`) usando Messenger com mensagem atrasada,
   - aplica penalidade para quem não respondeu,
   - resposta fora da janela também conta erro e perde vida.
6. Backend abre próxima rodada automaticamente (ou finaliza sessão) e publica eventos Pusher.
7. Finaliza (`POST /finish`) manualmente ou por regra de término.

---

## Eventos realtime publicados (Pusher)

Canal principal: `private-game-session-{sessionId}`  
Canal do dono: `private-user-{userId}`

Eventos:

- `game.session.created`
- `game.session.started`
- `game.question.generated`
- `game.answer.received`
- `game.participant.updated`
- `game.round.finished`
- `game.round.started`
- `game.participant.eliminated`
- `game.session.finished`

Payload base inclui:

- `schemaVersion`
- `occurredAt`
- `sessionId`
- dados específicos do evento

---

## Como o frontend deve consumir

### 1) Conectar realtime

- Autenticar usuário (JWT).
- Assinar `private-game-session-{sessionId}` no Pusher.
- Se for tela de gestão do dono, assinar também `private-user-{userId}`.
- Manter o worker do Messenger ativo no backend (`php bin/console messenger:consume async`) para fechamento automático das rodadas por timeout.

### 2) Executar ações por REST

- Botões/ações do usuário disparam REST (`start`, `answer`, `finish`).
- O frontend **não depende só da resposta HTTP**; usa os eventos para sincronizar todos os clientes.

### 3) Atualizar UI por evento

- `game.question.generated` / `game.round.started` → renderizar pergunta/rodada.
- `game.answer.received` / `game.participant.updated` → atualizar placar, vidas, status.
- `game.participant.eliminated` → refletir eliminação.
- `game.round.finished` → mostrar resumo da rodada.
- `game.session.finished` → mostrar ranking final e travar ações de jogo.

### Regra de vidas/erro

- Vidas iniciais: **3** por participante.
- Perde 1 vida quando:
  - erra a resposta;
  - responde fora da janela;
  - não responde até o fechamento automático da rodada.

### 4) Reconexão / refresh de página

Ao reconectar, reidratar estado com:

- `GET /api/game-sessions/{id}`
- `GET /api/game-sessions/{sessionId}/guests`

Isso garante recuperação mesmo se algum evento realtime for perdido.

---

## Observação importante

Como os canais são `private-*`, o app precisa do fluxo de autenticação de canais do Pusher no backend (auth endpoint para assinatura de canal).
