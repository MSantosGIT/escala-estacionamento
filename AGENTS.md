# AGENTS.md — Contexto do projeto (manter SEMPRE atualizado)

Sistema **APE — Apoio Externo · Gestão de Escala de Estacionamento** (PHP + MySQL/MariaDB,
PWA). Gerencia escala da equipe de apoio ao estacionamento: colaboradores, veículos,
escalas, disponibilidade, trocas, check-in, checklist, contagem de carros por evento,
alertas e relatório anual.

## Repositório e deploy

- `origin` = `https://github.com/MSantosGIT/escala-estacionamento` — branch `main`.
- **Fluxo de atualização** (obrigatório após cada alteração aprovada):
  1. Alterar e testar local → `php -l` nos arquivos alterados.
  2. Testar no navegador local (`http://localhost/escala_estacionamento`).
  3. `git add` + `git commit` + `git push origin main`.
  4. No servidor de produção: `git pull origin main` (via SSH — dados em `CONTEXTO_LOCAL.md`).
  5. Validar lint + HTTP (resposta 200/302 esperada sem sessão).
- **NUNCA existir 2 máquinas na mesma versão desatualizada**: local ≡ GitHub ≡ servidor.
- Arquivos ignorados (não versionar): `config/db.php`, `uploads/`, `seed.php`, `*.log`,
  `CONTEXTO_LOCAL.md`. Segredos vivem só nesses arquivos — **não checar segredos no repo público**.

## Ambientes

- **Local (dev):** `C:\xampp\htdocs\escala_estacionamento` — XAMPP (Apache + MariaDB 10.4 + PHP).
  Banco `escala_estacionamento` (root sem senha). URL: `http://localhost/escala_estacionamento`.
- **Produção:** VPS `45.164.243.235` — Debian 13, Apache, PHP 8.4, MariaDB 11.8.
  Site: `/var/www/instituto/public_html/escala` → `https://apoioexterno.mooo.com/escala`.
  Banco `escala_estacionamento` (usuário próprio; senha no `config/db.php` do servidor).

## Banco de dados

- Schema base em `database.sql` + migrations `migration_*.sql` (todas idempotentes).
- As ~19 tabelas atuais já estão aplicadas tanto no local quanto na produção.
- **Mudanças de schema:** criar `migration_*.sql`, aplicar no local E na produção,
  e rodar novamente apenas o necessário (idempotente).

## Cron de produção (já instalado no crontab do root)

```
0 6 * * 1  php /var/www/instituto/public_html/escala/cron/gerar_escalas.php   # segunda 06:00
0 8 * * *  php /var/www/instituto/public_html/escala/cron_confirmacoes.php      # diário 08:00
```
Log: `/var/log/escala_cron.log`.

## Convenções

- PHP, sem comentários desnecessários; fuso `America/Sao_Paulo` (PHP + MySQL `-03:00`).
- UTF-8; senhas com `password_hash`/`password_verify`; CSRF em formulários.
- Credenciais/uso: ver `CONTEXTO_LOCAL.md` (gitignored).

## REGRA OBRIGATÓRIA

Ao final de cada tarefa que altere ambiente, senhas, URLs, commits ou infraestrutura:
**atualizar este `AGENTS.md` e o `CONTEXTO_LOCAL.md` imediatamente** — commit/push se houver
mudança no AGENTS.md. Nunca deixar o contexto desatualizado entre sessões.