-- ============================================================
--  MIGRATION — Carros por evento com 3 categorias
--  Estacionamento, Anexo e Externo (o total é a soma dos três).
--  Execute uma vez no phpMyAdmin no banco escala_estacionamento.
-- ============================================================

USE escala_estacionamento;

-- cria a tabela já com as três categorias (caso ainda não exista)
CREATE TABLE IF NOT EXISTS carros_evento (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  escala_id       INT NOT NULL,
  qtd_estacionamento INT NOT NULL DEFAULT 0,
  qtd_anexo       INT NOT NULL DEFAULT 0,
  qtd_externo     INT NOT NULL DEFAULT 0,
  registrado_por  INT NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME NULL,
  UNIQUE KEY uq_escala (escala_id),
  FOREIGN KEY (escala_id) REFERENCES escalas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- se a tabela já existia (versão anterior), adiciona as colunas novas.
-- Ignore mensagens de "Duplicate column" se já tiverem sido criadas.
ALTER TABLE carros_evento
  ADD COLUMN IF NOT EXISTS qtd_estacionamento INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS qtd_anexo INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS qtd_externo INT NOT NULL DEFAULT 0;
