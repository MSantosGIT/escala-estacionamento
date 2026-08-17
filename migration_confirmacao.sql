-- ============================================================
--  MIGRATION — Confirmação de escala (ciente) + alertas clicáveis
--  Execute uma vez no phpMyAdmin no banco escala_estacionamento.
-- ============================================================

USE escala_estacionamento;

-- confirmação ("ciente") do colaborador para cada evento em que está escalado
CREATE TABLE IF NOT EXISTS escala_confirmacoes (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  escala_id      INT NOT NULL,
  colaborador_id INT NOT NULL,
  confirmado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_confirmacao (escala_id, colaborador_id),
  FOREIGN KEY (escala_id) REFERENCES escalas(id) ON DELETE CASCADE,
  FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- link opcional no alerta: ao clicar, leva direto à tela indicada
ALTER TABLE alertas
  ADD COLUMN IF NOT EXISTS link VARCHAR(255) NULL AFTER mensagem;

-- controle para o cron não disparar o mesmo aviso duas vezes
CREATE TABLE IF NOT EXISTS avisos_enviados (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  escala_id  INT NOT NULL,
  tipo       VARCHAR(30) NOT NULL,   -- 'confirmar_7d' | 'pendentes_24h'
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_aviso (escala_id, tipo),
  FOREIGN KEY (escala_id) REFERENCES escalas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
