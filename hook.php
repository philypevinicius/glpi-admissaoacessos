<?php

/**
 * Plugin Acessos da admissão - Hook functions.
 */

use GlpiPlugin\Admissaoacessos\CatalogoService;
use GlpiPlugin\Admissaoacessos\Sistema;

/**
 * Seed inicial do catálogo (ver especificação do processo) - 13 sistemas,
 * na ordem de exibição do e-mail. Cada um já ganha seu \TaskTemplate
 * nativo criado com o corpo padrão, pra não exigir nenhum passo manual
 * antes do coordenador conseguir usar o botão "Adicionar tarefas de
 * acesso" no chamado.
 *
 * @return array<int, array{name: string, access_type: string, email_text: string, uses_project_action: bool}>
 */
function plugin_admissaoacessos_catalogo_seed(): array
{
    $senha = '{{senha_padrao}}';

    return [
        ['name' => 'SGTEC',                 'access_type' => Sistema::TIPO_EMAIL_SEPARADO, 'email_text' => __('Dados de acesso enviados em e-mail separado.', 'admissaoacessos'), 'uses_project_action' => false],
        ['name' => 'SGTEC/SGF',             'access_type' => Sistema::TIPO_EMAIL_SEPARADO, 'email_text' => __('Dados de acesso enviados em e-mail separado.', 'admissaoacessos'), 'uses_project_action' => false],
        ['name' => 'SGTEC/SOL',             'access_type' => Sistema::TIPO_EMAIL_SEPARADO, 'email_text' => __('Dados de acesso enviados em e-mail separado.', 'admissaoacessos'), 'uses_project_action' => false],
        ['name' => 'FLUIG',                 'access_type' => Sistema::TIPO_PADRAO,         'email_text' => sprintf(__('Matrícula e senha %s.', 'admissaoacessos'), $senha), 'uses_project_action' => false],
        ['name' => 'MeuRH',                 'access_type' => Sistema::TIPO_PADRAO,         'email_text' => sprintf(__('Matrícula e senha %s.', 'admissaoacessos'), $senha), 'uses_project_action' => false],
        ['name' => 'Espaço do Fornecedor',  'access_type' => Sistema::TIPO_REDE,           'email_text' => __('Use sua senha de rede.', 'admissaoacessos'), 'uses_project_action' => false],
        ['name' => 'Painéis de BI',         'access_type' => Sistema::TIPO_REDE,           'email_text' => __('Use sua senha de rede.', 'admissaoacessos'), 'uses_project_action' => false],
        ['name' => 'Parça',                 'access_type' => Sistema::TIPO_REDE,           'email_text' => __('Use sua senha de rede.', 'admissaoacessos'), 'uses_project_action' => false],
        ['name' => 'Portfólio de Soluções', 'access_type' => Sistema::TIPO_REDE,           'email_text' => __('Use sua senha de rede.', 'admissaoacessos'), 'uses_project_action' => false],
        ['name' => 'GESCON',                'access_type' => Sistema::TIPO_REDE,           'email_text' => __('Use sua senha de rede.', 'admissaoacessos'), 'uses_project_action' => false],
        ['name' => 'SEF',                   'access_type' => Sistema::TIPO_REDE,           'email_text' => __('Use sua senha de rede.', 'admissaoacessos'), 'uses_project_action' => false],
        ['name' => 'RM',                    'access_type' => Sistema::TIPO_LIVRE,          'email_text' => __('Acesso liberado, entre em contato.', 'admissaoacessos'), 'uses_project_action' => false],
        ['name' => 'FOCO',                  'access_type' => Sistema::TIPO_LIVRE,          'email_text' => __('Acesso liberado por link do Nacional.', 'admissaoacessos'), 'uses_project_action' => true],
    ];
}

function plugin_admissaoacessos_install()
{
    global $DB;

    $migration = new Migration(PLUGIN_ADMISSAOACESSOS_VERSION);

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    $systemsTableJaExistia = $DB->tableExists('glpi_plugin_admissaoacessos_systems');

    if (!$systemsTableJaExistia) {
        $query = "CREATE TABLE `glpi_plugin_admissaoacessos_systems` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL DEFAULT '',
            `tasktemplates_id` int {$default_key_sign} NOT NULL DEFAULT 0,
            `access_type` varchar(20) NOT NULL DEFAULT 'livre',
            `email_text` text,
            `display_order` int NOT NULL DEFAULT 0,
            `uses_project_action` tinyint NOT NULL DEFAULT 0,
            `is_active` tinyint NOT NULL DEFAULT 1,
            `entities_id` int {$default_key_sign} NOT NULL DEFAULT 0,
            `is_recursive` tinyint NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `tasktemplates_id` (`tasktemplates_id`),
            KEY `is_active` (`is_active`),
            KEY `entities_id` (`entities_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation};";
        $DB->doQuery($query) or die($DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_admissaoacessos_configs')) {
        $query = "CREATE TABLE `glpi_plugin_admissaoacessos_configs` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `tasktemplates_id_base` int {$default_key_sign} NOT NULL DEFAULT 0,
            `senha_padrao_cripto` text,
            `email_subject` varchar(255) NOT NULL DEFAULT '',
            `email_body` text,
            `email_cc` varchar(255) NOT NULL DEFAULT '',
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation};";
        $DB->doQuery($query) or die($DB->error());

        $DB->insert('glpi_plugin_admissaoacessos_configs', [
            'id'           => 1,
            'email_subject' => __('Bem-vindo(a)! Seus acessos já estão liberados', 'admissaoacessos'),
            'email_body'    => __("Olá {{nome}},\n\nSeus acessos foram liberados:\n\n{{lista_acessos}}\n\n{{aviso_troca_senha}}", 'admissaoacessos'),
        ]);
    }

    if (!$DB->tableExists('glpi_plugin_admissaoacessos_categorias')) {
        $query = "CREATE TABLE `glpi_plugin_admissaoacessos_categorias` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `itilcategories_id` int {$default_key_sign} NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `itilcategories_id` (`itilcategories_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation};";
        $DB->doQuery($query) or die($DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_admissaoacessos_logs')) {
        $query = "CREATE TABLE `glpi_plugin_admissaoacessos_logs` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `tickets_id` int {$default_key_sign} NOT NULL DEFAULT 0,
            `date_send` datetime DEFAULT NULL,
            `destinatario` varchar(255) NOT NULL DEFAULT '',
            `sistemas_enviados` varchar(255) NOT NULL DEFAULT ',,',
            `resultado` varchar(20) NOT NULL DEFAULT '',
            `mensagem_erro` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `tickets_id` (`tickets_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation};";
        $DB->doQuery($query) or die($DB->error());
    }

    $migration->executeMigration();

    // Seed só na criação original da tabela - não re-seeda numa
    // atualização (evitaria duplicar sistemas se o coordenador já tiver
    // mexido no catálogo).
    if (!$systemsTableJaExistia) {
        $ordem = 1;
        foreach (plugin_admissaoacessos_catalogo_seed() as $dados) {
            $tasktemplatesId = CatalogoService::criarOuAtualizarModelo(
                0,
                $dados['name'],
                $dados['uses_project_action'],
                0,
                true
            );

            $DB->insert('glpi_plugin_admissaoacessos_systems', [
                'name'                 => $dados['name'],
                'tasktemplates_id'     => $tasktemplatesId,
                'access_type'          => $dados['access_type'],
                'email_text'           => $dados['email_text'],
                'display_order'        => $ordem,
                'uses_project_action'  => $dados['uses_project_action'] ? 1 : 0,
                'is_active'            => 1,
                'entities_id'          => 0,
                'is_recursive'         => 1,
                'date_creation'        => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            ]);
            $ordem++;
        }
    }

    // Direito guardado por existência prévia - addProfileRights() não é
    // idempotente (mesmo padrão já corrigido no controleinterno/assistente).
    $direitoJaExiste = (bool) $DB->request([
        'FROM'  => 'glpi_profilerights',
        'WHERE' => ['name' => \GlpiPlugin\Admissaoacessos\AccessService::RIGHTNAME],
        'LIMIT' => 1,
    ])->current();
    if (!$direitoJaExiste) {
        ProfileRight::addProfileRights([\GlpiPlugin\Admissaoacessos\AccessService::RIGHTNAME]);
    }

    $idsPorNome = plugin_admissaoacessos_buscar_ids_perfis(['Coordenador', 'Admin', 'Super-Admin']);
    foreach (['Coordenador', 'Admin', 'Super-Admin'] as $nomePerfil) {
        if (isset($idsPorNome[$nomePerfil])) {
            ProfileRight::updateProfileRights($idsPorNome[$nomePerfil], [\GlpiPlugin\Admissaoacessos\AccessService::RIGHTNAME => READ]);
        }
    }

    return true;
}

/**
 * @param array<int, string> $nomes
 * @return array<string, int> nome => id, só os que existirem nesta instalação.
 */
function plugin_admissaoacessos_buscar_ids_perfis(array $nomes): array
{
    global $DB;

    $it = $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM'   => 'glpi_profiles',
        'WHERE'  => ['name' => $nomes],
    ]);

    $resultado = [];
    foreach ($it as $linha) {
        $resultado[$linha['name']] = (int) $linha['id'];
    }
    return $resultado;
}

function plugin_admissaoacessos_uninstall()
{
    global $DB;

    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_admissaoacessos_systems`") or die($DB->error());
    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_admissaoacessos_configs`") or die($DB->error());
    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_admissaoacessos_categorias`") or die($DB->error());
    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_admissaoacessos_logs`") or die($DB->error());

    ProfileRight::deleteProfileRights([\GlpiPlugin\Admissaoacessos\AccessService::RIGHTNAME]);

    return true;
}

/**
 * Item de menu "Acessos da admissão" - só pra quem tem o direito (igual
 * padrão do assistente/controleinterno).
 */
function plugin_admissaoacessos_redefine_menus($menus)
{
    global $CFG_GLPI;

    if (!\GlpiPlugin\Admissaoacessos\AccessService::canAccess()) {
        return $menus;
    }

    $menus['admissaoacessos'] = [
        'title'   => __('Acessos da admissão', 'admissaoacessos'),
        'icon'    => 'ti ti-user-plus',
        'default' => $CFG_GLPI['root_doc'] . '/plugins/admissaoacessos/front/sistema.php',
        'content' => [
            'sistema' => [
                'title' => __('Catálogo de sistemas', 'admissaoacessos'),
                'page'  => $CFG_GLPI['root_doc'] . '/plugins/admissaoacessos/front/sistema.php',
                'icon'  => 'ti ti-list',
            ],
            'config' => [
                'title' => __('Configuração', 'admissaoacessos'),
                'page'  => $CFG_GLPI['root_doc'] . '/plugins/admissaoacessos/front/config.php',
                'icon'  => 'ti ti-settings',
            ],
        ],
    ];

    return $menus;
}

/**
 * Botão "Adicionar tarefas de acesso" na timeline do chamado - só
 * aparece em chamados de uma categoria configurada E pra quem tem
 * direito nativo de criar tarefa nesse chamado (nada de reinventar
 * permissão: usa Ticket::canAddItem(), igual o resto do GLPI).
 * O botão só abre o modal (JS) - quem realmente cria as tarefas é
 * ajax/criar_tarefas.php, que confere tudo de novo no servidor.
 */
function plugin_admissaoacessos_timeline_actions(array $params): void
{
    $item = $params['item'] ?? null;
    if (!($item instanceof \Ticket) || !$item->getID()) {
        return;
    }

    $config = \GlpiPlugin\Admissaoacessos\Config::getConfig();
    $categoriasAdmissao = $config->categoriasIds();
    if (empty($categoriasAdmissao) || !in_array((int) $item->fields['itilcategories_id'], $categoriasAdmissao, true)) {
        return;
    }

    if (!$item->canAddItem('TicketTask')) {
        return;
    }

    $rand = $params['rand'] ?? mt_rand();

    echo "<li>";
    echo "<button type='button' class='btn btn-outline-secondary admissaoacessos-abrir-modal' "
        . "data-tickets-id='" . (int) $item->getID() . "' data-rand='" . (int) $rand . "'>";
    echo "<i class='ti ti-user-plus' aria-hidden='true'></i> " . __('Adicionar tarefas de acesso', 'admissaoacessos');
    echo "</button>";
    echo "</li>";
}

/**
 * Fecha o chamado -> dispara o e-mail de boas-vindas (Fase 5, ainda não
 * implementada). Por enquanto só um stub seguro, pra não quebrar a
 * ativação do plugin.
 */
function plugin_admissaoacessos_item_update_ticket(\Ticket $item): void
{
    // TODO Fase 5
}
