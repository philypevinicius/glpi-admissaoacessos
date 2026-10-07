<?php

namespace GlpiPlugin\Admissaoacessos;

/**
 * Um sistema do catálogo (ex.: FLUIG, FOCO, SGTEC) - liga um
 * \TaskTemplate nativo ao texto que vai pro e-mail de boas-vindas quando
 * a tarefa correspondente for concluída.
 */
class Sistema extends \CommonDBTM
{
    public const TIPO_REDE           = 'rede';
    public const TIPO_PADRAO         = 'padrao';
    public const TIPO_EMAIL_SEPARADO = 'email_separado';
    public const TIPO_LIVRE          = 'livre';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_admissaoacessos_systems';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Sistema', 'Sistemas', $nb, 'admissaoacessos');
    }

    public static function getTiposAcesso(): array
    {
        return [
            self::TIPO_REDE           => __('Usa a senha de rede', 'admissaoacessos'),
            self::TIPO_PADRAO         => __('Matrícula e senha padrão', 'admissaoacessos'),
            self::TIPO_EMAIL_SEPARADO => __('Dados enviados em e-mail separado', 'admissaoacessos'),
            self::TIPO_LIVRE          => __('Texto livre', 'admissaoacessos'),
        ];
    }

    /**
     * Se já foi usado em algum envio (log), só deixa desativar - não
     * apagar - pra não perder o histórico de "o que foi mandado em cada
     * admissão".
     */
    public function podeExcluir(): bool
    {
        global $DB;

        $usado = (bool) $DB->request([
            'FROM'  => RegistroEnvio::getTable(),
            'WHERE' => ['sistemas_enviados' => ['LIKE', '%,' . (int) $this->getID() . ',%']],
            'LIMIT' => 1,
        ])->current();

        return !$usado;
    }
}
