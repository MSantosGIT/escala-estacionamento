-- ============================================================
--  MIGRATION — Registro de último acesso por usuário
--  Execute uma vez no phpMyAdmin no banco escala_estacionamento.
-- ============================================================

USE escala_estacionamento;

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS ultimo_acesso DATETIME NULL AFTER colaborador_id;
