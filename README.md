# Sistema de Escala de Apoio ao Estacionamento

Sistema em **PHP + MySQL** com paleta laranja suave para gerenciar a escala
da equipe de apoio ao estacionamento.

## Recursos

- **Login** com dois perfis: Administrador e Colaborador.
- **Cadastro de colaboradores**: nome, celular, nível (Júnior, Pleno, Líder),
  disponibilidade em **Dias de Semana, Sábado e Domingo** (separadamente),
  e criação opcional de login/senha.
- **Cadastro de veículos**: marca, modelo, cor, placa, foto, proprietário,
  celular e um 2º telefone de contato opcional.
- **Escalas**: dia/mês/ano, evento, horário de chegada, nº de colaboradores e
  exigência de líder.
- **Geração de domingos do ano**: cria automaticamente o *Culto de Celebração*
  às **17:45** em todos os domingos do ano escolhido.
- **Geração automática de escala** seguindo as regras de negócio.
- **Histórico** de participações por colaborador.
- **Gestão de disponibilidade**: até o dia 20 de cada mês, o colaborador marca em
  `disponibilidade.php` os eventos do mês seguinte em que **não** poderá servir.
  No 1º acesso de um administrador a partir do dia 20, o sistema **gera a escala do
  mês seguinte automaticamente** (uma única vez), respeitando as indisponibilidades
  e as regras de composição. O administrador também pode **gerar manualmente** a
  qualquer momento (botão na tela de Escalas), inclusive antes do dia 20. Eventos sem
  gente suficiente entram como parciais para ajuste manual. Um alerta na tela inicial
  avisa a todos que a agenda já está montada.
- **Ajuste de escala pelo Admin** (`admin_escala.php`): o administrador revê todos
  os eventos do mês com seus escalados e pode **substituir, adicionar ou remover**
  colaboradores (escalados ou não) em cada evento. O sistema **avisa** quando o ajuste
  fere a regra de nível (A1↔A1; A2/A3 entre si) ou usa alguém que marcou
  indisponibilidade, mas deixa o admin confirmar. Os colaboradores envolvidos são
  notificados a cada ajuste.
- **Indisponibilidade pelo Admin** (`admin_indisponibilidade.php`): o administrador
  seleciona um colaborador, navega pelo calendário do mês e clica nos eventos para
  marcar a indisponibilidade dele (sem a trava do dia 20). Após salvar, pode
  **regerar a escala do mês** para aplicar as mudanças.
- **Contadores** de registros em cada tela de cadastro (colaboradores, veículos,
  usuários e escalas do mês).
- **Calendário imprimível**: visão mensal em grade com os eventos, horário,
  vagas e a equipe escalada por cor de nível, pronta para impressão (A4 paisagem).
- **Autocadastro público de veículos** (`cadastro_publico.php`): página fora do
  sistema, sem login, para qualquer pessoa registrar o próprio veículo. Os envios
  ficam **pendentes de aprovação** pelo administrador na tela de Veículos.
  Proteções incluídas: token CSRF, honeypot anti-bot, limite de envios por sessão,
  validação de placa (padrão antigo e Mercosul), verificação de imagem real e
  bloqueio de placas duplicadas.
- **Busca rápida de veículos** (`busca.php`): consulta por placa, proprietário,
  marca ou modelo em tempo real, com link de WhatsApp para o proprietário.
  Inclui **leitura da placa pela câmera do celular** via OCR no próprio navegador
  (Tesseract.js), sem custo de API. A placa detectada vai para o campo de busca
  e pode ser corrigida antes de pesquisar.
- **Importação de veículos por CSV** (`importar_veiculos.php`): botão na tela de
  Veículos para cadastrar vários de uma vez a partir de uma planilha. Reconhece as
  colunas MARCA, MODELO, COR, PLACA, CONDUTOR (ou PROPRIETARIO) e CELULAR em qualquer
  ordem, normaliza e valida as placas, ignora colunas extras, trata duplicatas
  (ignorar ou atualizar) e gera um relatório com inseridos, atualizados, ignorados
  e linhas sem placa válida.

> **Câmera no celular:** o botão "Foto da placa" abre a câmera traseira apenas em
> **HTTPS** (ou `localhost`). Em HTTP comum, o navegador abre a galeria de fotos —
> isso é uma exigência de segurança dos navegadores, não do sistema. Para uso em
> celular na rede, sirva o site por HTTPS.

## Regras de geração implementadas

1. Eventos com **3 colaboradores ou mais** exigem composição mínima:
   **1 líder + 1 (ou mais) pleno + 1 (ou mais) júnior**.
2. **Preferência de 1 evento por colaborador no mês** (quem tem menos eventos
   no mês entra primeiro).
3. **Prioriza quem está há mais tempo sem ser escalado**.
4. Respeita a **disponibilidade** por tipo de dia: dias de semana, sábado e domingo.
5. **Grava o histórico** de cada escalação por colaborador.

## Instalar como app (celular e tablet)

O sistema é um PWA: pode ser instalado na tela inicial e abre em tela cheia, com
aparência de aplicativo. Requer acesso por **HTTPS** (ou `localhost`); em rede local
sem HTTPS, o ícone ainda pode ser adicionado, mas alguns recursos do app não ativam.

- **Android (Chrome):** abra o sistema, toque no aviso "Instalar o app" que aparece no
  topo, ou no menu ⋮ → "Adicionar à tela inicial".
- **iPhone/iPad (Safari):** toque em Compartilhar → "Adicionar à Tela de Início".
- **Computador (Chrome/Edge):** ícone de instalar na barra de endereço.

Os ícones ficam em `assets/icons/`, e a aparência (nome, cores) é definida em
`manifest.json`. O `sw.js` (service worker) habilita a instalação e um cache leve dos
assets.

## Instalação

1. Copie a pasta para o diretório do servidor (ex.: `htdocs` / `www`).
2. Crie o banco importando `database.sql`:
   ```bash
   mysql -u root -p < database.sql
   ```
3. Ajuste as credenciais em `config/db.php` se necessário.
4. Acesse `http://localhost/seed.php` **uma vez** para criar o admin
   (`admin` / `admin123`) e colaboradores de exemplo. **Depois apague `seed.php`.**
5. Dê permissão de escrita à pasta `uploads/`.
6. Acesse `login.php` e entre.

> **Já tinha o banco instalado antes do autocadastro?** Rode também
> `migration_cadastro_publico.sql` para adicionar as colunas `origem` e `aprovado`.
> Para a disponibilidade em três dias (Semana/Sábado/Domingo), rode também
> `migration_disponibilidade.sql`.

## Cadastro público de veículos

Compartilhe o link `cadastro_publico.php` (também disponível com botão de copiar
na tela de Veículos). Qualquer pessoa pode registrar o próprio veículo; o cadastro
fica pendente até o administrador aprovar em **Veículos → Aguardando aprovação**.

## Uso rápido

1. Em **Escalas → Ferramentas automáticas**, clique em **Criar domingos** para o ano.
2. Cadastre/ajuste **Colaboradores**.
3. Clique em **Gerar escala automática** (ou **Gerar** em um evento específico).

## Geração da escala do mês seguinte

A escala do mês seguinte é montada de duas formas (respeitando as indisponibilidades
marcadas pelos colaboradores até o dia 20):

1. **Automática no acesso:** no 1º acesso de qualquer administrador a partir do dia 20,
   o sistema gera a escala do mês seguinte uma única vez.
2. **Manual (antecipada):** o administrador pode clicar em **"📅 Gerar mês seguinte"**
   na tela de Escalas → Ferramentas automáticas, a qualquer momento, inclusive antes do
   dia 20. Útil para antecipar a montagem. Após gerar, o mês não é regerado automaticamente.

## Cron (opcional)

O cron auxiliar `cron/gerar_escalas.php` preenche escalas abertas futuras a qualquer
momento, caso queira um reforço agendado:

```
0 6 * * 1  php /caminho/escala_estacionamento/cron/gerar_escalas.php
```

## Estrutura

```
config/db.php              conexão PDO
includes/functions.php     sessão, auth, helpers, CSRF
includes/escala_engine.php motor de geração (regras)
includes/header/footer.php layout
login / logout / dashboard
colaboradores / veiculos / usuarios / escalas / historico
assets/css/style.css       paleta laranja suave
uploads/                   fotos dos veículos
cron/gerar_escalas.php      execução agendada
seed.php                   dados iniciais (apagar após uso)
database.sql               schema
```
