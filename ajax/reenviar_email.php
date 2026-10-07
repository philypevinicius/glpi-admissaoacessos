<?php

/**
 * Plugin Acessos da admissão - Reenvio manual do e-mail de boas-vindas
 * (botão "Reenviar e-mail de acessos") - ignora a trava de "já enviado"
 * de propósito, mas passa pela mesma validação de sempre.
 *
 * NÃO chamar Session::checkCSRF($_POST) aqui - mesmo motivo documentado
 * nos outros plugins desta base.
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
    echo json_encode(['enviado' => false, 'motivo' => __('Sem permissão.', 'admissaoacessos')]);
    exit;
}

$ticketsId = (int) ($_POST['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($ticketsId <= 0 || !$ticket->getFromDB($ticketsId)) {
    http_response_code(404);
    echo json_encode(['enviado' => false, 'motivo' => __('Chamado não encontrado.', 'admissaoacessos')]);
    exit;
}

echo json_encode(EmailBoasVindasService::reenviarManualmente($ticket));
