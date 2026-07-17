<?php
// ============================================================
//  CONTEÚDO DE AJUDA CONTEXTUAL — por tela (nome do arquivo)
//  Usado pelo botão flutuante "❓ Ajuda" em includes/header.php.
//  Cada entrada: titulo, intro (opcional), passos[], dica (opcional).
// ============================================================

function ajudaConteudo(): array {
    return [

        'dashboard.php' => [
            'titulo' => '🏠 Início',
            'intro'  => 'O painel inicial reúne os avisos mais importantes do seu dia a dia no sistema.',
            'passos' => [
                'Notificações de troca (pedidos, aceites, aprovações) aparecem em destaque, em verde ou vermelho.',
                'Alertas do administrador aparecem em vermelho vibrante. Leia e toque em "✓ Ok" para dispensar — cada colaborador marca o seu próprio alerta como lido.',
                'Use o menu lateral (ou o ícone ☰ no celular) para acessar qualquer outra tela.',
            ],
            'dica' => 'Confira o painel Início sempre que abrir o sistema — é onde os avisos mais importantes aparecem primeiro.',
        ],

        'checkin.php' => [
            'titulo' => '📍 Check-in de chegada',
            'intro'  => 'Confirme sua chegada no local do evento. A janela de check-in abre 30 minutos antes do horário e fecha 1h30 depois do início.',
            'passos' => [
                'A tela mostra os eventos de hoje em que você está escalado.',
                'Dentro da janela de horário, toque em "Confirmar minha chegada" — o sistema grava a hora exata.',
                'Acompanhe quem da equipe já chegou (✓ com horário) e quem ainda não (—).',
                'Colaborador principal (primeiro da escala): pode enviar até 3 fotos do evento direto pela câmera do celular.',
                'O líder preenche o checklist de encerramento (portões, cones, rádios, coletes) ao final e pode deixar uma observação. Depois de encerrado, o registro não pode mais ser alterado.',
            ],
            'dica' => 'O encerramento do evento é definitivo — confira tudo antes de confirmar.',
        ],

        'busca.php' => [
            'titulo' => '🔍 Buscar veículo',
            'intro'  => 'Encontre rapidamente o dono de um veículo pela placa, nome do proprietário ou modelo.',
            'passos' => [
                'Digite parte da placa, nome ou modelo no campo de busca — os resultados aparecem instantaneamente.',
                'Ou toque em "📷 Foto da placa" para usar a câmera. O sistema lê a placa automaticamente.',
                'Depois da foto, ajuste o quadro sobre a placa antes de tocar em "Ler placa" — isso melhora muito a precisão.',
                'Confira o resultado: dados do proprietário, telefone e foto do veículo aparecem na tela.',
            ],
            'dica' => 'Boa iluminação e a placa bem enquadrada tornam a leitura automática muito mais confiável.',
        ],

        'escalas.php' => [
            'titulo' => '📋 Escalas',
            'intro'  => 'Veja a escala mensal completa: quem está escalado em cada evento.',
            'passos' => [
                'Use as setas ou o seletor de mês/ano para navegar entre os períodos.',
                'Cada evento mostra a equipe escalada, com os nomes coloridos por nível (líder, pleno, júnior).',
                'Localize os dias em que você está escalado para se programar com antecedência.',
            ],
        ],

        'calendario.php' => [
            'titulo' => '📅 Calendário',
            'intro'  => 'Visualização em formato de calendário mensal, com os eventos marcados em cada dia.',
            'passos' => [
                'Veja de relance quais dias do mês têm eventos com escala.',
                'Toque em um dia para ver os detalhes do evento e da equipe escalada.',
            ],
        ],

        'lista_eventos.php' => [
            'titulo' => '🎫 Eventos',
            'intro'  => 'Cada evento vira um "ticket" visual, com data, horário e a equipe escalada.',
            'passos' => [
                'Escolha o mês/ano para ver os tickets dos eventos daquele período.',
                'Os nomes da equipe aparecem coloridos por nível — fácil identificar quem é líder, pleno ou júnior.',
                'Toque em "Imprimir" para gerar uma versão limpa em papel, sem os menus do sistema.',
            ],
        ],

        'carros_evento.php' => [
            'titulo' => '📈 Carros por evento',
            'intro'  => 'Registre a movimentação de veículos de cada evento, separada por área: Principal, Anexo, Gramado e Externo.',
            'passos' => [
                'Toque em "➕ Registrar movimento", escolha o evento e informe a quantidade em cada área.',
                'O total de veículos é somado automaticamente enquanto você digita.',
                'Se o evento já tiver um registro, salvar de novo atualiza os números.',
                'Consulte o histórico logo abaixo, com os registros de todos os eventos já lançados.',
            ],
        ],

        'trocas.php' => [
            'titulo' => '🔁 Trocas de escala',
            'intro'  => 'Precisa faltar num evento em que está escalado? Solicite uma troca com outro colaborador direto pelo sistema.',
            'passos' => [
                'Escolha o evento em que você está escalado e selecione um colega compatível.',
                'O colega recebe a solicitação e pode aceitar ou recusar.',
                'Se aceitar, a troca vai para o administrador aprovar — só depois disso ela é confirmada de fato.',
                'Acompanhe o status (pendente, aceita, aprovada ou recusada) direto nesta tela.',
            ],
            'dica' => 'Solicite trocas com a maior antecedência possível, para dar tempo de aprovação.',
        ],

        'historico_trocas.php' => [
            'titulo' => '📜 Histórico de trocas',
            'intro'  => 'Acompanhe o histórico completo de todas as trocas em que você participou.',
            'passos' => [
                'As trocas aparecem da mais recente para a mais antiga.',
                'Cada card mostra a linha do tempo: quando foi solicitada, quando o colega respondeu e quando o admin decidiu.',
                'Use o filtro no topo para ver só confirmadas, pendentes, recusadas ou canceladas.',
            ],
        ],

        'disponibilidade.php' => [
            'titulo' => '✅ Disponibilidade',
            'intro'  => 'Avise com antecedência os dias em que não poderá ser escalado.',
            'passos' => [
                'Selecione as datas em que estará indisponível (viagem, compromisso, etc.).',
                'Salve — o sistema não vai mais te escalar automaticamente nesses dias.',
                'Você pode remover uma indisponibilidade a qualquer momento, se os planos mudarem.',
            ],
            'dica' => 'Quanto mais cedo você registrar, melhor a distribuição da escala para todo mundo.',
        ],

        'historico.php' => [
            'titulo' => '🕘 Histórico',
            'intro'  => 'Consulte escalas de meses anteriores.',
            'passos' => [
                'Navegue por meses passados e veja em quais eventos você atuou.',
                'Útil para conferir sua participação ao longo do tempo.',
            ],
        ],

        'historico_disponibilidade.php' => [
            'titulo' => '📆 Histórico de disponibilidade',
            'intro'  => 'Consulte todos os registros de indisponibilidade, organizados de três formas diferentes.',
            'passos' => [
                'Use as abas no topo para trocar a visão: "Por mês" (agrupado pelo mês do evento), "Por evento" (quem marcou indisponibilidade em cada evento) e "Por colaborador" (histórico de cada pessoa — só para administradores).',
                'Cada registro mostra o evento, a data e o horário exato em que a indisponibilidade foi marcada no sistema.',
                'Como colaborador, você vê apenas os seus próprios registros; administradores veem os de toda a equipe.',
            ],
        ],

        'relatorio_anual.php' => [
            'titulo' => '📊 Relatório anual',
            'intro'  => 'Resumo consolidado da sua participação ao longo do ano.',
            'passos' => [
                'Veja o total de eventos, presença e trocas no ano selecionado.',
                'Use o seletor de ano para consultar períodos anteriores.',
            ],
        ],

        'trocar_senha.php' => [
            'titulo' => '🔑 Trocar senha',
            'intro'  => 'Altere a senha da sua conta a qualquer momento.',
            'passos' => [
                'Informe a senha atual e a nova senha (mínimo de 6 caracteres).',
                'Confirme para salvar — na próxima vez que entrar, use a nova senha.',
            ],
            'dica' => 'Por segurança, o sistema desconecta automaticamente após 30 minutos de inatividade.',
        ],

        'sobre.php' => [
            'titulo' => 'ℹ️ Sobre',
            'intro'  => 'Informações do sistema e contato do desenvolvedor.',
            'passos' => [
                'Veja os dados do sistema e a versão em uso.',
                'Use o e-mail ou WhatsApp exibidos para relatar problemas ou sugerir melhorias.',
            ],
        ],

        // ---------------- telas de administração ----------------

        'colaboradores.php' => [
            'titulo' => '👥 Colaboradores (Admin)',
            'intro'  => 'Gerencie o cadastro da equipe de apoio.',
            'passos' => [
                'Toque em "➕ Novo colaborador" para cadastrar, informando nome, nível e celular.',
                'Use ✏️ para editar um colaborador existente ou 🗑️ para inativar.',
                'O nível (líder, pleno, júnior) define a cor do nome nas escalas e a ordem de prioridade.',
            ],
        ],

        'admin_escala.php' => [
            'titulo' => '🛠️ Ajustar escala (Admin)',
            'intro'  => 'Gere e ajuste manualmente a escala do mês.',
            'passos' => [
                'Escolha o mês/ano e toque em "Gerar escala" para a distribuição automática.',
                'Arraste para reordenar quem aparece primeiro (define o colaborador principal/líder do evento).',
                'Ajustes manuais substituem a geração automática apenas para os eventos alterados.',
            ],
        ],

        'admin_indisponibilidade.php' => [
            'titulo' => '🚫 Indisponibilidade (Admin)',
            'intro'  => 'Veja e gerencie as indisponibilidades de todos os colaboradores.',
            'passos' => [
                'Consulte quem está indisponível em cada data antes de gerar a escala.',
                'Você pode remover uma indisponibilidade em nome do colaborador, se necessário.',
            ],
        ],

        'veiculos.php' => [
            'titulo' => '🚗 Veículos (Admin)',
            'intro'  => 'Gerencie o cadastro de veículos autorizados.',
            'passos' => [
                'Use a busca para localizar rapidamente um veículo por placa, proprietário ou modelo.',
                'Toque em "🔗 Link de autocadastro" para gerar um link que os próprios membros podem usar para se cadastrar.',
                'Use "📥 Importar CSV" para cadastrar vários veículos de uma vez.',
                'Veículos de autocadastro pendentes aparecem destacados para sua aprovação.',
            ],
        ],

        'usuarios.php' => [
            'titulo' => '👤 Usuários (Admin)',
            'intro'  => 'Gerencie os logins de acesso ao sistema.',
            'passos' => [
                'Toque em "➕ Novo usuário" para criar um login, vinculando (ou não) a um colaborador.',
                'Use ✏️ para editar nome/login/tipo — a edição não altera a senha.',
                'Use "🔑 Resetar senha" para definir uma nova senha para o usuário.',
            ],
        ],

        'acessos.php' => [
            'titulo' => '🕒 Acessos (Admin)',
            'intro'  => 'Veja quando cada usuário acessou o sistema pela última vez.',
            'passos' => [
                'A lista mostra nome, tipo e o último acesso, com uma etiqueta colorida indicando há quanto tempo.',
                'Use a busca para localizar um usuário específico rapidamente.',
            ],
        ],

        'enviar_alerta.php' => [
            'titulo' => '📢 Enviar alerta (Admin)',
            'intro'  => 'Envie um aviso que aparece na tela inicial dos colaboradores.',
            'passos' => [
                'Escreva a mensagem (até 100 caracteres) e escolha o destino: todos, colaboradores selecionados, e/ou administradores.',
                'Cada destinatário marca o alerta como lido individualmente — o alerta some só para quem confirmou.',
                'Confira o histórico de alertas enviados logo abaixo, com o status de leitura de cada pessoa.',
            ],
        ],

        'checklist_itens.php' => [
            'titulo' => '📋 Checklist (Admin)',
            'intro'  => 'Gerencie os itens do checklist de encerramento de eventos.',
            'passos' => [
                'Toque em "➕ Novo item" para adicionar um item ao checklist.',
                'Use ✏️ para editar a descrição/ordem, ou 🗑️ para remover um item.',
                'Alterações valem para os próximos encerramentos — encerramentos já feitos não mudam.',
            ],
        ],

        'importar_veiculos.php' => [
            'titulo' => '📥 Importar veículos (Admin)',
            'intro'  => 'Cadastre vários veículos de uma vez a partir de um arquivo CSV.',
            'passos' => [
                'Prepare a planilha seguindo o modelo indicado na tela (placa, proprietário, modelo, etc.).',
                'Envie o arquivo e confira o resumo antes de confirmar a importação.',
            ],
        ],

    ];
}

/**
 * Retorna o conteúdo de ajuda da tela atual, ou null se não houver.
 */
function ajudaDaTelaAtual(): ?array {
    $arquivo = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $todas = ajudaConteudo();
    return $todas[$arquivo] ?? null;
}
