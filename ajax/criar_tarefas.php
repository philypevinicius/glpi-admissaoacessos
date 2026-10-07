<?php

/**
 * Plugin Acessos da admissão - Cria as TicketTask nativas a partir dos
 * sistemas escolhidos no modal "Adicionar tarefas de acesso", reaproveitando
 * CommonITILTask::prepareInputForAdd() (chave `_tasktemplates_id`) pra copiar
 * conteúdo/categoria/responsável do TaskTemplate de cada sistema - exatamente
 * o que o formulário nativo faria, só que pra vários de uma vez.
 *
 * NÃO chamar Session::checkCSRF($_POST) aqui - mesmo motivo documentado
 * nos outros plugins desta base (o framework já valida).
 */
if (!class_exists(Session::class)) {
    require_once __DIR__ . '/../../../inc/includes.php';
}

use GlpiPlugin\Admissaoacessos\CatalogoService;
use GlpiPlugin\Admissaoacessos\Config;
use GlpiPlugin\Admissaoacessos\ExtracaoCampoService;
use GlpiPlugin\Admissaoacessos\Sistema;

global $DB;

Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

$ticketsId = (int) ($_POST['tickets_id'] ?? 0);
$sistemasIds = array_map('intval', (array) ($_POST['systems_ids'] ?? []));

if ($ticketsId <= 0 || empty($sistemasIds)) {
    http_response_code(400);
    echo json_encode(['erro' => __('Dados incompletos.', 'admissaoacessos')]);
    exit;
}

$ticket = new Ticket();
if (!$ticket->getFromDB($ticketsId)) {
    http_response_code(404);
    echo json_encode(['erro' => __('Chamado não encontrado.', 'admissaoacessos')]);
    exit;
}

// Mesmo dois gates do botão (categoria configurada + direito nativo na
// tarefa) - o botão só aparece quando passa, mas quem decide de verdade
// é sempre o servidor, nunca o que o JS mostrou ou escondeu.
$categoriasAdmissao = Config::getConfig()->categoriasIds();
if (empty($categoriasAdmissao) || !in_array((int) $ticket->fields['itilcategories_id'], $categoriasAdmissao, true)) {
    http_response_code(403);
    echo json_encode(['erro' => __('Este chamado não é de uma categoria de admissão configurada.', 'admissaoacessos')]);
    exit;
}

if (!$ticket->canAddItem('TicketTask')) {
    http_response_code(403);
    echo json_encode(['erro' => __('Sem permissão para criar tarefas neste chamado.', 'admissaoacessos')]);
    exit;
}

// Todos os campos padrão (Nome, CPF, Matrícula, Cargo, Telefone, E-mail,
// datas, Unidade, Empresa, Perfil a clonar) "que o GLPI já sabe" pra
// pré-preencher as tarefas novas - pega da tarefa mais ANTIGA do chamado
// que já tiver cada linha preenchida (normalmente a do AD, preenchida
// primeiro). Inclui CPF a pedido explícito do usuário - diferente do
// e-mail de boas-vindas (EmailBoasVindasService), que continua restrito
// a Nome/E-mail.
$rotulosPadrao = CatalogoService::rotulosPadrao();
$camposConhecidos = [];
foreach ($DB->request([
    'SELECT' => ['content'],
    'FROM'   => TicketTask::getTable(),
    'WHERE'  => ['tickets_id' => $ticketsId],
    'ORDER'  => ['date_creation ASC', 'id ASC'],
]) as $tarefaExistente) {
    $faltando = array_diff($rotulosPadrao, array_keys($camposConhecidos));
    if (empty($faltando)) {
        break;
    }
    $camposConhecidos += ExtracaoCampoService::todos((string) $tarefaExistente['content'], $faltando);
}

$criadas = 0;
$jaExistiam = 0;
$falhas = [];

foreach ($sistemasIds as $sistemaId) {
    $sistema = new Sistema();
    if (!$sistema->getFromDB($sistemaId) || !$sistema->fields['is_active'] || !$sistema->fields['tasktemplates_id']) {
        $falhas[] = $sistemaId;
        continue;
    }

    $jaTem = (bool) $DB->request([
        'FROM'  => TicketTask::getTable(),
        'WHERE' => [
            'tickets_id'       => $ticketsId,
            'tasktemplates_id' => $sistema->fields['tasktemplates_id'],
        ],
        'LIMIT' => 1,
    ])->current();

    if ($jaTem) {
        $jaExistiam++;
        continue;
    }

    $task = new TicketTask();
    $novoId = $task->add([
        'tickets_id'        => $ticketsId,
        '_tasktemplates_id' => $sistema->fields['tasktemplates_id'],
    ]);

    if ($novoId) {
        $criadas++;

        if (!empty($camposConhecidos)) {
            $conteudoPreenchido = CatalogoService::preencherCamposConhecidos(
                (string) $task->fields['content'],
                $camposConhecidos
            );
            if ($conteudoPreenchido !== $task->fields['content']) {
                $task->update(['id' => $novoId, 'content' => $conteudoPreenchido]);
            }
        }
    } else {
        $falhas[] = $sistemaId;
    }
}

echo json_encode([
    'criadas'      => $criadas,
    'ja_existiam'  => $jaExistiam,
    'falhas'       => $falhas,
]);
