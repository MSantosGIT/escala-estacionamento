-- ============================================================
--  MIGRATION — segundo telefone de contato do veículo
--  Execute se o banco JÁ foi criado sem o campo celular2.
-- ============================================================
USE escala_estacionamento;

ALTER TABLE veiculos
  ADD COLUMN celular2 VARCHAR(20) NULL AFTER celular;
