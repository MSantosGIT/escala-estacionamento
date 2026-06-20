-- ============================================================
--  MIGRATION — Gestão de trocas de escala + notificações
--  Execute se o banco JÁ foi criado sem essas tabelas.
-- ============================================================
USE escala_estacionamento;

CREATE TABLE IF NOT EXISTS trocas_escala (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  solicitante_id      INT NOT NULL,
  escala_origem_id    INT NOT NULL,
  alvo_id             INT NOT NULL,
  escala_alvo_id      INT NULL,
  status              ENUM('pendente_colaborador','pendente_admin','confirmada','recusada_colaborador','recusada_admin','cancelada')
                      NOT NULL DEFAULT 'pendente_colaborador',
  criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  respondido_em       DATETIME NULL,
  decidido_em         DATETIME NULL,
  CONSTRAINT fk_tr_sol  FOREIGN KEY (solicitante_id)   REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_tr_alvo FOREIGN KEY (alvo_id)          REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_tr_eo   FOREIGN KEY (escala_origem_id) REFERENCES escalas(id)       ON DELETE CASCADE,
  CONSTRAINT fk_tr_ea   FOREIGN KEY (escala_alvo_id)   REFERENCES escalas(id)       ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notificacoes (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  colaborador_id INT NULL,
  para_admin     TINYINT(1) NOT NULL DEFAULT 0,
  mensagem       VARCHAR(255) NOT NULL,
  troca_id       INT NULL,
  lida           TINYINT(1) NOT NULL DEFAULT 0,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_nt_colab FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_nt_troca FOREIGN KEY (troca_id)       REFERENCES trocas_escala(id) ON DELETE CASCADE
) ENGINE=InnoDB;
