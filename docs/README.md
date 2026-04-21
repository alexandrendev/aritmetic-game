# Bruno docs - Auth JWT

Importe a coleção `docs/bruno` no Bruno.

Use o ambiente `docs/bruno/environments/local.bru`.

Ordem sugerida de testes:

1. `auth/register`
2. `auth/login` (pode usar `identifier` com username ou email)
3. Copie `token` para `accessToken` e `refresh_token` para `refreshToken` no ambiente
4. `auth/me`
5. `auth/refresh`

Guias adicionais:

- `docs/game-session-flow.md`
- `docs/pusher-requests-guide.md`

Coleção Bruno (novo fluxo de sessão de jogo):

1. `auth/register`
2. `auth/login`
3. `auth/me` (salva `userId`)
4. `file/List available avatars`
5. `guest/Create new guest` (salva `guestId`)
6. `game-session/Create game session` (salva `sessionId`)
7. `game-session/Add guest to game session` (salva `sessionGuestId`)
8. `game-session/Start game session`
9. `game-session/Answer current question`
