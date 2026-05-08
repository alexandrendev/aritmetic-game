# Métricas do Projeto — Aritmetic Game

> Documento vivo. Atualizado semanalmente toda segunda-feira.  
> Responsável pela coleta: Alexandre  
> Início do projeto: 2026-03-22 | Última atualização: 2026-05-08

---

## Sumário

- [1. Tamanho](#1-tamanho)
- [2. Esforço](#2-esforço)
- [3. Prazo](#3-prazo)
- [4. Produtividade](#4-produtividade)
- [5. Qualidade](#5-qualidade)
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

### Snapshot atual — 2026-05-06

| Camada | Arquivos | LOC |
|---|---|---|
| Controllers | 8 | 1.427 |
| Entities | 12 | 1.104 |
| Services | 9 | 1.395 |
| Events | 20 | 476 |
| Repositories | 9 | 307 |
| EventSubscriber | 1 | 212 |
| Message/Handler | 2 | 60 |
| **Total** | **63** | **5.056** |

| Indicador | Valor |
|---|---|
| Endpoints | 33 |
| Migrações | 10 |
| Eventos Pusher | 20 |

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
| 2026-W18 | 10–16 mai | 2 |

> **Observação:** W15 e W17 com zero commits. Pico na W16 com 10 commits concentrados em dois dias (24–25/04).

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
| Frontend de jogo | — | — | 🔲 |

### Indicadores atuais — 2026-05-06

| Indicador | Valor |
|---|---|
| Duração total do projeto | 45 dias (2026-03-22 → hoje) |
| Semanas totais | 7 |
| Semanas ativas (≥1 commit) | 5 |
| Taxa de regularidade | **71%** |
| Tempo médio entre PRs | ~7 dias |
| Maior gap sem PR | 16 dias (06/04 → 24/04) |

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

### Indicadores atuais — 2026-05-06

| Indicador | Valor |
|---|---|
| LOC total em src/ | 5.056 |
| LOC médio por PR (feature) | ~487 LOC líquido |
| Endpoints entregues total | 33 |
| LOC por endpoint | **153 LOC/endpoint** |
| PRs por semana ativa | **1,6 PR/semana** |
| LOC líquido por semana ativa | **~798 LOC/semana** |

> **Nota:** O PR #24 (+2.764 LOC) distorce a média. Excluindo-o, a média cai para ~162 LOC/PR, mais representativa de uma entrega incremental saudável.

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

### Indicadores atuais — 2026-05-08

| Indicador | Valor | Alerta |
|---|---|---|
| Arquivos de teste | **4** | 🟡 Cobertura inicial — apenas 3 services e 1 entity |
| Testes executados | 37 | — |
| Asserts | 178 | — |
| Taxa de sucesso | **100%** | 🟢 Suíte verde |
| Commits `feat` | 10 | — |
| Commits `fix` | 1 | — |
| Razão fix/feat | **0,10** | 🟢 Baixa (fase inicial) |
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
3. `EventSubscriber` Pusher (212 LOC) — efeitos colaterais de publicação.
4. Testes de integração HTTP (Symfony `WebTestCase`) para os 33 endpoints.

> **Análise:** A primeira leva de testes cobre os componentes determinísticos (geradores, lógica de ferramentas, máquina de estados do jogador) e estabelece a infraestrutura Pest+Mockery. O motor de jogo e o subscriber Pusher seguem descobertos e continuam sendo o maior risco — a próxima iteração deve atacar `GameSessionEngineService`. A razão fix/feat de 0,10 é esperada para o estágio atual, mas tende a aumentar conforme o frontend iniciar o consumo real da API.

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
```
