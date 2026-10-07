<?php

/**
 * Plugin Acessos da admissão - Catálogo de sistemas (listagem).
 */
if (!class_exists(Session::class)) {
    require_once __DIR__ . '/../../../inc/includes.php';
}

use GlpiPlugin\Admissaoacessos\AccessService;
use GlpiPlugin\Admissaoacessos\Sistema;

global $CFG_GLPI, $DB;

Session::checkLoginUser();
AccessService::exigirAcesso();

Html::header(__('Acessos da admissão', 'admissaoacessos'), '', 'tools', 'pluginadmissaoacessos');

echo "<div class='container-fluid' style='margin-top: 1em;'>";
echo "<div class='card'><div class='card-body'>";

echo "<div class='d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3'>";
echo "<div>";
echo "<h2 class='mb-0'>" . __('Catálogo de sistemas', 'admissaoacessos') . "</h2>";
echo "<div class='text-muted small'>" . __('Cada sistema liga um modelo de tarefa nativo ao texto que vai pro e-mail de boas-vindas.', 'admissaoacessos') . "</div>";
echo "</div>";
echo "<div class='d-flex gap-2'>";
echo "<a class='btn btn-outline-secondary btn-sm' href='" . $CFG_GLPI['root_doc'] . "/plugins/admissaoacessos/front/config.php'><i class='ti ti-settings'></i> " . __('Configuração', 'admissaoacessos') . "</a>";
echo "<a class='btn btn-primary btn-sm' href='" . $CFG_GLPI['root_doc'] . "/plugins/admissaoacessos/front/sistema.form.php'><i class='ti ti-plus'></i> " . __('Novo sistema', 'admissaoacessos') . "</a>";
echo "</div>";
echo "</div>";

$termoBusca = trim($_GET['busca'] ?? '');

$where = [];
if ($termoBusca !== '') {
    $where['name'] = ['LIKE', '%' . $termoBusca . '%'];
}

$totalGeral = countElementsInTable(Sistema::getTable());

$linhas = $DB->request([
    'FROM'  => Sistema::getTable(),
    'WHERE' => $where,
    'ORDER' => ['display_order ASC', 'name ASC'],
]);

echo "<div class='mb-3'>";
echo "<form method='get' class='d-flex gap-2'>";
echo "<input type='text' name='busca' class='form-control' value='" . htmlescape($termoBusca) . "' placeholder='" . __s('Buscar sistema...', 'admissaoacessos') . "'>";
echo Html::submit(_sx('button', 'Search'), ['class' => 'btn btn-outline-secondary']);
echo "</form>";
echo "</div>";

if (count($linhas) === 0) {
    echo "<div class='text-muted small'>" . __('Nenhum sistema encontrado.', 'admissaoacessos') . "</div>";
} else {
    $tipos = Sistema::getTiposAcesso();
    $totalExibido = 0;

    echo "<div class='d-flex flex-column gap-2'>";
    foreach ($linhas as $linha) {
        $totalExibido++;
        $urlEditar = $CFG_GLPI['root_doc'] . '/plugins/admissaoacessos/front/sistema.form.php?id=' . $linha['id'];
        $urlToggle = $CFG_GLPI['root_doc'] . '/plugins/admissaoacessos/front/sistema.form.php?id=' . $linha['id'] . '&toggle=1';

        $subtitulo = $tipos[$linha['access_type']] ?? $linha['access_type'];
        if ($linha['uses_project_action']) {
            $subtitulo .= ' · ' . __('pede Projeto e Ação', 'admissaoacessos');
        }

        echo "<div class='card mb-0'>";
        echo "<div class='card-body d-flex align-items-center justify-content-between gap-3 py-2'>";
        echo "<div>";
        echo "<a href='" . $urlEditar . "' class='fw-bold text-decoration-none'>" . htmlescape($linha['name']) . "</a>";
        echo "<div class='text-muted small'>" . htmlescape($subtitulo) . "</div>";
        echo "</div>";
        echo "<div class='d-flex align-items-center gap-3'>";
        echo "<a href='" . $urlToggle . "' class='badge rounded-pill " . ($linha['is_active'] ? 'text-bg-success' : 'text-bg-secondary') . "' style='text-decoration:none;'>" . ($linha['is_active'] ? __('Ativo', 'admissaoacessos') : __('Inativo', 'admissaoacessos')) . "</a>";
        echo "<a href='" . $urlEditar . "' class='btn btn-sm btn-outline-secondary' title='" . __s('Editar', 'admissaoacessos') . "'><i class='ti ti-pencil'></i></a>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
    echo "</div>";

    echo "<div class='text-muted small mt-2'>";
    echo sprintf(__('Mostrando %1$d de %2$d sistemas', 'admissaoacessos'), $totalExibido, $totalGeral);
    echo "</div>";
}

echo "</div></div>";
echo "</div>";

echo Html::script('plugins/admissaoacessos/public/js/busca-tabela.js');

Html::footer();
