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

$linhas = $DB->request([
    'FROM'  => Sistema::getTable(),
    'ORDER' => ['display_order ASC', 'name ASC'],
]);

if (count($linhas) === 0) {
    echo "<div class='text-muted small'>" . __('Nenhum sistema cadastrado ainda.', 'admissaoacessos') . "</div>";
} else {
    echo "<div class='mb-3'>";
    echo "<input type='text' class='form-control' data-busca-tabela='aa-tabela-sistemas' placeholder='" . __s('Pesquisar...', 'admissaoacessos') . "'>";
    echo "</div>";
    echo "<table class='table table-striped' id='aa-tabela-sistemas'>";
    echo "<thead><tr>";
    echo "<th>" . __('Ordem', 'admissaoacessos') . "</th>";
    echo "<th>" . __('Sistema', 'admissaoacessos') . "</th>";
    echo "<th>" . __('Tipo de acesso', 'admissaoacessos') . "</th>";
    echo "<th>" . __('Modelo de tarefa', 'admissaoacessos') . "</th>";
    echo "<th>" . __('Ativo', 'admissaoacessos') . "</th>";
    echo "<th></th>";
    echo "</tr></thead><tbody>";

    $tipos = Sistema::getTiposAcesso();

    foreach ($linhas as $linha) {
        $urlEditar = $CFG_GLPI['root_doc'] . '/plugins/admissaoacessos/front/sistema.form.php?id=' . $linha['id'];
        $urlToggle = $CFG_GLPI['root_doc'] . '/plugins/admissaoacessos/front/sistema.form.php?id=' . $linha['id'] . '&toggle=1';

        $nomeModelo = $linha['tasktemplates_id']
            ? Dropdown::getDropdownName('glpi_tasktemplates', $linha['tasktemplates_id'])
            : "<span class='text-danger'>" . __('Nenhum', 'admissaoacessos') . "</span>";

        echo "<tr" . ($linha['is_active'] ? "" : " class='text-muted'") . ">";
        echo "<td>" . (int) $linha['display_order'] . "</td>";
        echo "<td><a href='" . $urlEditar . "'>" . htmlescape($linha['name']) . "</a></td>";
        echo "<td>" . htmlescape($tipos[$linha['access_type']] ?? $linha['access_type']) . "</td>";
        echo "<td>" . $nomeModelo . "</td>";
        echo "<td><a href='" . $urlToggle . "' class='badge rounded-pill " . ($linha['is_active'] ? 'text-bg-success' : 'text-bg-secondary') . "' style='text-decoration:none;'>" . ($linha['is_active'] ? __('Sim', 'admissaoacessos') : __('Não', 'admissaoacessos')) . "</a></td>";
        echo "<td class='text-end'><a href='" . $urlEditar . "' class='btn btn-sm btn-outline-secondary'><i class='ti ti-pencil'></i></a></td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

echo "</div></div>";
echo "</div>";

echo Html::script('plugins/admissaoacessos/public/js/busca-tabela.js');

Html::footer();
