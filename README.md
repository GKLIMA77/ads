# ADS — Barbearia Adrian Souza

Site + painel administrativo em PHP, MySQL e TypeScript.

## Estrutura de pastas (reorganizada)

```
ads/
├── admin/                     → tudo do painel administrativo
│   ├── admin.php              → login + painel (dashboard, agendamentos, clientes, serviços, loja)
│   └── api.php                → API JSON usada pelo painel (CALL nas procedures, CRUDs)
│
├── site/                      → tudo do site público
│   ├── index.php              → página principal (hero, loja, localização, agenda)
│   └── processar_agendamento.php → recebe o formulário de agendamento do site
│
├── includes/                  → arquivos de template/config reaproveitados
│   ├── conexao.php            → conexão com o MySQL (mysqli) — COLE SUAS CREDENCIAIS REAIS
│   ├── admin_auth.php         → login/sessão do admin — COLE SEU USUÁRIO/SENHA REAIS
│   ├── header.php / footer.php           → template do site público
│   └── head-admin.php / footer-admin.php → template do painel admin
│
├── assets/
│   ├── css/style.css          → CSS do projeto (cole o seu aqui)
│   ├── js/admin.js            → gerado automaticamente pelo build — não editar na mão
│   └── img/                   → fotos dos produtos da loja
│
├── src/
│   └── admin.ts               → código-fonte TypeScript do painel (edite este arquivo)
│
├── sql/
│   └── banco_completo.sql     → cole aqui o SQL completo do banco (tabelas, trigger, function, procedures, views)
│
├── package.json / tsconfig.json → configuração do TypeScript
└── README.md
```

## ⚠️ Antes de rodar

Os arquivos `includes/conexao.php` e `includes/admin_auth.php` vieram como
**modelo** — você precisa colar as credenciais de banco e o usuário/senha
do admin que já estavam no seu projeto original por cima dos valores de
exemplo.

Da mesma forma, `includes/header.php`, `footer.php`, `head-admin.php` e
`footer-admin.php` foram reconstruídos com base na estrutura observada
(Bootstrap 5 + Font Awesome) — confira se o visual bate com o que você
já tinha e ajuste o `assets/css/style.css` se necessário.

## Como rodar no XAMPP

1. Copie a pasta inteira para dentro de `htdocs`.
2. Abra o phpMyAdmin e importe `sql/banco_completo.sql` (o seu, com trigger,
   function, procedures, views e dados de teste).
3. Confira/edite `includes/conexao.php` com as credenciais do seu MySQL.
4. Acesse:
   - Site público: `http://localhost/<pasta>/site/index.php`
   - Painel admin: `http://localhost/<pasta>/admin/admin.php`

## Como editar o painel (TypeScript)

O painel usa `assets/js/admin.js`, gerado automaticamente a partir de
`src/admin.ts`. Nunca edite o `.js` direto.

```bash
npm install      # instala o compilador TypeScript (só na primeira vez)
npm run build    # compila src/admin.ts -> assets/js/admin.js
npm run watch    # recompila automaticamente a cada alteração salva
```

## Onde cada item da rubrica está implementado

- **Procedures**: `sp_obter_indicadores_dashboard()`, `sp_listar_agendamentos()` — chamadas via `CALL` em `admin/api.php`
- **Function**: `fn_calcular_faturamento_cliente()` — usada na ação `clientes` de `admin/api.php`
- **View**: `vw_relatorio_servicos` — usada na ação `ranking`
- **CRUDs**: Agendamentos, Clientes, Serviços, Produtos — todos em `admin/api.php`
- **Lógica TypeScript** (reduce/filter/map/tipagem/async): `src/admin.ts`
