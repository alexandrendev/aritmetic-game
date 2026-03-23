# Bruno docs - Auth JWT

Importe a coleção `docs/bruno` no Bruno.

Use o ambiente `docs/bruno/environments/local.bru`.

Ordem sugerida de testes:

1. `auth/register`
2. `auth/login` (pode usar `identifier` com username ou email)
3. Copie `token` para `accessToken` e `refresh_token` para `refreshToken` no ambiente
4. `auth/me`
5. `auth/refresh`
