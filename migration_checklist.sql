-- ============================================================
--  MIGRATION — Checklist de encerramento de eventos
--  Execute uma vez no phpMyAdmin no banco escala_estacionamento.
-- ============================================================

USE escala_estacionamento;

-- itens padrão do checklist (gerenciados pelo admin)
CREATE TABLE IF NOT EXISTS checklist_itens (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  descricao VARCHAR(200) NOT NULL,
  ordem     INT NOT NULL DEFAULT 0,
  ativo     TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- registro do encerramento de cada evento (1 por evento, imutável)
CREATE TABLE IF NOT EXISTS evento_encerramentos (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  escala_id     INT NOT NULL,
  encerrado_por INT NULL,
  observacao    TEXT NULL,
  encerrado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_encerramento (escala_id),
  FOREIGN KEY (escala_id) REFERENCES escalas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- checklist preenchido no encerramento (snapshot do texto de cada item)
CREATE TABLE IF NOT EXISTS evento_checklist (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  encerramento_id INT NOT NULL,
  item_descricao  VARCHAR(200) NOT NULL,
  marcado         TINYINT NOT NULL DEFAULT 0,
  FOREIGN KEY (encerramento_id) REFERENCES evento_encerramentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- itens padrão iniciais (só insere se a tabela estiver vazia)
INSERT INTO checklist_itens (descricao, ordem)
SELECT * FROM (
  SELECT 'Portão Veículos do anexo fechado' AS descricao, 1 AS ordem UNION ALL
  SELECT 'Portão Pedestres, acesso ao Anexo fechado', 2 UNION ALL
  SELECT 'Cones e Sinalizadores guardados', 3 UNION ALL
  SELECT 'Lanternas (3) e Rádios (4) colocar para recarregar', 4 UNION ALL
  SELECT 'Coletes guardados nos respectivos cabides (XG) (GG)', 5
) t
WHERE NOT EXISTS (SELECT 1 FROM checklist_itens);
