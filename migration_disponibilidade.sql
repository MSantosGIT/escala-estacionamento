-- ============================================================
--  MIGRATION — disponibilidade em 3 campos
--  (Dias de Semana, Sábado e Domingo)
--  Execute se o banco JÁ foi criado com o campo trabalha_fds.
-- ============================================================
USE escala_estacionamento;

-- novos campos
ALTER TABLE colaboradores
  ADD COLUMN trabalha_sabado  TINYINT(1) NOT NULL DEFAULT 1 AFTER trabalha_semana,
  ADD COLUMN trabalha_domingo TINYINT(1) NOT NULL DEFAULT 1 AFTER trabalha_sabado;

-- migra o valor antigo de fim de semana para sábado e domingo
UPDATE colaboradores SET trabalha_sabado = trabalha_fds, trabalha_domingo = trabalha_fds;

-- remove o campo antigo (opcional — descomente se desejar)
-- ALTER TABLE colaboradores DROP COLUMN trabalha_fds;
