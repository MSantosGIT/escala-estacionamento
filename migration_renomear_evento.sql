-- ============================================================
--  MIGRATION — renomeia o evento de domingo
--  "Culto de Colaboração" -> "Culto de Celebração"
--  Execute para atualizar as escalas JÁ cadastradas.
-- ============================================================
USE escala_estacionamento;

UPDATE escalas
   SET evento = 'Culto de Celebração'
 WHERE evento = 'Culto de Colaboração';
