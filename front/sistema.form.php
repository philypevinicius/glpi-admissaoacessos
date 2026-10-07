<?php

/**
 * Plugin Acessos da admissão - Novo/editar/excluir sistema do catálogo.
 */
if (!class_exists(Session::class)) {
    require_once __DIR__ . '/../../../inc/includes.php';
}

use GlpiPlugin\Admissaoacessos\AccessService;
use GlpiPlugin\Admissaoacessos\CatalogoService;
use GlpiPlugin\Admissaoacessos\Sistema;

global $CFG_GLPI;

Session::checkLoginUser();
AccessService::exigirAcesso();

$urlLista = $CFG_GLPI['root_doc'] . '/plugins/admissaoacessos/front/sistema.php';
$urlForm  = $CFG_GLPI['root_doc'] . '/plugins/admissaoacessos/front/sistema.form.php';

$item = new Sistema();
$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (isset($_GET['delete']) && $id > 0) {
    $item->getFromDB($id);
    if (!$item->podeExcluir()) {
        Session::addMessageAfterRedirect(__('Esse sistema já foi usado em algum envio - só é possível desativar, não excluir.', 'admissaoacessos'), false, ERROR);
    } else {
        $item->delete(['id' => $id], true);
        Session::addMessageAfterRedirect(__('Sistema excluído.', 'admissaoacessos'), false, INFO);
    }
    Html::redirect($urlLista);
    exit;
}

if (isset($_GET['toggle']) && $id > 0) {
    $item->getFromDB($id);
    $item->update(['id' => $id, 'is_active' => $item->fields['is_active'] ? 0 : 1]);
    Html::redirect($urlLista);
    exit;
}

/**
 * Monta o array de input comum ao add/update a partir do $_POST - usado
 * tanto no salvamento direto quanto depois de confirmar a sobrescrita do
 * modelo de tarefa.
 */
function plugin_admissaoacessos_input_do_post(): array
{
    return [
        'name'                => trim($_POST['name'] ?? ''),
        'tasktemplates_id'    => (int) ($_POST['tasktemplates_id'] ?? 0),
        'access_type'         => $_POST['access_type'] ?? Sistema::TIPO_LIVRE,
        'email_text'          => trim($_POST['email_text'] ?? ''),
        'display_order'       => (int) ($_POST['display_order'] ?? 0),
        'uses_project_action' => isset($_POST['uses_project_action']) ? 1 : 0,
        'is_active'           => isset($_POST['is_active']) ? 1 : 0,
        'entities_id'         => (int) ($_POST['entities_id'] ?? 0),
        'is_recursive'        => isset($_POST['is_recursive']) ? 1 : 0,
    ];
}

if (isset($_POST['add']) || isset($_POST['update'])) {
    $input = plugin_admissaoacessos_input_do_post();

    if ($input['name'] === '') {
        Session::addMessageAfterRedirect(__('Preencha o nome do sistema.', 'admissaoacessos'), false, ERROR);
        Html::back();
        exit;
    }

    $querCriarAtualizarModelo = isset($_POST['_criar_atualizar_modelo']);

    if ($querCriarAtualizarModelo && $input['tasktemplates_id'] > 0 && !isset($_POST['_confirmar_sobrescrita'])) {
        $template = new TaskTemplate();
        $corpoAtual = '';
        if ($template->getFromDB($input['tasktemplates_id'])) {
            $corpoAtual = (string) $template->fields['content'];
        }
        $corpoNovo = CatalogoService::corpoParaHtml(
            CatalogoService::montarCorpoPadrao($input['name'], (bool) $input['uses_project_action'])
        );

        if (trim(strip_tags($corpoAtual)) !== trim(strip_tags($corpoNovo))) {
            // Mostra a diferença e pede confirmação antes de sobrescrever
            // o modelo de tarefa já existente.
            Html::header(__('Acessos da admissão', 'admissaoacessos'), '', 'tools', 'pluginadmissaoacessos');
            echo "<div class='container-fluid' style='margin-top: 1em; max-width: 900px;'>";
            echo "<div class='card'><div class='card-body'>";
            echo "<h2>" . __('Confirmar sobrescrita do modelo de tarefa', 'admissaoacessos') . "</h2>";
            echo "<p class='text-muted'>" . __('O modelo de tarefa vinculado já tem um texto diferente do padrão. Confirme que quer sobrescrever.', 'admissaoacessos') . "</p>";
            echo "<div class='row'>";
            echo "<div class='col-6'><strong>" . __('Texto atual', 'admissaoacessos') . "</strong><div class='border rounded p-2 mt-1' style='white-space:pre-wrap;'>" . $corpoAtual . "</div></div>";
            echo "<div class='col-6'><strong>" . __('Texto novo (padrão)', 'admissaoacessos') . "</strong><div class='border rounded p-2 mt-1' style='white-space:pre-wrap;'>" . $corpoNovo . "</div></div>";
            echo "</div>";

            echo "<form method='post' action='" . $urlForm . "' class='mt-3'>";
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            foreach ($_POST as $chave => $valor) {
                if ($chave === '_glpi_csrf_token' || is_array($valor)) {
                    continue;
                }
                echo Html::hidden($chave, ['value' => $valor]);
            }
            echo Html::hidden('_confirmar_sobrescrita', ['value' => 1]);
            echo "<div class='d-flex gap-2'>";
            echo Html::submit(__('Sim, sobrescrever', 'admissaoacessos'), ['name' => isset($_POST['add']) ? 'add' : 'update', 'class' => 'btn btn-danger']);
            echo "<a class='btn btn-outline-secondary' href='" . $urlLista . "'>" . __('Cancelar', 'admissaoacessos') . "</a>";
            echo "</div>";
            echo "</form>";
            echo "</div></div></div>";
            Html::footer();
            exit;
        }
    }

    if ($querCriarAtualizarModelo) {
        $input['tasktemplates_id'] = CatalogoService::criarOuAtualizarModelo(
            $input['tasktemplates_id'],
            $input['name'],
            (bool) $input['uses_project_action'],
            $input['entities_id'],
            (bool) $input['is_recursive']
        );
    }

    if (isset($_POST['add'])) {
        $item->add($input);
    } else {
        $input['id'] = $id;
        $item->update($input);
    }

    Html::redirect($urlLista);
    exit;
}

// Exibição do formulário.
if ($id > 0) {
    $item->getFromDB($id);
}

Html::header(__('Acessos da admissão', 'admissaoacessos'), '', 'tools', 'pluginadmissaoacessos');

echo "<div class='container-fluid' style='margin-top: 1em;'>";
echo "<div style='max-width: 760px;'>";
echo "<div class='card'><div class='card-body'>";

echo "<div class='text-muted small mb-1'>" . __('Acessos da admissão', 'admissaoacessos') . " &rsaquo; <a href='" . $urlLista . "'>" . __('Catálogo', 'admissaoacessos') . "</a></div>";
echo "<h2 class='mb-3'>" . ($id > 0 ? sprintf(__('Editar sistema: %s', 'admissaoacessos'), htmlescape($item->fields['name'])) : __('Novo sistema', 'admissaoacessos')) . "</h2>";

echo "<form method='post' action='" . $urlForm . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo Html::hidden('id', ['value' => $id]);

echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('Nome do sistema', 'admissaoacessos') . "</label>";
echo Html::input('name', ['value' => $item->fields['name'] ?? '', 'required' => 'required']);
echo "<div class='form-text'>" . __('Precisa ser igual ao título do modelo de tarefa.', 'admissaoacessos') . "</div>";
echo "</div>";

echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('Modelo de tarefa vinculado', 'admissaoacessos') . "</label>";
Dropdown::show('TaskTemplate', [
    'name'                => 'tasktemplates_id',
    'value'               => $item->fields['tasktemplates_id'] ?? 0,
    'display_emptychoice' => true,
]);
echo "</div>";

echo "<div class='row mb-3'>";
echo "<div class='col-7'>";
echo "<label class='form-label'>" . __('Tipo de acesso', 'admissaoacessos') . "</label>";
Dropdown::showFromArray('access_type', Sistema::getTiposAcesso(), [
    'value' => $item->fields['access_type'] ?? Sistema::TIPO_LIVRE,
]);
echo "</div>";
echo "<div class='col-5'>";
echo "<label class='form-label'>" . __('Ordem no e-mail', 'admissaoacessos') . "</label>";
echo Html::input('display_order', ['value' => $item->fields['display_order'] ?? 0, 'type' => 'number']);
echo "</div>";
echo "</div>";

echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('Texto no e-mail', 'admissaoacessos') . "</label>";
echo "<textarea name='email_text' class='form-control' rows='3'>" . htmlescape($item->fields['email_text'] ?? '') . "</textarea>";
echo "<div class='form-text'>" . __('Variáveis: {{senha_padrao}}, {{nome}}, {{email}}', 'admissaoacessos') . "</div>";
echo "</div>";

echo "<div class='mb-2 form-check'>";
echo "<input type='checkbox' class='form-check-input' name='uses_project_action' id='uses_project_action'" . (($item->fields['uses_project_action'] ?? 0) ? " checked" : "") . ">";
echo "<label class='form-check-label' for='uses_project_action'>" . __('Pede Projeto e Ação no modelo', 'admissaoacessos') . "</label>";
echo "</div>";

echo "<div class='mb-2 form-check'>";
echo "<input type='checkbox' class='form-check-input' name='is_active' id='is_active'" . (($id === 0 || ($item->fields['is_active'] ?? 0)) ? " checked" : "") . ">";
echo "<label class='form-check-label' for='is_active'>" . __('Sistema ativo', 'admissaoacessos') . "</label>";
echo "</div>";

echo "<div class='mb-3 form-check'>";
echo "<input type='checkbox' class='form-check-input' name='_criar_atualizar_modelo' id='_criar_atualizar_modelo'>";
echo "<label class='form-check-label' for='_criar_atualizar_modelo'>" . __('Atualizar o modelo de tarefa com o texto padrão', 'admissaoacessos') . "</label>";
echo "</div>";

echo "<details class='mb-3'>";
echo "<summary class='text-muted small' style='cursor:pointer;'>" . __('Entidade e recursividade', 'admissaoacessos') . "</summary>";
echo "<div class='row mt-2'>";
echo "<div class='col-6'>";
Dropdown::show('Entity', ['name' => 'entities_id', 'value' => $item->fields['entities_id'] ?? 0]);
echo "</div>";
echo "<div class='col-6 d-flex align-items-center'>";
echo "<div class='form-check'>";
echo "<input type='checkbox' class='form-check-input' name='is_recursive' id='is_recursive'" . (($item->fields['is_recursive'] ?? 0) ? " checked" : "") . ">";
echo "<label class='form-check-label' for='is_recursive'>" . __('Recursivo (sub-entidades)', 'admissaoacessos') . "</label>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</details>";

echo "<hr>";
echo "<div class='d-flex justify-content-between'>";
if ($id > 0 && $item->podeExcluir()) {
    echo "<a class='btn btn-outline-danger' href='?id=" . $id . "&delete=1' onclick=\"return confirm('" . __s('Excluir este sistema?', 'admissaoacessos') . "');\">" . __('Excluir', 'admissaoacessos') . "</a>";
} elseif ($id > 0) {
    echo "<span class='text-muted small align-self-center'>" . __('Já usado em algum envio - só é possível desativar.', 'admissaoacessos') . "</span>";
} else {
    echo "<span></span>";
}
echo "<div class='d-flex gap-2'>";
echo "<a class='btn btn-outline-secondary' href='" . $urlLista . "'>" . __('Cancelar', 'admissaoacessos') . "</a>";
echo Html::submit(__('Salvar', 'admissaoacessos'), ['name' => $id > 0 ? 'update' : 'add', 'class' => 'btn btn-primary']);
echo "</div>";
echo "</div>";

echo "</form>";

echo "</div></div></div>";
echo "</div>";

Html::footer();
