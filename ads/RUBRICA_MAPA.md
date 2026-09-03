# Mapa do projeto x rubrica — Barbearia Adrian Souza

Este documento só organiza o que já existe no projeto, mostrando onde cada
critério da rubrica é atendido. Nenhuma tela ou estilo foi alterado.

---

## 1. Banco de Dados Avançado

| Critério | Onde está | Observação |
|---|---|---|
| CTE + View analítica | `sql/banco_completo.sql` → `vw_relatorio_servicos` | Usa `WITH CTE_ServicoEstatistica AS (...)` |
| Stored Procedure com filtro + paginação | `sql/banco_completo.sql` → `sp_listar_agendamentos(p_status, p_limite, p_offset)` | Chamada pelo `api.php` via `CALL` (case `agendamentos`) |
| Trigger BEFORE UPDATE | `sql/banco_completo.sql` → `trg_verificar_preco_positivo` | Bloqueia `UPDATE servicos SET preco <= 0` |
| Function para reuso | `sql/banco_completo.sql` → `fn_calcular_faturamento_cliente(nome)` | ✅ Agora chamada em `api.php` (case `clientes`) e exibida na coluna "Faturamento" da aba Clientes do painel |
| View de centralização | `sql/banco_completo.sql` → `vw_central_agendamentos` | Junta `agendamentos` + `servicos` |

**Implementado:** `api.php` agora faz
`SELECT c.*, fn_calcular_faturamento_cliente(c.nome) AS faturamento FROM clientes c`
no case `clientes`. O `src/admin.ts` recebe esse campo (`Cliente.faturamento`) e
mostra numa coluna nova na tabela de Clientes do painel — única mudança
visual, e é a própria função do banco aparecendo na tela.

## 2. Desenvolvimento Web Avançado

| Critério | Onde está |
|---|---|
| Bootstrap (3+ componentes) | Navbar, Modais, Cards, Botões, Table (`includes/header.php`, `admin.php`, `assets/css/style.css`) |
| Template para manutenção | `includes/header.php` / `includes/footer.php` (site público) e `includes/head-admin.php` / `includes/footer-admin.php` (painel) — reaproveitados em `index.php` e `admin.php` |
| Estrutura separada por responsabilidade | `includes/conexao.php` (banco), `includes/admin_auth.php` (autenticação), `api.php` (regras de negócio), `processar_agendamento.php` (form público), `src/admin.ts`/`assets/js/admin.js` (front do painel) |
| 3 CRUDs completos | Agendamentos, Clientes, Serviços — e ainda Categorias/Produtos (loja), todos em `api.php` + `src/admin.ts` |
| Regras de exclusão com mensagem clara | `api.php` → `excluir_cliente`, `excluir_servico`, `excluir_categoria` retornam mensagem específica quando há vínculo impedindo a exclusão |

## 3. Lógica Avançada (TypeScript)

Tudo isso está em `src/admin.ts` (compilado para `assets/js/admin.js` via `tsc`):

| Critério | Função em `src/admin.ts` |
|---|---|
| Tipagem estrita, sem `any` | `interfaces` no topo (`Agendamento`, `Cliente`, `Servico`, `Categoria`, `Produto`, `Indicadores`, `RankingServico`, `RespostaApi<T>`) |
| `.reduce()` | `carregarDashboard()` (acha o campeão do ranking) e `carregarAgendamentos()` (soma faturamento confirmado) |
| `.filter()` | `carregarAgendamentos(filtro)` — separa por status |
| Ranking / destaque | `carregarDashboard()` — calcula `maxAg` e marca o card campeão com 🏆 |
| `.map()` | Presente em todas as funções `carregar*()` — transforma array em HTML e em `R$` (`formatarMoeda`) |
| Edge cases (vazio) | Todo `carregar*()` verifica lista vazia e mostra `msg-sem-*` em vez de quebrar |
| DOM sem `!` | `getEl<T>()` sempre retorna `T | null`; todo acesso é `if (el) ...` |

## 4. Tech Forge

| Critério | Onde está |
|---|---|
| `fetch` + `async/await` + `try/catch` | `buscarDados()` e `enviarDados()` em `src/admin.ts` |
| Compilação TS → JS | `tsconfig.json` + `package.json` (`npm run build` roda `tsc`) |
| Organização / responsabilidade única | Cada `carregar*()` só busca e renderiza sua seção; salvar/editar/excluir são funções separadas por entidade |
| Apresentação do fluxo completo | Banco (`sql/banco_completo.sql`) → `api.php` (JSON) → `src/admin.ts` (fetch/render) → DOM — dá pra desenhar esse fluxo em 1 slide |

---

## Observação sobre `assets/js/admin.js`

Esse arquivo é **gerado automaticamente** pelo `tsc` a partir do `src/admin.ts`
(configurado em `tsconfig.json`). Não edite `assets/js/admin.js` na mão — qualquer
ajuste deve ser feito no `.ts` e depois rodar `npm run build`, senão o código
fica dessincronizado.
