-- ============================================================
--  MIGRATION — Check-in de chegada + fotos do evento
--  Execute uma vez no phpMyAdmin no banco escala_estacionamento.
-- ============================================================

USE escala_estacionamento;

-- check-in de chegada dos colaboradores escalados
CREATE TABLE IF NOT EXISTS evento_checkins (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  escala_id      INT NOT NULL,
  colaborador_id INT NOT NULL,
  checkin_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_checkin (escala_id, colaborador_id),
  FOREIGN KEY (escala_id) REFERENCES escalas(id) ON DELETE CASCADE,
  FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- fotos do evento (máximo de 3, controlado pela aplicação)
CREATE TABLE IF NOT EXISTS evento_fotos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  escala_id   INT NOT NULL,
  arquivo     VARCHAR(255) NOT NULL,
  enviado_por INT NULL,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (escala_id) REFERENCES escalas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
