# Sistema de Escala de Apoio ao Estacionamento

Sistema em **PHP + MySQL** com paleta laranja suave para gerenciar a escala
da equipe de apoio ao estacionamento.

## Recursos

- **Login** com dois perfis: Administrador e Colaborador.
- **Cadastro de colaboradores**: nome, celular, nível (Júnior, Pleno, Líder),
  disponibilidade na semana e no fim de semana, e criação opcional de login/senha.
- **Cadastro de veículos**: marca, modelo, cor, placa, foto, proprietário e celular.
- **Escalas**: dia/mês/ano, evento, horário de chegada, nº de colaboradores e
  exigência de líder.
- **Geração de domingos do ano**: cria automaticamente o *Culto de Colaboração*
  às **17:45** em todos os domingos do ano escolhido.
- **Geração automática de escala** seguindo as regras de negócio.
- **Histórico** de participações por colaborador.
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
4. Respeita a **disponibilidade** (dia de semana × fim de semana).
5. **Grava o histórico** de cada escalação por colaborador.

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

## Cadastro público de veículos

Compartilhe o link `cadastro_publico.php` (também disponível com botão de copiar
na tela de Veículos). Qualquer pessoa pode registrar o próprio veículo; o cadastro
fica pendente até o administrador aprovar em **Veículos → Aguardando aprovação**.

## Uso rápido

1. Em **Escalas → Ferramentas automáticas**, clique em **Criar domingos** para o ano.
2. Cadastre/ajuste **Colaboradores**.
3. Clique em **Gerar escala automática** (ou **Gerar** em um evento específico).

## Cron (opcional)

```
0 6 * * 1  php /caminho/cron/gerar_escalas.php
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
