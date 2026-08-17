-- ============================================================
--  MIGRATION — Nova categoria de contagem: Gramado
--  Execute uma vez no phpMyAdmin no banco escala_estacionamento.
-- ============================================================

USE escala_estacionamento;

ALTER TABLE carros_evento
  ADD COLUMN IF NOT EXISTS qtd_gramado INT NOT NULL DEFAULT 0 AFTER qtd_anexo;
