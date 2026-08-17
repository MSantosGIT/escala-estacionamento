-- ============================================================
--  MIGRATION — Sistema de alertas com controle individual
--  Cada usuário (admin ou colaborador) marca seu próprio alerta
--  como visto, sem afetar os demais.
--  Execute uma vez no phpMyAdmin no banco escala_estacionamento.
-- ============================================================

USE escala_estacionamento;

-- tabela com a mensagem do alerta (criada uma vez por envio)
CREATE TABLE IF NOT EXISTS alertas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  mensagem    VARCHAR(120) NOT NULL,
  criado_por  INT NULL,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- destinatários do alerta: 1 linha por usuário que deve ver
-- visto_em != NULL significa que aquele usuário já dispensou
CREATE TABLE IF NOT EXISTS alertas_destinatarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  alerta_id   INT NOT NULL,
  usuario_id  INT NOT NULL,
  visto_em    DATETIME NULL,
  UNIQUE KEY uq_alerta_usuario (alerta_id, usuario_id),
  FOREIGN KEY (alerta_id) REFERENCES alertas(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
