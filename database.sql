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
  nivel            ENUM('junior','pleno','lider') NOT NULL DEFAULT 'junior',
  trabalha_semana  TINYINT(1) NOT NULL DEFAULT 1,   -- disponível em dias de semana (seg–sex)
  trabalha_sabado  TINYINT(1) NOT NULL DEFAULT 1,   -- disponível aos sábados
  trabalha_domingo TINYINT(1) NOT NULL DEFAULT 1,   -- disponível aos domingos
  ativo            TINYINT(1) NOT NULL DEFAULT 1,
  criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
  celular2     VARCHAR(20)  NULL,
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
--  Solicitações de troca de escala
--  Fluxo: pendente_colaborador -> (aceita) -> pendente_admin
--         -> (confirmada | recusada_admin); ou recusada_colaborador
-- ------------------------------------------------------------
CREATE TABLE trocas_escala (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  solicitante_id      INT NOT NULL,           -- colaborador que pediu a troca
  escala_origem_id    INT NOT NULL,           -- evento em que o solicitante está escalado
  alvo_id             INT NOT NULL,           -- colaborador convidado para a troca
  escala_alvo_id      INT NULL,               -- evento do alvo (NULL se ele não está escalado)
  status              ENUM('pendente_colaborador','pendente_admin','confirmada','recusada_colaborador','recusada_admin','cancelada')
                      NOT NULL DEFAULT 'pendente_colaborador',
  criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  respondido_em       DATETIME NULL,          -- quando o alvo aceitou/recusou
  decidido_em         DATETIME NULL,          -- quando o admin confirmou/recusou
  CONSTRAINT fk_tr_sol  FOREIGN KEY (solicitante_id)   REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_tr_alvo FOREIGN KEY (alvo_id)          REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_tr_eo   FOREIGN KEY (escala_origem_id) REFERENCES escalas(id)       ON DELETE CASCADE,
  CONSTRAINT fk_tr_ea   FOREIGN KEY (escala_alvo_id)   REFERENCES escalas(id)       ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Notificações (avisos na tela inicial)
--  destino: colaborador específico (colaborador_id) ou admin (para_admin=1)
-- ------------------------------------------------------------
CREATE TABLE notificacoes (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  colaborador_id INT NULL,                    -- destinatário (NULL quando for para admin)
  para_admin     TINYINT(1) NOT NULL DEFAULT 0,
  mensagem       VARCHAR(255) NOT NULL,
  troca_id       INT NULL,                    -- referência opcional à troca
  lida           TINYINT(1) NOT NULL DEFAULT 0,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_nt_colab FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_nt_troca FOREIGN KEY (troca_id)       REFERENCES trocas_escala(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Indisponibilidades marcadas pelos colaboradores
--  (eventos em que o colaborador NÃO estará disponível)
-- ------------------------------------------------------------
CREATE TABLE indisponibilidades (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  colaborador_id INT NOT NULL,
  escala_id      INT NOT NULL,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ind_colab FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_ind_escala FOREIGN KEY (escala_id)     REFERENCES escalas(id)       ON DELETE CASCADE,
  UNIQUE KEY uk_ind (colaborador_id, escala_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Controle de geração automática mensal (roda 1x por mês/ano)
-- ------------------------------------------------------------
CREATE TABLE geracao_mensal (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  mes         TINYINT NOT NULL,
  ano         SMALLINT NOT NULL,
  gerado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_gm (mes, ano)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  O usuário administrador padrão (admin / admin123) e os dados
--  de exemplo são criados executando seed.php no navegador.
-- ------------------------------------------------------------
