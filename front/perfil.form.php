<?php

/**
 * Plugin Acessos da admissão - Grava o direito "plugin_admissaoacessos_config"
 * de um perfil. Direito único (READ = tem acesso ao catálogo/configuração).
 *
 * NÃO chamar Session::checkCSRF($_POST) aqui - mesmo motivo documentado
 * nos outros plugins desta base (o framework de ação já cuida disso no
 * fluxo de abas de Perfil).
 */
if (!class_exists(Session::class)) {
    require_once __DIR__ . '/../../../inc/includes.php';
}

use GlpiPlugin\Admissaoacessos\AccessService;

Session::checkRight(Profile::$rightname, UPDATE);

$profilesId = (int) ($_POST['profiles_id'] ?? 0);
if ($profilesId <= 0) {
    Html::back();
    exit;
}

$valor = isset($_POST['gerenciar']) ? READ : 0;

ProfileRight::updateProfileRights($profilesId, [
    AccessService::RIGHTNAME => $valor,
]);

Html::back();
