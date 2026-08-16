# ADS — Barbearia Adrian Souza

Site + painel administrativo em PHP, MySQL e TypeScript.
Nome da pasta do projeto: **ads** (URL simples: `http://localhost/ads/`).

## Estrutura de pastas

```
ads/
├── index.php                 → site público
├── admin.php                 → login + painel administrativo
├── api.php                   → API JSON usada pelo painel (todas as ações: CALL nas procedures, CRUDs)
├── processar_agendamento.php → recebe o formulário de agendamento do site público
│
├── includes/                 → partes de PHP reaproveitadas (nada de HTML repetido)
│   ├── conexao.php           → conexão com o MySQL (mysqli)
│   ├── admin_auth.php        → login/sessão do admin
│   ├── header.php / footer.php           → topo/rodapé do site público
│   └── head-admin.php / footer-admin.php → topo/rodapé do painel
│
├── assets/
│   ├── css/style.css         → todo o CSS do projeto
│   ├── js/admin.js           → JS do painel (gerado automaticamente — não editar na mão)
│   └── img/                  → fotos dos produtos da loja (Pomada.jpg, shampo3.jpg, etc.)
│
├── src/
│   └── admin.ts               → código-fonte TypeScript do painel (é este arquivo que se edita)
│
├── sql/
│   ├── banco_completo.sql                 → cria o banco do zero (tabelas, trigger, function, procedures, views, dados — já com os caminhos assets/img/ certos)
│   ├── atualizar_procedure_agendamentos.sql → só a procedure nova, sem apagar dados
│   └── atualizar_imagens_produtos.sql       → se você já tinha o banco populado com caminho antigo "img/...", roda isso pra migrar pra assets/img/
│
├── package.json / package-lock.json / tsconfig.json → configuração do TypeScript
└── README.md
```

## Como rodar no XAMPP

1. Copie a pasta `ads` inteira para dentro de `htdocs` (fica `htdocs/ads/`).
2. Abra o phpMyAdmin, aba **Importar**, e importe `sql/banco_completo.sql`
   (cria o banco `barbearia_adrian_souza` do zero, já com trigger, function,
   procedures, views, dados de teste e os caminhos de imagem certos).
3. Confira o usuário/senha do MySQL em `includes/conexao.php`.
4. Acesse `http://localhost/ads/index.php` (site) ou
   `http://localhost/ads/admin.php` (painel — login
   `adrian` / senha `1234`, definidos em `includes/admin_auth.php`).

## Como editar o painel (TypeScript)

O painel (`admin.php`) usa `assets/js/admin.js`, mas esse arquivo **é gerado
automaticamente** a partir de `src/admin.ts`. Nunca edite o `.js` direto.

```bash
npm install      # instala o compilador TypeScript (só na primeira vez)
npm run build    # compila src/admin.ts -> assets/js/admin.js
npm run watch    # recompila automaticamente a cada alteração salva
```

## Onde cada item da rubrica está implementado

Veja `RUBRICA_MAPA.md` (enviado junto) para o mapeamento completo
critério → arquivo, incluindo trigger, function, procedures, views,
CRUDs, tipagem TS, reduce/filter/map e fluxo assíncrono.
