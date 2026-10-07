<?php

/**
 * Plugin Acessos da admissão - Pré-visualização do e-mail de boas-vindas
 * (botão "Ver e-mail"), sem enviar nem gravar nada.
 */
if (!class_exists(Session::class)) {
    require_once __DIR__ . '/../../../inc/includes.php';
}

use GlpiPlugin\Admissaoacessos\AccessService;
use GlpiPlugin\Admissaoacessos\EmailBoasVindasService;

Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

if (!AccessService::canAccess()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'motivo' => __('Sem permissão.', 'admissaoacessos')]);
    exit;
}

$ticketsId = (int) ($_GET['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($ticketsId <= 0 || !$ticket->getFromDB($ticketsId)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'motivo' => __('Chamado não encontrado.', 'admissaoacessos')]);
    exit;
}

echo json_encode(EmailBoasVindasService::prever($ticket));
