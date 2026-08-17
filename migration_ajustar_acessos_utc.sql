-- ============================================================
--  MIGRATION OPCIONAL — corrige acessos antigos gravados em UTC
--  Execute uma única vez, APENAS se os últimos acessos atuais
--  estão aparecendo 3 horas adiantados.
--  Após executar isso, o problema não acontece mais (pois o PHP
--  agora alinha o MySQL ao fuso de Brasília automaticamente).
-- ============================================================

USE escala_estacionamento;

UPDATE usuarios
   SET ultimo_acesso = DATE_SUB(ultimo_acesso, INTERVAL 3 HOUR)
 WHERE ultimo_acesso IS NOT NULL;
