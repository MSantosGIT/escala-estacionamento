-- ============================================================
--  MIGRATION — Gestão de disponibilidade + geração mensal automática
--  Execute se o banco JÁ foi criado sem essas tabelas.
-- ============================================================
USE escala_estacionamento;

CREATE TABLE IF NOT EXISTS indisponibilidades (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  colaborador_id INT NOT NULL,
  escala_id      INT NOT NULL,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ind_colab FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
  CONSTRAINT fk_ind_escala FOREIGN KEY (escala_id)     REFERENCES escalas(id)       ON DELETE CASCADE,
  UNIQUE KEY uk_ind (colaborador_id, escala_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS geracao_mensal (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  mes         TINYINT NOT NULL,
  ano         SMALLINT NOT NULL,
  gerado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_gm (mes, ano)
) ENGINE=InnoDB;
