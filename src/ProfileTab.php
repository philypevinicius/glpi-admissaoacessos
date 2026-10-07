<?php

namespace GlpiPlugin\Admissaoacessos;

/**
 * Aba "Acessos da admissão" em Configurar > Perfis > [perfil] - mesmo
 * padrão já usado no plugin assistente. Direito único (READ = tem acesso
 * ao catálogo/configuração, sem diferenciar leitura de escrita - é tudo
 * ou nada pra quem é Coordenador+).
 */
class ProfileTab extends \CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Acessos da admissão', 'admissaoacessos');
    }

    public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof \Profile) || !$item->fields['id']) {
            return '';
        }

        return self::createTabEntry(self::getTypeName());
    }

    public static function displayTabContentForItem(\CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!($item instanceof \Profile)) {
            return false;
        }

        self::showForProfile((int) $item->getID());

        return true;
    }

    public static function showForProfile(int $profilesId): void
    {
        global $CFG_GLPI;

        $podeEditar = \Session::haveRight(\Profile::$rightname, UPDATE);
        $direitos   = \ProfileRight::getProfileRights($profilesId, [AccessService::RIGHTNAME]);
        $valorAtual = (int) ($direitos[AccessService::RIGHTNAME] ?? 0);

        echo "<form method='post' action='" . $CFG_GLPI['root_doc'] . "/plugins/admissaoacessos/front/perfil.form.php'>";
        echo \Html::hidden('profiles_id', ['value' => $profilesId]);
        echo \Html::hidden('_glpi_csrf_token', ['value' => \Session::getNewCSRFToken()]);

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>" . __('Acessos da admissão', 'admissaoacessos') . "</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Gerenciar catálogo de sistemas e configuração', 'admissaoacessos') . "</td>";
        echo "<td>";
        \Html::showCheckbox([
            'name'     => 'gerenciar',
            'checked'  => (bool) ($valorAtual & READ),
            'readonly' => !$podeEditar,
        ]);
        echo "</td>";
        echo "</tr>";

        echo "</table>";

        if ($podeEditar) {
            echo "<div class='center mt-2'>";
            echo \Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>";
        }

        \Html::closeForm();
    }
}
