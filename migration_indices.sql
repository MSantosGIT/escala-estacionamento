-- ============================================================
--  MIGRATION OPCIONAL — Índices de performance
--  Acelera as consultas mais frequentes do sistema.
--  Execute uma vez no phpMyAdmin. Se algum índice já existir,
--  ignore a mensagem de "Duplicate key name".
-- ============================================================

USE escala_estacionamento;

-- telas de calendário/eventos/escalas filtram por data constantemente
CREATE INDEX idx_escalas_data ON escalas (data_evento);

-- dashboard busca notificações não lidas por colaborador
CREATE INDEX idx_notif_colab_lida ON notificacoes (colaborador_id, lida);
CREATE INDEX idx_notif_admin_lida ON notificacoes (para_admin, lida);

-- histórico de trocas ordena por criado_em e filtra por participante
CREATE INDEX idx_trocas_criado ON trocas_escala (criado_em);

-- busca de veículos por proprietário (ordenação da listagem)
CREATE INDEX idx_veic_prop ON veiculos (proprietario);

-- alertas: busca de não vistos por usuário
CREATE INDEX idx_alertas_dest_usuario ON alertas_destinatarios (usuario_id, visto_em);
