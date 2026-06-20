-- ============================================================
--  MIGRATION — execute se o banco JÁ foi criado antes do
--  recurso de cadastro público de veículos.
-- ============================================================
USE escala_estacionamento;

ALTER TABLE veiculos
  ADD COLUMN origem   ENUM('sistema','publico') NOT NULL DEFAULT 'sistema' AFTER celular,
  ADD COLUMN aprovado TINYINT(1) NOT NULL DEFAULT 1 AFTER origem;
