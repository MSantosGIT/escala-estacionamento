-- =============================================================
--  APOIO EXTERNO · GESTÃO DE ESCALA
--  Instalação completa do banco de dados
--  Execute uma única vez no phpMyAdmin (ou via linha de comando).
--  ATENÇÃO: este script APAGA o banco "escala_estacionamento" se já existir.
-- =============================================================

DROP DATABASE IF EXISTS escala_estacionamento;
CREATE DATABASE escala_estacionamento DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE escala_estacionamento;

-- -------------------------------------------------------------
--  Colaboradores
-- -------------------------------------------------------------
CREATE TABLE colaboradores (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  nome              VARCHAR(120) NOT NULL,
  celular           VARCHAR(20),
  nivel             ENUM('junior','pleno','lider') NOT NULL DEFAULT 'junior',
  trabalha_semana   TINYINT(1) NOT NULL DEFAULT 1,
  trabalha_sabado   TINYINT(1) NOT NULL DEFAULT 1,
  trabalha_domingo  TINYINT(1) NOT NULL DEFAULT 1,
  ativo             TINYINT(1) NOT NULL DEFAULT 1,
  criado_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------------
--  Veículos (cadastrados por admin ou pelo público)
-- -------------------------------------------------------------
CREATE TABLE veiculos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  marca        VARCHAR(60) NOT NULL,
  modelo       VARCHAR(60) NOT NULL,
  cor          VARCHAR(30),
  placa        VARCHAR(10) NOT NULL,
  foto         VARCHAR(120),
  proprietario VARCHAR(120),
  celular      VARCHAR(20),
  celular2     VARCHAR(20),
  origem       ENUM('sistema','publico') NOT NULL DEFAULT 'sistema',
  aprovado     TINYINT(1) NOT NULL DEFAULT 1,
  criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_placa (placa)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
--  Usuários do sistema (admin / colaborador)
-- -------------------------------------------------------------
CREATE TABLE usuarios (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  nome            VARCHAR(120) NOT NULL,
  login           VARCHAR(60)  NOT NULL,
  senha           VARCHAR(255) NOT NULL,
  tipo            ENUM('administrador','colaborador') NOT NULL DEFAULT 'colaborador',
  colaborador_id  INT NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_login (login),
  CONSTRAINT fk_usr_colab FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------
--  Escalas (eventos)
-- -------------------------------------------------------------
CREATE TABLE escalas (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  data_evento        DATE NOT NULL,
  dia                TINYINT NOT NULL,
  mes                TINYINT NOT NULL,
  ano                SMALLINT NOT NULL,
  evento             VARCHAR(120) NOT NULL,
  horario_chegada    TIME NOT NULL,
  num_colaboradores  TINYINT NOT NULL DEFAULT 3,
  exige_lider        TINYINT(1) NOT NULL DEFAULT 0,
  status             ENUM('aberta','preenchida') NOT NULL DEFAULT 'aberta',
  criado_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_evento_data (data_evento, evento, horario_chegada)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
--  Participações dos colaboradores em cada escala
-- -------------------------------------------------------------
CREATE TABLE escala_colaboradores (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  escala_id       INT NOT NULL,
  colaborador_id  INT NOT NULL,
  nivel_na_escala ENUM('junior','pleno','lider') NOT NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ec_escala FOREIGN KEY (escala_id)      REFERENCES escalas(id)       ON DELETE CASCADE,
  CONSTRAINT fk_ec_colab  FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
  UNIQUE KEY uk_ec (escala_id, colaborador_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
--  Trocas de escala entre colaboradores
-- -------------------------------------------------------------
CREATE TABLE trocas_escala (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  escala_id       INT NOT NULL,
  solicitante_id  INT NOT NULL,
  alvo_id         INT NOT NULL,
  status          ENUM('pendente_colaborador','pendente_admin','confirmada',
                       'recusada_colaborador','recusada_admin','cancelada') NOT NULL DEFAULT 'pendente_colaborador',
  motivo          VARCHAR(255),
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tr_escala FOREIGN KEY (escala_id)      REFERENCES escalas(id)       ON DELETE CASCADE,
  CONSTRAINT fk_tr_solic  FOREIGN KEY (solicitante_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_tr_alvo   FOREIGN KEY (alvo_id)        REFERENCES colaboradores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------
--  Notificações (para colaborador ou para admin)
-- -------------------------------------------------------------
CREATE TABLE notificacoes (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  colaborador_id  INT NULL,
  para_admin      TINYINT(1) NOT NULL DEFAULT 0,
  mensagem        VARCHAR(255) NOT NULL,
  lida            TINYINT(1) NOT NULL DEFAULT 0,
  troca_id        INT NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_nt_colab FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_nt_troca FOREIGN KEY (troca_id)       REFERENCES trocas_escala(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------
--  Indisponibilidades dos colaboradores em eventos específicos
-- -------------------------------------------------------------
CREATE TABLE indisponibilidades (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  colaborador_id INT NOT NULL,
  escala_id      INT NOT NULL,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ind_colab  FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_ind_escala FOREIGN KEY (escala_id)      REFERENCES escalas(id)       ON DELETE CASCADE,
  UNIQUE KEY uk_ind (colaborador_id, escala_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
--  Controle de geração mensal automática (roda 1x por mês/ano)
-- -------------------------------------------------------------
CREATE TABLE geracao_mensal (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  mes       TINYINT NOT NULL,
  ano       SMALLINT NOT NULL,
  gerado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_gm (mes, ano)
) ENGINE=InnoDB;

-- =============================================================
--  Dados iniciais
-- =============================================================

-- Administrador padrão
-- login: admin   senha: admin123   (TROQUE APÓS O PRIMEIRO ACESSO)
INSERT INTO usuarios (nome, login, senha, tipo) VALUES
('Administrador', 'admin', '$2b$10$UKJY.6eJt8d6.L1viYoyz.mbQuwTnpg4iIz6tjqhCFUqc.UQr/eA.', 'administrador');

-- =============================================================
--  Pronto! O sistema já pode ser acessado por dashboard.php
--  Próximos passos recomendados:
--   1. Entrar com admin / admin123 e TROCAR A SENHA em Usuários
--   2. Cadastrar os colaboradores em Colaboradores
--   3. Em Escalas, usar "Gerar domingos do ano" para criar os cultos
-- =============================================================
