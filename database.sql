-- ============================================================
--  SISTEMA DE ESCALA DE APOIO AO ESTACIONAMENTO
--  Banco de dados MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS escala_estacionamento
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE escala_estacionamento;

-- ------------------------------------------------------------
--  Usuários do sistema (Administrador / Colaborador)
-- ------------------------------------------------------------
CREATE TABLE usuarios (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120) NOT NULL,
  login         VARCHAR(60)  NOT NULL UNIQUE,
  senha         VARCHAR(255) NOT NULL,           -- hash bcrypt
  tipo          ENUM('administrador','colaborador') NOT NULL DEFAULT 'colaborador',
  colaborador_id INT NULL,                        -- vínculo quando tipo = colaborador
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Colaboradores
-- ------------------------------------------------------------
CREATE TABLE colaboradores (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  nome            VARCHAR(120) NOT NULL,
  celular         VARCHAR(20)  NOT NULL,
  nivel           ENUM('junior','pleno','lider') NOT NULL DEFAULT 'junior',
  trabalha_semana TINYINT(1) NOT NULL DEFAULT 1,   -- pode trabalhar dia de semana
  trabalha_fds    TINYINT(1) NOT NULL DEFAULT 1,   -- pode trabalhar final de semana
  ativo           TINYINT(1) NOT NULL DEFAULT 1,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE usuarios
  ADD CONSTRAINT fk_usuario_colab
  FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id)
  ON DELETE SET NULL;

-- ------------------------------------------------------------
--  Veículos
-- ------------------------------------------------------------
CREATE TABLE veiculos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  marca        VARCHAR(60)  NOT NULL,
  modelo       VARCHAR(60)  NOT NULL,
  cor          VARCHAR(40)  NOT NULL,
  placa        VARCHAR(10)  NOT NULL UNIQUE,
  foto         VARCHAR(255) NULL,
  proprietario VARCHAR(120) NOT NULL,
  celular      VARCHAR(20)  NOT NULL,
  origem       ENUM('sistema','publico') NOT NULL DEFAULT 'sistema',
  aprovado     TINYINT(1) NOT NULL DEFAULT 1,
  criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Eventos / Escalas
-- ------------------------------------------------------------
CREATE TABLE escalas (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  data_evento         DATE NOT NULL,
  dia                 TINYINT NOT NULL,
  mes                 TINYINT NOT NULL,
  ano                 SMALLINT NOT NULL,
  evento              VARCHAR(150) NOT NULL,
  horario_chegada     TIME NOT NULL,
  num_colaboradores   TINYINT NOT NULL DEFAULT 1,
  exige_lider         TINYINT(1) NOT NULL DEFAULT 0,
  status              ENUM('aberta','preenchida') NOT NULL DEFAULT 'aberta',
  criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_data_evento (data_evento, evento)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Vínculo de colaboradores escalados (histórico)
-- ------------------------------------------------------------
CREATE TABLE escala_colaboradores (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  escala_id      INT NOT NULL,
  colaborador_id INT NOT NULL,
  nivel_na_escala ENUM('junior','pleno','lider') NOT NULL,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ec_escala FOREIGN KEY (escala_id)
    REFERENCES escalas(id) ON DELETE CASCADE,
  CONSTRAINT fk_ec_colab FOREIGN KEY (colaborador_id)
    REFERENCES colaboradores(id) ON DELETE CASCADE,
  UNIQUE KEY uk_escala_colab (escala_id, colaborador_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  O usuário administrador padrão (admin / admin123) e os dados
--  de exemplo são criados executando seed.php no navegador.
-- ------------------------------------------------------------
