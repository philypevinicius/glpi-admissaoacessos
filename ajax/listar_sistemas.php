<?php

/**
 * Plugin Acessos da admissão - Lista os sistemas ativos pro modal do
 * botão "Adicionar tarefas de acesso" (qualquer usuário logado pode
 * listar; a criação de tarefa em si é que exige o direito nativo no
 * chamado, conferido em criar_tarefas.php).
 */
if (!class_exists(Session::class)) {
    require_once __DIR__ . '/../../../inc/includes.php';
}

use GlpiPlugin\Admissaoacessos\Sistema;

global $DB;

Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

$linhas = $DB->request([
    'SELECT' => ['id', 'name'],
    'FROM'   => Sistema::getTable(),
    'WHERE'  => ['is_active' => 1],
    'ORDER'  => ['display_order ASC', 'name ASC'],
]);

$resultado = [];
foreach ($linhas as $linha) {
    $resultado[] = ['id' => (int) $linha['id'], 'name' => $linha['name']];
}

echo json_encode($resultado);
