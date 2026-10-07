<?php

define('PLUGIN_ADMISSAOACESSOS_VERSION', '0.1.0');
define('PLUGIN_ADMISSAOACESSOS_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_ADMISSAOACESSOS_MAX_GLPI_VERSION', '12.0.99');

function plugin_version_admissaoacessos()
{
    return [
        'name'         => 'Acessos da admissão',
        'version'      => PLUGIN_ADMISSAOACESSOS_VERSION,
        'author'       => 'SEBRAE',
        'license'      => 'GPLv3+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_ADMISSAOACESSOS_MIN_GLPI_VERSION,
                'max' => PLUGIN_ADMISSAOACESSOS_MAX_GLPI_VERSION,
            ],
            'php' => [
                'min' => '8.2.0',
            ],
        ],
    ];
}

function plugin_init_admissaoacessos()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['admissaoacessos'] = true;

    $PLUGIN_HOOKS['redefine_menus']['admissaoacessos'] = 'plugin_admissaoacessos_redefine_menus';

    // Botão "Adicionar tarefas de acesso" no chamado: widget JS carregado em
    // toda página autenticada, igual o padrão já usado no assistente/
    // movimentacoes - ele mesmo decide (via ajax) se está numa tela de
    // chamado de admissão antes de se desenhar.
    $PLUGIN_HOOKS['add_javascript']['admissaoacessos'] = 'public/js/admissaoacessos.js';
    $PLUGIN_HOOKS['add_css']['admissaoacessos'] = 'public/css/admissaoacessos.css';

    // Fecha o chamado -> dispara o e-mail de boas-vindas (Fase 5).
    $PLUGIN_HOOKS['item_update']['admissaoacessos'] = [
        'Ticket' => 'plugin_admissaoacessos_item_update_ticket',
    ];

    // Aba "Acessos da admissão" em Configurar > Perfis > [perfil].
    Plugin::registerClass(\GlpiPlugin\Admissaoacessos\ProfileTab::class, ['addtabon' => ['Profile']]);
}

function plugin_admissaoacessos_check_prerequisites()
{
    return true;
}

function plugin_admissaoacessos_check_config()
{
    return true;
}
