-- ============================================================
--  MIGRATION — Ordenação manual de colaboradores na escala
--  Execute uma vez no phpMyAdmin no banco escala_estacionamento.
-- ============================================================

USE escala_estacionamento;

-- coluna de posição (ordem manual) — null = usa a ordem padrão (nível + nome)
ALTER TABLE escala_colaboradores
  ADD COLUMN IF NOT EXISTS posicao INT NULL AFTER nivel_na_escala;

-- coluna de ordem global do colaborador — null = usa nível + nome
ALTER TABLE colaboradores
  ADD COLUMN IF NOT EXISTS ordem_padrao INT NULL;
