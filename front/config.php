<?php

/**
 * Plugin Acessos da admissão - Configuração geral.
 */
if (!class_exists(Session::class)) {
    require_once __DIR__ . '/../../../inc/includes.php';
}

use GlpiPlugin\Admissaoacessos\AccessService;
use GlpiPlugin\Admissaoacessos\Config;

global $CFG_GLPI;

Session::checkLoginUser();
AccessService::exigirAcesso();

$urlConfig = $CFG_GLPI['root_doc'] . '/plugins/admissaoacessos/front/config.php';

if (isset($_POST['update'])) {
    $config = Config::getConfig();

    $input = [
        'id'                    => 1,
        'tasktemplates_id_base' => (int) ($_POST['tasktemplates_id_base'] ?? 0),
        'email_subject'         => trim($_POST['email_subject'] ?? ''),
        'email_body'            => trim($_POST['email_body'] ?? ''),
        'email_cc'              => trim($_POST['email_cc'] ?? ''),
    ];
    $config->update($input);

    // Senha em branco = mantém a atual (nunca reexibida em texto aberto
    // no formulário, então "não mexer" tem que significar "não mudou").
    $senhaDigitada = (string) ($_POST['senha_padrao'] ?? '');
    if ($senhaDigitada !== '') {
        $config->salvarSenhaPadrao($senhaDigitada);
    }

    $config->salvarCategorias(array_map('intval', $_POST['itilcategories_id'] ?? []));

    Session::addMessageAfterRedirect(__('Configuração salva.', 'admissaoacessos'), false, INFO);
    Html::redirect($urlConfig);
    exit;
}

$config = Config::getConfig();
$categoriasAtuais = $config->categoriasIds();
$temSenhaConfigurada = $config->senhaPadraoDecriptada() !== '';

Html::header(__('Acessos da admissão', 'admissaoacessos'), '', 'tools', 'pluginadmissaoacessos');

echo "<div class='container-fluid' style='margin-top: 1em;'>";
echo "<div style='max-width: 760px;'>";
echo "<div class='card'><div class='card-body'>";

echo "<div class='d-flex align-items-center gap-2 mb-3'>";
echo "<a href='" . $CFG_GLPI['root_doc'] . "/plugins/admissaoacessos/front/sistema.php' class='btn btn-outline-secondary btn-sm rounded-pill d-inline-flex align-items-center gap-1'><i class='ti ti-arrow-left'></i>" . __('Voltar', 'admissaoacessos') . "</a>";
echo "<h2 class='mb-0'>" . __('Configuração', 'admissaoacessos') . "</h2>";
echo "</div>";

echo "<form method='post' action='" . $urlConfig . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('Categoria(s) ITIL do chamado de admissão', 'admissaoacessos') . "</label>";
Dropdown::show('ITILCategory', [
    'name'      => 'itilcategories_id',
    'values'    => $categoriasAtuais,
    'multiple'  => true,
    'comments'  => false,
]);
echo "</div>";

echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('Modelo de tarefa base (AD) - precisa estar concluído antes do envio', 'admissaoacessos') . "</label>";
Dropdown::show('TaskTemplate', [
    'name'                => 'tasktemplates_id_base',
    'value'               => $config->fields['tasktemplates_id_base'] ?? 0,
    'display_emptychoice' => true,
]);
echo "</div>";

echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('Senha padrão', 'admissaoacessos') . "</label>";
echo Html::input('senha_padrao', ['type' => 'password', 'value' => '', 'autocomplete' => 'new-password']);
echo "<div class='form-text'>" . ($temSenhaConfigurada
    ? __('Já há uma senha configurada. Deixe em branco pra manter a atual.', 'admissaoacessos')
    : __('Nenhuma senha configurada ainda.', 'admissaoacessos')) . "</div>";
echo "</div>";

echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('Assunto do e-mail', 'admissaoacessos') . "</label>";
echo Html::input('email_subject', ['value' => $config->fields['email_subject'] ?? '']);
echo "</div>";

echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('Texto do e-mail', 'admissaoacessos') . "</label>";
echo "<textarea name='email_body' class='form-control' rows='8'>" . htmlescape($config->fields['email_body'] ?? '') . "</textarea>";
echo "<div class='form-text'>" . __('Variáveis disponíveis: {{nome}}, {{lista_acessos}}, {{aviso_troca_senha}}', 'admissaoacessos') . "</div>";
echo "</div>";

echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('E-mail em cópia (opcional)', 'admissaoacessos') . "</label>";
echo Html::input('email_cc', ['value' => $config->fields['email_cc'] ?? '']);
echo "</div>";

echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);

echo "</form>";

echo "</div></div></div>";
echo "</div>";

Html::footer();
