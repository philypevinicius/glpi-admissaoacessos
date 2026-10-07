<?php

namespace GlpiPlugin\Admissaoacessos;

/**
 * Um envio (ou tentativa) do e-mail de boas-vindas - serve de trava
 * anti-duplicidade (reabrir/fechar o chamado de novo não reenvia) e de
 * histórico auditável.
 *
 * `sistemas_enviados` guarda os IDs dos \Sistema incluídos no e-mail no
 * formato ",1,2,3," (vírgula nas pontas) de propósito - permite achar
 * "esse sistema já foi citado em algum envio" com um LIKE simples
 * (`%,1,%`) sem precisar de uma tabela de junção só pra isso.
 */
class RegistroEnvio extends \CommonDBTM
{
    public const SUCESSO = 'sucesso';
    public const FALHA   = 'falha';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_admissaoacessos_logs';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Envio de acessos', 'Envios de acessos', $nb, 'admissaoacessos');
    }

    public static function jaEnviadoParaChamado(int $ticketsId): bool
    {
        global $DB;

        return (bool) $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['tickets_id' => $ticketsId, 'resultado' => self::SUCESSO],
            'LIMIT' => 1,
        ])->current();
    }

    public static function formatarListaSistemas(array $idsSistemas): string
    {
        if (empty($idsSistemas)) {
            return ',,';
        }
        return ',' . implode(',', array_map('intval', $idsSistemas)) . ',';
    }
}
