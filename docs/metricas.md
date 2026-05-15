# Métricas do Projeto — Aritmetic Game

> Documento vivo. Atualizado semanalmente toda segunda-feira.  
> Responsável pela coleta: Alexandre  
> Início do projeto: 2026-03-22 | Última atualização: 2026-05-15

---

## Sumário

- [1. Tamanho](#1-tamanho)
- [2. Esforço](#2-esforço)
- [3. Prazo](#3-prazo)
- [4. Produtividade](#4-produtividade)
- [5. Qualidade](#5-qualidade)
- [6. Processo de Desenvolvimento](#6-processo-de-desenvolvimento)
- [7. Aquisição e Retenção](#7-aquisição-e-retenção)
- [8. Engajamento](#8-engajamento)
- [9. Saúde em Produção](#9-saúde-em-produção)
- [Registro Semanal](#registro-semanal)
- [Como coletar os dados](#como-coletar-os-dados)

---

## 1. Tamanho

### O que essa dimensão revela

Tamanho quantifica o crescimento real do produto de software: quanto código foi escrito, quantas funcionalidades existem e qual é a complexidade estrutural do sistema. Permite comparar o volume entregue com o esforço empenhado e detectar inchaço desnecessário ou entrega insuficiente.

**Risco que ajuda a identificar:** crescimento desordenado (muitas linhas sem funcionalidade equivalente), acúmulo de código morto, ou escopo sendo expandido silenciosamente.

### Métricas escolhidas

| Métrica | Definição |
|---|---|
| **LOC** | Linhas de código em `app/src/` (PHP) |
| **N° de endpoints** | Rotas com método HTTP explícito em controllers |
| **N° de entidades** | Arquivos em `src/Entity/` |
| **N° de eventos Pusher** | Arquivos em `src/Event/` |
| **N° de migrações** | Arquivos em `migrations/` |

#### Justificativa técnica

LOC é uma métrica simples e objetivamente coletável via `wc -l`. Sozinha ela é limitada — código verboso infla o número sem agregar valor — mas combinada com N° de endpoints e entidades revela a relação entre volume e funcionalidade. Para este projeto orientado a eventos, o N° de eventos Pusher é um indicador de riqueza funcional que uma contagem genérica de LOC não capturaria.

### Coleta

```bash
# LOC em src/
find app/src -name "*.php" | xargs wc -l | tail -1

# Endpoints
grep -r "#\[Route(" app/src/Controller --include="*.php" | grep "methods" | wc -l

# Entidades, eventos, migrações
ls app/src/Entity/*.php | wc -l
ls app/src/Event/*.php | wc -l
ls app/migrations/*.php | wc -l
```

Frequência: semanal (toda segunda-feira).

### Snapshot atual — 2026-05-15

| Camada | Arquivos | LOC |
|---|---|---|
| Controllers | 9 | 1.537 |
| Entities | 12 | 1.119 |
| Services | 9 | 1.412 |
| Events | 21 | 503 |
| Repositories | 9 | 298 |
| EventSubscriber | 1 | 231 |
| Message/Handler | 2 | 60 |
| **Total** | **65** | **5.235** |

| Indicador | Valor |
|---|---|
| Endpoints | 34 |
| Migrações | 11 |
| Eventos Pusher | 21 |

> **Principais adições desde 2026-05-06:** `GameParticipantKickedEvent` (novo evento de expulsão de participante); campo `createdAt` em `GameSession` com migration correspondente; lógica de bloqueio de sessões duplicadas em `GameSessionController::create()`; enriquecimento da serialização de sessão com `participantsCount`, `participants` e `createdAt`; método `findActiveByUserId()` em `GameSessionRepository`; handler `onParticipantKicked()` em `GameSessionPusherSubscriber`.
>
> **Análise:** A densidade de 154 LOC/endpoint permanece estável (vs. 153 anterior), confirmando que os novos endpoints e comportamentos seguem o mesmo padrão de complexidade. O crescimento de +179 LOC com +1 endpoint e +1 evento reflete adição de funcionalidade transversal (gerenciamento de sala + expulsão) que distribui código entre camadas existentes ao invés de criar controllers novos. O EventSubscriber cresceu +19 LOC (+9%) por um único handler novo — evidência de que a arquitetura de eventos está bem compartimentada.

---

## 2. Esforço

### O que essa dimensão revela

Esforço mede o trabalho efetivamente realizado pela equipe no período. Em projetos sem time-tracking formal, os commits e PRs merged são proxies razoáveis: cada PR representa uma unidade de trabalho concluída e revisada.

**Risco que ajuda a identificar:** picos de esforço concentrados (indicam entrega em cima do prazo), semanas sem atividade (paralisia ou bloqueio técnico), e desequilíbrio entre volume de código e PRs entregues.

### Métricas escolhidas

| Métrica | Definição |
|---|---|
| **Commits por semana** | Total de commits na semana via `git log` |
| **PRs merged por semana** | Merges em `main` rastreados via `git log --merges` |
| **LOC líquido por PR** | Linhas adicionadas menos linhas removidas no merge commit |

#### Justificativa técnica

Commits sozinhos são ruidosos (commits de fix, rebase, ajuste de typo). PRs merged são uma unidade mais significativa pois passaram por revisão. LOC líquido por PR revela a "densidade" do esforço — um PR com +2.764 LOC líquidos (como o PR #24) é muito diferente de um com +3 LOC, ainda que ambos contem como 1 PR.

### Coleta

```bash
# Commits por semana
git log --format="%ad" --date=format:"%Y-%W" | sort | uniq -c

# PRs merged com LOC
git log --merges --format="%H|%ad|%s" --date=format:"%Y-%m-%d" | while IFS='|' read hash date subject; do
  added=$(git diff --shortstat ${hash}^..${hash} | grep -oP '\d+ insertion' | grep -oP '\d+')
  removed=$(git diff --shortstat ${hash}^..${hash} | grep -oP '\d+ deletion' | grep -oP '\d+')
  echo "$date | $subject | +${added:-0} -${removed:-0}"
done
```

### Histórico de PRs merged

| Data | PR | LOC + | LOC − | LOC líquido |
|---|---|---|---|---|
| 2026-03-28 | #11 — feat: File entity + seeder + endpoint | +596 | −3 | **+593** |
| 2026-03-28 | #12 — chore: remove unused repository | 0 | −43 | **−43** |
| 2026-03-28 | #13 — feat: Guest entity + endpoint | +283 | 0 | **+283** |
| 2026-03-28 | #14 — docs: atualiza nomes Bruno | +26 | −17 | **+9** |
| 2026-04-06 | #24 — feat: game engine + Pusher + battle/chat | +2.764 | 0 | **+2.764** |
| 2026-04-24 | #25 — feat: room code + get by code | +217 | −20 | **+197** |
| 2026-04-24 | #26 — chore: app settings | +2 | −1 | **+1** |
| 2026-04-25 | #27 — build: Docker + worker | +95 | −5 | **+90** |

### Commits por semana (histórico)

| Semana | Período (aprox.) | Commits |
|---|---|---|
| 2026-W11 | 22–28 mar | 3 |
| 2026-W12 | 29 mar – 4 abr | 8 |
| 2026-W13 | 5–11 abr | 1 |
| 2026-W14 | 12–18 abr | 1 |
| 2026-W15 | 19–25 abr | — |
| 2026-W16 | 26 abr – 2 mai | 10 |
| 2026-W17 | 3–9 mai | — |
| 2026-W18 | 10–16 mai | 6 |
| 2026-W19 | 17–23 mai | 3 + em andamento |

> **Observação:** W15 e W17 com zero commits. Pico na W16 com 10 commits concentrados em dois dias (24–25/04). W18 voltou a 6 commits incluindo feat(pusher), feat(cors) e setup de testes; W19 em andamento com feat de gerenciamento de salas e expulsão de participantes (branch `feat/file-crud`).

> **Análise:** O PR #24 (+2.764 LOC) concentrou 73% do volume total entregue em um único merge — padrão esperado em fase de bootstrap de domínio complexo (engine + Pusher + battle/chat precisam coexistir antes de qualquer entrega incremental). Os 3 PRs merged em menos de 30 minutos no dia 24/04 revelam um padrão de entrega em lote: desenvolvimento acumulado localmente e submetido em sequência rápida, eficiente para time solo mas que dificultaria revisão contextual em time. A ausência de PRs nas semanas W15 e W17 é coerente com a irregularidade identificada na dimensão de Prazo.

---

## 3. Prazo

### O que essa dimensão revela

Prazo compara o ritmo de entrega planejado versus o ritmo real. Sem um cronograma formal, o indicador principal é a **regularidade** dos commits e PRs ao longo das semanas — ausência de atividade por períodos prolongados é um sinal de risco.

**Risco que ajuda a identificar:** entregas acumuladas no final (efeito "big bang"), semanas mortas que podem indicar bloqueio técnico ou dívida de planejamento, e desvio entre marcos estimados e realizados.

### Métricas escolhidas

| Métrica | Definição |
|---|---|
| **Semanas ativas** | Semanas com pelo menos 1 commit |
| **Taxa de regularidade** | Semanas ativas / semanas totais (%) |
| **Tempo médio entre PRs** | Dias entre merges consecutivos em `main` |
| **Marcos planejados vs realizados** | Comparação de datas previstas e entregues |

#### Justificativa técnica

A taxa de regularidade captura o problema que uma simples contagem total de commits esconde: um projeto pode ter 25 commits e ainda assim ter ficado parado por 3 semanas. O tempo médio entre PRs mede a cadência de entrega de valor — picos longos indicam funcionalidades que demoram a sair ou integração postergada.

### Marcos do projeto

| Marco | Data prevista | Data realizada | Status |
|---|---|---|---|
| Setup inicial + Auth JWT | — | 2026-03-22 | ✅ |
| File + Guest entities + endpoints | — | 2026-03-28 | ✅ |
| Game engine + Pusher | — | 2026-04-06 | ✅ |
| Room code (get by code) | — | 2026-04-24 | ✅ |
| Docker + worker | — | 2026-04-25 | ✅ |
| Admin CRUD de avatars | — | 2026-05-06 | ✅ |
| Setup de testes (Pest) + suíte inicial | — | 2026-05-08 | ✅ |
| Pusher auth dual-path (host + guest) | — | 2026-05-13 | ✅ |
| Gerenciamento de salas: bloqueio de duplicatas, listagem enriquecida, `createdAt` | — | 2026-05-15 | ✅ |
| Expulsão de participantes (`game.participant.kicked`) | — | 2026-05-15 | ✅ |
| Frontend de jogo (completo) | — | — | 🔲 |

### Indicadores atuais — 2026-05-15

| Indicador | Valor |
|---|---|
| Duração total do projeto | 54 dias (2026-03-22 → hoje) |
| Semanas totais | 9 |
| Semanas ativas (≥1 commit) | 7 |
| Taxa de regularidade | **78%** |
| Tempo médio entre PRs | ~7 dias |
| Maior gap sem PR | 16 dias (06/04 → 24/04) |

> **Análise:** A taxa de regularidade de 71% é aceitável para projeto solo sem sprints formais, mas o histograma revela dois modos de operação — burst (W12 com 8 commits, W16 com 10 em 2 dias) e silêncio total (W15, W17). O gap de 16 dias pós-PR #24 é o sinal mais relevante: entregar o engine de jogo não gerou próximo passo imediato na fila de trabalho, indicando que o backlog carece de granularidade após marcos técnicos grandes. O tempo médio de 7 dias entre PRs é puxado para cima por esses gaps; a mediana está muito abaixo, já que a maioria dos PRs foi merged no mesmo dia em que foram abertos.

---

## 4. Produtividade

### O que essa dimensão revela

Produtividade relaciona o volume entregue com o tempo e esforço gastos. Permite identificar se o ritmo de entrega está aumentando, estável ou caindo ao longo das semanas, e se há desproporcionalidade entre esforço e resultado funcional.

**Risco que ajuda a identificar:** queda de ritmo nas fases finais (fadiga ou dívida técnica acumulada), features que consomem muito esforço por pouco valor entregue, e sobrecarga de trabalho concentrada em uma única pessoa.

### Métricas escolhidas

| Métrica | Definição |
|---|---|
| **LOC líquido / semana** | Linhas de código líquidas adicionadas por semana ativa |
| **Endpoints entregues / semana** | Novas rotas por semana ativa |
| **PRs merged / semana ativa** | Unidades de trabalho concluídas por semana |
| **Razão LOC/endpoint** | LOC total ÷ N° de endpoints (densidade por funcionalidade) |

#### Justificativa técnica

LOC/semana isolado pode ser inflado por código repetitivo; combinado com endpoints/semana e PRs/semana forma uma visão triangulada: muito LOC com poucos endpoints e poucos PRs sugere código de suporte (infra, config) ou refatoração sem entrega funcional. A razão LOC/endpoint é um proxy de complexidade média por funcionalidade.

### Indicadores atuais — 2026-05-15

| Indicador | Valor |
|---|---|
| LOC total em src/ | 5.235 |
| LOC médio por PR (feature) | ~487 LOC líquido |
| Endpoints entregues total | 34 |
| LOC por endpoint | **154 LOC/endpoint** |
| PRs por semana ativa | **1,4 PR/semana** |
| LOC líquido por semana ativa | **~748 LOC/semana** |

> **Nota:** O PR #24 (+2.764 LOC) distorce a média. Excluindo-o, a média cai para ~162 LOC/PR, mais representativa de uma entrega incremental saudável.

> **Análise:** A razão de 153 LOC/endpoint é estável e dentro da faixa esperada para um backend REST Symfony estruturado. A média de 487 LOC/PR é fortemente distorcida pelo outlier #24; a mediana (~162 LOC/PR) é mais representativa da cadência real de entrega. A métrica mais confiável para monitorar é PRs/semana ativa (1,6): ela descarta semanas mortas e mede o ritmo real quando o projeto está em movimento — o objetivo para as próximas semanas deveria ser mantê-la acima de 2 conforme o frontend iniciar o consumo ativo da API.

---

## 5. Qualidade

### O que essa dimensão revela

Qualidade mensura a robustez e a confiabilidade do código entregue. Para um projeto de jogo em tempo real com lógica de estado complexa, qualidade é crítica: bugs no engine de jogo ou nos eventos Pusher afetam a experiência de todos os jogadores simultaneamente.

**Risco que ajuda a identificar:** acúmulo de dívida técnica, ausência de verificação automatizada, bugs latentes em lógica crítica (cálculo de pontos, fechamento de rodada, eliminação de participantes), e fragilidade a regressões futuras.

### Métricas escolhidas

| Métrica | Definição |
|---|---|
| **Arquivos de teste** | Quantidade de arquivos `*Test.php` em `app/tests/` |
| **Asserts executados** | Total de asserts da suíte (saída final do Pest) |
| **Taxa de sucesso** | Testes verdes ÷ total de testes (%) |
| **Razão fix/feat** | Commits de `fix` ÷ commits de `feat` (quanto do esforço é corretivo) |
| **Commits de hotfix em main** | Fixes diretos sem PR (indicam urgência e processo contornado) |
| **Endpoints sem validação explícita** | Rotas que não validam o payload antes de persistir |

#### Justificativa técnica

Cobertura de testes é a métrica de qualidade mais objetiva disponível. A razão fix/feat revela se o projeto está estabilizando (aumenta com maturidade saudável) ou se está em ciclo de regressão constante (fix/feat > 0,5 é sinal de alerta). Hotfixes diretos em main indicam processo de qualidade sendo contornado sob pressão.

Cobertura de testes supera outras métricas de qualidade (como complexidade ciclomática) neste contexto por ser **acionável**: é possível escrever um teste a qualquer momento; já a complexidade ciclomática exige refatoração para melhorar.

### Coleta

```bash
# Arquivos de teste
find app/tests -name "*Test.php" | wc -l

# Suíte completa (asserts e taxa de sucesso saem na linha final do Pest)
docker compose run --rm php php vendor/bin/pest --testdox

# Razão fix/feat
feat=$(git log --oneline | grep -c "^.\{8\} feat")
fix=$(git log --oneline | grep -c "^.\{8\} fix")
echo "feat: $feat | fix: $fix | razão: $(echo "scale=2; $fix/$feat" | bc)"

# Hotfixes diretos em main (commits sem merge)
git log --no-merges --oneline main | grep "fix"
```

### Indicadores atuais — 2026-05-15

| Indicador | Valor | Alerta |
|---|---|---|
| Arquivos de teste | **4** | 🟡 Cobertura inicial — apenas 3 services e 1 entity |
| Testes executados | 37 | — |
| Asserts | 178 | — |
| Taxa de sucesso | **100%** | 🟢 Suíte verde |
| Commits `feat` | ~12 | — |
| Commits `fix` | 1 | — |
| Razão fix/feat | **~0,08** | 🟢 Baixa (fase inicial) |
| Hotfixes diretos em main | 1 | 🟡 Monitorar |

#### O que está coberto

| Alvo | LOC | Casos |
|---|---|---|
| `GameQuestionGeneratorService` | 79 | 16 (faixa de target, dificuldade, geração de questão e opções) |
| `WeaknessAlgorithmService` | 156 | 7 (seleção, opções de resposta, dica, opções reduzidas) |
| `ToolService` | 75 | 6 (uso de hint/eliminate/skip, jogador eliminado, ferramenta inexistente) |
| `BattlePlayer` (entity) | 186 | 8 (vidas, status ghost, score, ferramentas) |

#### O que falta cobrir (prioridade)

1. `GameSessionEngineService` (555 LOC) — núcleo do jogo, sem cobertura.
2. `BattleService` e `ChatService` — fluxo de partida e mensagens.
3. `EventSubscriber` Pusher (231 LOC) — efeitos colaterais de publicação, incluindo novo `onParticipantKicked`.
4. Testes de integração HTTP (Symfony `WebTestCase`) para os 34 endpoints.
5. `GameSessionController::create()` — lógica de bloqueio de sessões duplicadas (409) não coberta.

> **Análise:** A primeira leva de testes cobre os componentes determinísticos (geradores, lógica de ferramentas, máquina de estados do jogador) e estabelece a infraestrutura Pest+Mockery. O motor de jogo e o subscriber Pusher seguem descobertos e continuam sendo o maior risco — a próxima iteração deve atacar `GameSessionEngineService`. A razão fix/feat de 0,10 é esperada para o estágio atual, mas tende a aumentar conforme o frontend iniciar o consumo real da API.

---

## 6. Processo de Desenvolvimento

### O que essa dimensão revela

Processo de desenvolvimento mede a saúde das práticas de engenharia: como o trabalho é organizado em branches, quão rápido código entra em produção (cycle time), se a equipe mantém convenções de commit e qual é o tamanho típico das unidades de entrega. Em projetos solo, essas métricas preparam o terreno para quando um segundo desenvolvedor entrar — hábitos ruins de processo são muito mais difíceis de corrigir com time do que sozinho.

**Risco que ajuda a identificar:** cycle time elevado (código que demora a ser integrado acumula conflitos e risco), PRs excessivamente grandes (difíceis de revisar e reverter), convenções inconsistentes (dificultam leitura do histórico e automações de changelog), e branches de longa duração (divergência do estado de `main`).

### Métricas escolhidas

| Métrica | Definição |
|---|---|
| **Cycle time mediano** | Tempo entre o primeiro commit da branch e o merge em `main` |
| **Commits por PR** | Quantos commits cada PR contém (granularidade de entrega) |
| **Arquivos por PR** | Quantidade de arquivos alterados por merge (tamanho do PR) |
| **Conformidade de commits** | % de commits que seguem o padrão Conventional Commits |
| **Nomenclatura de branches** | Consistência no padrão de nomes das branches criadas |

#### Justificativa técnica

Cycle time captura o tempo real que uma unidade de trabalho leva desde o início até estar em `main` — em projeto solo sem revisão formal, o número tende a ser baixo, mas exceções (branches de longa duração) são sinais de travamento técnico. Commits por PR e arquivos por PR medem "atomicidade": PRs com 1 commit e poucos arquivos são mais fáceis de reverter e bisectar em caso de regressão. Conformidade de Conventional Commits habilita automações futuras (changelogs, releases semânticos) e facilita leitura do histórico por novos membros.

### Coleta

```bash
# Cycle time por PR (primeiro commit da branch até o merge)
git log --merges --format="%H|%s" | while IFS='|' read hash subject; do
  first=$(git log --no-merges --format="%ai" "${hash}^1..${hash}" 2>/dev/null | tail -1)
  merge=$(git log -1 --format="%ai" "$hash")
  pr=$(echo "$subject" | grep -oP '#\d+')
  echo "$pr | primeiro: $first | merge: $merge"
done

# Commits por PR
git log --merges --format="%H|%s" | while IFS='|' read hash subject; do
  count=$(git log --no-merges --oneline "${hash}^1..${hash}" 2>/dev/null | wc -l | tr -d ' ')
  pr=$(echo "$subject" | grep -oP '#\d+')
  echo "$pr: $count commit(s)"
done

# Arquivos alterados por PR
git log --merges --format="%H" | while read hash; do
  pr=$(git log -1 --format="%s" "$hash" | grep -oP '#\d+')
  files=$(git diff --name-only "${hash}^1..${hash}" 2>/dev/null | wc -l | tr -d ' ')
  echo "$pr: $files arquivo(s)"
done

# Conformidade de Conventional Commits
total=$(git log --no-merges --oneline | wc -l)
ok=$(git log --no-merges --format="%s" | grep -cP '^(feat|fix|chore|docs|build|refactor|test|style|perf|ci)(\(.+\))?: ')
echo "Conformes: $ok / $total ($(echo "scale=0; $ok * 100 / $total" | bc)%)"

# Branches criadas (remotas)
git branch -r | grep -v HEAD
```

### Indicadores atuais — 2026-05-15

#### Cycle time por PR

| PR | Primeiro commit | Merge | Cycle time |
|---|---|---|---|
| #11 | 2026-03-28 14:21 | 2026-03-28 14:37 | ~16 min |
| #12 | 2026-03-28 14:50 | 2026-03-28 14:50 | < 1 min |
| #13 | 2026-03-28 15:53 | 2026-03-28 15:54 | < 1 min |
| #14 | 2026-03-28 17:01 | 2026-03-28 17:02 | < 1 min |
| #24 | 2026-04-02 22:46 | 2026-04-06 08:05 | **4 dias** |
| #25 | 2026-04-24 21:11 | 2026-04-24 21:26 | ~15 min |
| #26 | 2026-04-24 21:41 | 2026-04-24 21:41 | < 1 min |
| #27 | 2026-04-25 13:57 | 2026-04-25 14:56 | ~59 min |
| #28 | 2026-05-08 09:16 | 2026-05-08 09:20 | ~4 min |
| feat/file-crud | 2026-05-15 | — | em andamento |

| Indicador | Valor |
|---|---|
| Cycle time mediano | < 15 min |
| Cycle time máximo | 4 dias (PR #24) |
| PRs merged no mesmo dia | 8 de 9 (89%) |

#### Tamanho e granularidade dos PRs

| PR | Commits | Arquivos alterados |
|---|---|---|
| #11 | 1 | 21 |
| #12 | 1 | 1 |
| #13 | 1 | 7 |
| #14 | 1 | 3 |
| #24 | 1 | 29 |
| #25 | 3 | 12 |
| #26 | 1 | 1 |
| #27 | 1 | 8 |
| #28 | 2 | 18 |
| **Média** | **1,3** | **~11** |

#### Conformidade de commits e nomenclatura de branches

| Indicador | Valor | Alerta |
|---|---|---|
| Total de commits (sem merges) | 23 | — |
| Commits conformes (Conventional) | 16 | — |
| Taxa de conformidade | **70%** | 🟡 Abaixo de 80% |
| Branches com prefixo `feat-` | 3 | — |
| Branches com nº de issue | 2 | — |
| Branches sem convenção | 1 (`developer`) | 🟡 Monitorar |

**Commits não-conformes identificados:**

| Commit | Problema |
|---|---|
| `update app setting` | sem tipo |
| `add shardcloud setup file` | sem tipo |
| `project setup` | sem tipo |
| `Initial commit` | sem tipo (aceitável) |
| `feat(guests) implemments guest entity...` | falta `:` após o escopo |
| `feat(gameSession):implemments...` | falta espaço após `:` |
| `docs: update avatar list and create geust request names` | typo em "geust" |

> **Análise:** O ciclo de entrega é extremamente curto — 89% dos PRs foram merged no mesmo dia em que foram abertos, o que é saudável e indica integração contínua efetiva para um projeto solo. A exceção é o PR #24 (4 dias), que coincide com a maior entrega do projeto em volume de LOC, confirmando que foi um desenvolvimento de sprint longo sem commits intermediários. A taxa de conformidade de Conventional Commits de 65% é o ponto de atenção principal do processo: os 7 commits fora do padrão incluem mensagens sem tipo algum e dois com formatação incorreta (falta de espaço ou `:` no escopo). Atingir 90%+ de conformidade é ação de baixo custo (basta consistência no momento do commit) e desbloqueia automações de changelog e versionamento semântico no futuro.

---

> As seções 7, 8 e 9 documentam métricas de produto a serem coletadas quando o produto estiver em uso real. Os comandos de coleta são prospectivos — alguns requerem instrumentação ainda não existente, indicada em cada seção.

---

## 7. Aquisição e Retenção

### O que essa dimensão revela

Aquisição mede quantos usuários chegam ao produto e completam o primeiro fluxo de valor — criar conta e jogar a primeira partida. Retenção mede quantos voltam. Em jogos competitivos, retenção é o indicador mais crítico de saúde do produto: uma aquisição forte com retenção baixa significa que o jogo não sustenta o interesse além da primeira experiência.

**Risco que ajuda a identificar:** funil de onboarding quebrado (usuários se registram mas nunca jogam), churn precoce (jogam uma vez e não voltam), e dependência de contexto específico (ex: uso apenas em ambiente escolar, sem retenção orgânica).

### Métricas escolhidas

| Métrica | Definição |
|---|---|
| **Usuários registrados** | Total de registros na tabela `user` |
| **Taxa de ativação** | % de usuários que completaram ao menos 1 partida |
| **Retenção D1** | % de usuários que voltaram no dia seguinte ao cadastro |
| **Retenção D7** | % de usuários que voltaram 7 dias após o cadastro |
| **Proporção guest / registrado** | Partidas jogadas por guests ÷ partidas jogadas por contas registradas |

#### Justificativa técnica

Taxa de ativação é o primeiro funil: um usuário cadastrado que nunca jogou indica fricção entre o registro e a entrada em sala. Retenção D1 e D7 são os benchmarks padrão de jogos casuais — D1 > 40% e D7 > 15% são referências saudáveis. A proporção guest/registrado revela se o fluxo de acesso como guest está funcionando como gateway de aquisição (guest joga → gosta → cria conta) ou como substituto permanente ao registro, o que prejudicaria métricas de identidade e retenção de longo prazo.

### Como coletaríamos

```sql
-- Total de usuários registrados
SELECT COUNT(*) AS usuarios_registrados FROM "user";

-- Taxa de ativação: usuários que participaram de ao menos 1 partida
SELECT
  COUNT(DISTINCT u.id) FILTER (WHERE bp.id IS NOT NULL) AS ativados,
  COUNT(DISTINCT u.id)                                   AS total,
  ROUND(
    COUNT(DISTINCT u.id) FILTER (WHERE bp.id IS NOT NULL) * 100.0
    / NULLIF(COUNT(DISTINCT u.id), 0), 1
  ) AS taxa_ativacao_pct
FROM "user" u
LEFT JOIN battle_player bp ON bp.user_id = u.id;

-- Proporção guest vs registrado por partida
SELECT
  COUNT(*) FILTER (WHERE guest_id IS NOT NULL)  AS partidas_guests,
  COUNT(*) FILTER (WHERE user_id IS NOT NULL)   AS partidas_registrados,
  ROUND(
    COUNT(*) FILTER (WHERE guest_id IS NOT NULL) * 100.0
    / NULLIF(COUNT(*), 0), 1
  ) AS pct_guests
FROM battle_player;
```

> **Instrumentação necessária:** Retenção D1/D7 requer registro de logins com timestamp — uma tabela `user_session` ou evento de login persistido, ainda não existente. As queries acima assumem colunas `user_id` e `guest_id` em `battle_player`; verificar nomes reais no schema antes de executar.

---

## 8. Engajamento

### O que essa dimensão revela

Engajamento mede a profundidade da interação dos jogadores durante uma sessão. Para um jogo de perguntas em batalha em tempo real, revela se as mecânicas estão funcionando como esperado: os jogadores chegam ao fim da partida, usam as ferramentas disponíveis e encontram a dificuldade equilibrada?

**Risco que ajuda a identificar:** abandono precoce de partidas (mecânicas frustrantes ou bugs no engine), desequilíbrio de dificuldade (um nível com >85% de acerto está fácil demais; <25% é punitivo e derruba o engajamento), e sub-utilização de ferramentas (hint, eliminate, skip) indicando que os jogadores não as descobrem ou não as percebem como úteis.

### Métricas escolhidas

| Métrica | Definição |
|---|---|
| **Taxa de conclusão de partida** | Partidas terminadas normalmente ÷ partidas iniciadas (%) |
| **Questões respondidas por partida** | Média de respostas submetidas por game session |
| **Taxa de acerto por dificuldade** | Respostas corretas ÷ total por nível de dificuldade (%) |
| **Uso de ferramentas por partida** | Média de hint / eliminate / skip usados por sessão |
| **Duração média de sessão** | Tempo entre criação e encerramento de uma game session |

#### Justificativa técnica

Taxa de conclusão separa partidas orgânicas (encerradas pela lógica do jogo) de partidas abandonadas (conexão perdida, jogador desistiu). Taxa de acerto por dificuldade é o principal indicador de calibração do `GameQuestionGeneratorService` — a distribuição esperada numa partida equilibrada é ~60–70% de acerto no nível médio. Uso de ferramentas por partida revela se as mecânicas de suporte ao jogador estão sendo descobertas; sub-utilização pode indicar problema de UX de apresentação, não de interesse.

### Como coletaríamos

```sql
-- Taxa de conclusão
-- Requer campo status em game_session ('finished' | 'abandoned' | 'in_progress')
SELECT
  status,
  COUNT(*) AS total,
  ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (), 1) AS pct
FROM game_session
GROUP BY status;

-- Média de questões respondidas por partida
SELECT ROUND(AVG(total), 1) AS media_respostas_por_partida
FROM (
  SELECT game_session_id, COUNT(*) AS total
  FROM game_answer
  GROUP BY game_session_id
) sub;

-- Taxa de acerto por dificuldade
SELECT
  difficulty,
  COUNT(*)                                            AS total_respostas,
  COUNT(*) FILTER (WHERE is_correct = true)           AS corretas,
  ROUND(
    COUNT(*) FILTER (WHERE is_correct = true) * 100.0
    / NULLIF(COUNT(*), 0), 1
  ) AS taxa_acerto_pct
FROM game_answer
GROUP BY difficulty
ORDER BY difficulty;

-- Uso de ferramentas por tipo
SELECT tool_type, COUNT(*) AS usos_totais
FROM tool_usage
GROUP BY tool_type
ORDER BY usos_totais DESC;

-- Duração média de sessão (requer campos created_at e finished_at em game_session)
SELECT
  ROUND(AVG(EXTRACT(EPOCH FROM (finished_at - created_at)) / 60), 1) AS duracao_media_min
FROM game_session
WHERE status = 'finished';
```

> **Instrumentação necessária:** Campo `status` em `game_session` para distinguir partidas concluídas de abandonadas. Tabela `game_answer` persistindo cada resposta com `is_correct` e `difficulty`. Tabela `tool_usage` (ou equivalente) registrando uso de cada ferramenta com `tool_type`. Campos `created_at` e `finished_at` em `game_session` para duração. Verificar o que já existe no schema atual antes de adicionar colunas.

---

## 9. Saúde em Produção

### O que essa dimensão revela

Saúde em produção mensura se o produto está funcionando de forma confiável para os usuários finais. Em um jogo multiplayer em tempo real, falhas de infra se traduzem diretamente em experiência degradada: um evento Pusher perdido significa que um jogador vê o estado errado da partida; um endpoint lento interrompe o fluxo de uma rodada no pior momento possível.

**Risco que ajuda a identificar:** degradação silenciosa de performance (erros que não chegam ao desenvolvedor), eventos Pusher sendo perdidos, endpoints críticos com latência acima do tolerável para tempo real (referência: < 200 ms para ações de jogo), worker do Messenger inativo acumulando mensagens na fila, e exceções não tratadas chegando ao cliente como 500.

### Métricas escolhidas

| Métrica | Definição |
|---|---|
| **Taxa de erro HTTP (5xx)** | Respostas 5xx ÷ total de requisições (%) |
| **Latência P50 / P95 / P99** | Percentis de tempo de resposta dos endpoints críticos |
| **Eventos Pusher publicados vs falhos** | Taxa de sucesso de publicação no dashboard do Pusher |
| **Uptime do Messenger worker** | % do tempo em que o worker de filas está ativo |
| **Exceções não tratadas** | Erros capturados nos logs do PHP sem handler explícito |

#### Justificativa técnica

P95 e P99 são mais relevantes que a média para jogos em tempo real: a média pode estar em 50 ms enquanto 5% dos jogadores experienciam 800 ms — e esses 5% são os que vão embora. O uptime do worker é crítico porque o Symfony Messenger processa eventos assíncronos; se o worker cair, mensagens ficam na fila sem processamento e o estado do jogo congela para os jogadores afetados. Exceções não tratadas nos logs são a forma mais barata de triagem de bugs antes de ter uma ferramenta de monitoramento dedicada.

### Como coletaríamos

```bash
# Erros 5xx nos logs do Nginx
docker compose logs nginx 2>/dev/null | grep '" 5[0-9][0-9] ' | wc -l

# Total de requisições (para calcular taxa de erro)
docker compose logs nginx 2>/dev/null | grep -cP '" [0-9]{3} '

# Latência por requisição — requer log_format com $request_time no Nginx
# Adicionar em docker/nginx/default.conf:
#   log_format timed '$remote_addr "$request" $status rt=$request_time';
#   access_log /var/log/nginx/access.log timed;
docker compose logs nginx 2>/dev/null \
  | grep -oP 'rt=\K[0-9.]+' \
  | sort -n \
  | awk 'BEGIN{n=0} {a[n++]=$1} END {
      print "P50:", a[int(n*0.50)], "s"
      print "P95:", a[int(n*0.95)], "s"
      print "P99:", a[int(n*0.99)], "s"
    }'

# Status do Messenger worker
docker compose ps php
docker compose logs php 2>/dev/null | grep -i "worker\|messenger\|consuming" | tail -20

# Exceções não tratadas nos logs do PHP
docker compose logs php 2>/dev/null | grep -iE "Uncaught|Fatal error|PHP Fatal" | tail -30
```

> **Instrumentação recomendada:** Configurar `log_format` com `$request_time` no Nginx (exemplo acima) para habilitar análise de latência. Para monitoramento contínuo de exceções e performance, integrar o pacote `sentry/sentry-symfony` — captura automaticamente exceções não tratadas e rastreia performance de endpoints sem instrumentação manual. O dashboard do Pusher expõe métricas de eventos entregues, conexões ativas e falhas por canal sem necessidade de instrumentação adicional no backend.

---

## Registro Semanal

> Copiar e preencher a cada semana (toda segunda-feira).

```
## Semana YYYY-WXX — DD/MM/YYYY

### Tamanho
- LOC total em src/:
- Endpoints:
- Entidades:
- Eventos:
- Migrações:

### Esforço
- Commits na semana:
- PRs merged:
- LOC líquido adicionado:

### Prazo
- Semana ativa? (S/N):
- Marcos concluídos:
- Atraso identificado:

### Produtividade
- LOC/PR médio na semana:
- Endpoints entregues:

### Qualidade
- Arquivos de teste:
- Novos commits fix:
- Regressões identificadas:

### Processo
- Cycle time mediano dos PRs da semana:
- Commits conformes / total:
- PRs com mais de 20 arquivos alterados:

### Análise e interpretação
**O que mudou:**
**Por que mudou:**
**A variação indica:**
**Ação corretiva necessária:**
```

---

## Como coletar os dados

Todos os comandos podem ser executados na raiz do repositório (`aritmetic-game/`):

```bash
# --- TAMANHO ---
find app/src -name "*.php" | xargs wc -l | tail -1
grep -r "#\[Route(" app/src/Controller --include="*.php" | grep "methods" | wc -l
ls app/src/Entity/*.php | wc -l
ls app/src/Event/*.php | wc -l
ls app/migrations/*.php | wc -l

# --- ESFORÇO ---
git log --oneline --after="YYYY-MM-DD" --before="YYYY-MM-DD" | wc -l
git log --merges --oneline --after="YYYY-MM-DD"

# --- PRAZO ---
git log --format="%ad" --date=format:"%Y-%W" | sort | uniq -c

# --- PRODUTIVIDADE ---
# LOC líquido por PR (substituir HASH pelo hash do merge commit)
git diff --shortstat HASH^..HASH

# --- QUALIDADE ---
find app/tests -name "*Test.php" | wc -l
docker compose run --rm php php vendor/bin/pest --testdox
git log --oneline | grep -c "^.\{8\} feat"
git log --oneline | grep -c "^.\{8\} fix"

# --- PROCESSO ---
# Cycle time de um PR específico (substituir HASH pelo merge commit)
first=$(git log --no-merges --format="%ai" "HASH^1..HASH" | tail -1)
merge=$(git log -1 --format="%ai" HASH)
echo "Primeiro: $first | Merge: $merge"

# Arquivos alterados no PR
git diff --name-only HASH^1..HASH | wc -l

# Conformidade de Conventional Commits (total acumulado)
total=$(git log --no-merges --oneline | wc -l)
ok=$(git log --no-merges --format="%s" | grep -cP '^(feat|fix|chore|docs|build|refactor|test|style|perf|ci)(\(.+\))?: ')
echo "Conformes: $ok / $total ($(echo "scale=0; $ok * 100 / $total" | bc)%)"
```
