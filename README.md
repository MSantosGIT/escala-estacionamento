# APE — Apoio Externo · Gestão de Escala de Estacionamento

Sistema em **PHP + MySQL** (paleta laranja suave) para gerenciar a escala da equipe
de apoio ao estacionamento da igreja. É um **PWA**: instala na tela inicial do
celular/tablet e abre como aplicativo.

## Perfis de acesso

- **Administrador**: controle total (equipe, escalas, ajustes, veículos, alertas, relatórios).
- **Colaborador**: vê suas escalas, confirma participação, faz check-in, pede trocas,
  marca indisponibilidade e consulta histórico/relatórios.

Níveis da equipe exibidos como **A1 (líder), A2 (pleno), A3 (júnior)**.

## Recursos

### Escala
- **Cadastro de colaboradores**: nome, celular, nível (A1/A2/A3), disponibilidade em
  **Dias de Semana, Sábado e Domingo** (separadamente) e criação opcional de login/senha.
- **Escalas**: dia/mês/ano, evento, horário de chegada, nº de colaboradores e exigência de líder.
- **Geração de domingos do ano**: cria automaticamente o *Culto de Celebração* às **17:45**
  em todos os domingos do ano escolhido.
- **Geração automática de escala** seguindo as regras de negócio (ver abaixo).
- **Geração do mês seguinte**: até o dia 20 o colaborador marca os eventos em que **não**
  poderá servir (`disponibilidade.php`). No 1º acesso de um administrador a partir do dia 20,
  o sistema gera a escala do mês seguinte **automaticamente** (uma única vez). O admin também
  pode gerar manualmente a qualquer momento (botão na tela de Escalas). Eventos sem gente
  suficiente entram como parciais para ajuste manual. Um aviso na tela inicial informa que a
  agenda já está montada.
- **Ajuste de escala pelo Admin** (`admin_escala.php`): substituir, adicionar ou remover
  escalados em cada evento. O sistema **avisa** quando o ajuste fere a regra de nível
  (A1↔A1; A2/A3 entre si) ou usa alguém indisponível, mas permite confirmar. Os envolvidos
  são notificados a cada ajuste.
- **Indisponibilidade pelo Admin** (`admin_indisponibilidade.php`): seleciona um colaborador,
  navega pelo calendário do mês e clica nos eventos para marcar indisponibilidade (sem a trava
  do dia 20), podendo regerar o mês depois.
- **Ordenação manual** dos escalados em cada evento (`migration_ordenacao.sql`).
- **Calendário imprimível** (`calendario.php`): visão mensal em grade com eventos, horário,
  vagas e equipe escalada por cor de nível (A4 paisagem).

### Confirmação e execução do evento
- **Ciente da escala** (`confirmar_escala.php`): o escalado confirma presença a partir de
  7 dias antes do evento; o admin acompanha a equipe escalada dos eventos futuros — quem já
  deu o ciente permanece na lista com um **✓ verde** na frente do nome (e ⏳ nos pendentes),
  com contagem de confirmados por evento.
- **Cron de confirmações** (`cron_confirmacoes.php`): diariamente avisa os escalados a
  confirmar (7 dias antes) e alerta os admins sobre não confirmados a 48h do evento
  (com deduplicação via `avisos_enviados`).
- **Check-in** (`checkin.php`): registro de chegada a partir de 30 min antes do horário,
  com upload de até **3 fotos** do evento.
- **Encerramento com checklist**: no fim do evento, o responsável responde o checklist de
  encerramento (itens gerenciáveis em `checklist_itens.php`) e finaliza o evento.
- **Eventos finalizados** (`eventos_finalizados.php`: consulta de check-ins, checklists,
  fotos e dados dos eventos encerrados.

### Carros por evento
- **Contagem de carros** (`carros_evento.php`) por evento em **4 categorias**:
  Estacionamento, Anexo, Externo e Gramado.

### Trocas de escala
- Fluxo completo (`trocas.php`): colaborador solicita → convidado aceita/recusa →
  admin confirma/recusa. Regras de compatibilidade de nível: A1 troca com A1/A2;
  A2 com A1/A2/A3; A3 com A2/A3. Notificações automáticas em cada etapa e
  histórico completo com filtro de status (`historico_trocas.php`).

### Comunicação
- **Notificações no dashboard** para colaboradores específicos ou para o admin.
- **Alertas** (`enviar_alerta.php`): admin publica avisos no painel inicial, com escolha
  de destinatários e histórico.

### Veículos
- **Cadastro de veículos**: marca, modelo, cor, placa, foto, proprietário, celular e
  2º telefone opcional. Contadores de registros nas telas de cadastro.
- **Autocadastro público** (`cadastro_publico.php`): sem login, fica **pendente de aprovação**
  pelo admin em Veículos. Proteções: token CSRF, honeypot anti-bot, limite de envios por
  sessão, validação de placa (antiga e Mercosul), verificação de imagem real e bloqueio
  de placas duplicadas.
- **Busca rápida** (`busca.php`): por placa, proprietário, marca ou modelo em tempo real,
  com link de WhatsApp e **leitura da placa pela câmera** via OCR no navegador
  (Tesseract.js, sem custo de API).
- **Importação por CSV** (`importar_veiculos.php`): reconhece MARCA, MODELO, COR, PLACA,
  CONDUTOR/PROPRIETARIO e CELULAR em qualquer ordem; valida placas, trata duplicatas
  (ignorar/atualizar) e gera relatório de inseridos/atualizados/ignorados/inválidos.

### Gestão e relatórios
- **Usuários** (`usuarios.php`) e **auditoria de acessos** (`acessos.php`, com último acesso).
- **Histórico** de participações (`historico.php`) e de indisponibilidades
  (`historico_disponibilidade.php`).
- **Relatório anual** (`relatorio_anual.php`): matriz colaborador × mês, imprimível.
- **Página Sobre** (`sobre.php`) e ajuda contextual "?" em todas as telas.
- **Segurança**: sessão expira após 30 min de inatividade, CSRF em todos os formulários,
  headers de segurança HTTP, senhas em hash bcrypt.

> **Câmera no celular:** o OCR de placa usa a câmera traseira apenas em **HTTPS**
> (ou `localhost`). Em HTTP comum, o navegador abre a galeria de fotos — exigência dos
> navegadores, não do sistema.

## Regras de geração implementadas

1. Eventos com **3 colaboradores ou mais** exigem composição mínima:
   **1 líder + 1 (ou mais) pleno + 1 (ou mais) júnior**.
2. **Preferência de 1 evento por colaborador no mês** (quem tem menos eventos no mês entra primeiro).
3. **Prioriza quem está há mais tempo sem ser escalado**.
4. Respeita a **disponibilidade** por tipo de dia: semana, sábado e domingo, e as
   **indisponibilidades por evento** marcadas até o dia 20.
5. **Grava o histórico** de cada escalação por colaborador.

## Instalar como app (celular e tablet)

- **Android (Chrome):** toque no aviso "Instalar o app" ou menu ⋮ → "Adicionar à tela inicial".
- **iPhone/iPad (Safari):** Compartilhar → "Adicionar à Tela de Início".
- **Computador (Chrome/Edge):** ícone de instalar na barra de endereço.

Ícones em `assets/icons/`; aparência definida em `manifest.json`; o `sw.js` habilita a
instalação e cache leve dos assets (páginas PHP sempre buscam a versão mais recente).

## Instalação

1. Copie a pasta para o diretório do servidor (ex.: `htdocs` / `www`).
2. Crie o banco importando `database.sql`:
   ```bash
   mysql -u root -p < database.sql
   ```
3. Ajuste as credenciais em `config/db.php` se necessário (padrão XAMPP: root sem senha).
4. Acesse `http://localhost/seed.php?run=1` **uma vez** para criar o admin
   (`admin` / `admin123`) e dados de exemplo. **Depois apague `seed.php`.**
5. Dê permissão de escrita à pasta `uploads/`.
6. Acesse `login.php` e entre.

> **Instalação existente:** se o banco foi criado antes de algum recurso novo, rode as
> migrations correspondentes (todas idempotentes por tema):
> `migration_cadastro_publico.sql`, `migration_disponibilidade.sql`,
> `migration_disponibilidade_eventos.sql`, `migration_trocas.sql`, `migration_confirmacao.sql`,
> `migration_checkin.sql`, `migration_checklist.sql`, `migration_carros_evento.sql`,
> `migration_gramado.sql`, `migration_alertas.sql`, `migration_ultimo_acesso.sql`,
> `migration_celular2.sql`, `migration_ordenacao.sql`, `migration_indices.sql`,
> `migration_renomear_evento.sql`, `migration_ajustar_acessos_utc.sql`.

## Cron (opcional)

```
# preenche escalas abertas futuras
0 6 * * 1  php /caminho/escala_estacionamento/cron/gerar_escalas.php

# lembretes de confirmação (7 dias antes) e alerta de não confirmados (48h antes)
0 7 * * *  php /caminho/escala_estacionamento/cron_confirmacoes.php
```

## Uso rápido

1. Em **Escalas → Ferramentas automáticas**, clique em **Criar domingos** para o ano.
2. Cadastre/ajuste **Colaboradores**.
3. Clique em **Gerar escala automática** (ou **📅 Gerar mês seguinte**).
4. Revise em **Ajustar escala**, acompanhe cientes/check-ins e conte carros por evento.

## Estrutura

```
config/db.php               conexão PDO (fuso America/Sao_Paulo)
includes/functions.php      sessão, auth, helpers, CSRF, headers de segurança
includes/escala_engine.php  motor de geração (regras de negócio)
includes/trocas.php         lógica de trocas e notificações
includes/ajuda_conteudo.php ajuda contextual das telas
includes/header|footer.php  layout (sidebar, PWA, flash, máscara de telefone)
login / logout / index / dashboard / sobre / trocar_senha
colaboradores / usuarios / acessos / veiculos / importar_veiculos
escalas / calendario / lista_eventos / admin_escala / admin_indisponibilidade / disponiveis
disponibilidade / historico_disponibilidade
confirmar_escala / checkin / eventos_finalizados / checklist_itens / carros_evento
trocas / historico_trocas
busca / buscar_veiculo / cadastro_publico
enviar_alerta / historico / relatorio_anual
cron/gerar_escalas.php      execução agendada (reforço)
cron_confirmacoes.php       execução agendada (lembretes)
seed.php                    dados iniciais (apagar após uso)
database.sql                schema base
migration_*.sql             evoluções incrementais do banco
manifest.json / sw.js       PWA (instalação e cache)
uploads/                    fotos dos veículos e dos eventos (.htaccess protege a pasta)
```
