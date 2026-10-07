<?php

namespace GlpiPlugin\Admissaoacessos;

/**
 * Enfileira o e-mail de boas-vindas pela fila NATIVA do GLPI
 * (`glpi_queuednotifications`), herdando SMTP/reenvio/histórico - sem
 * precisar de um \NotificationTemplate cadastrado no banco, já que o
 * assunto/corpo chegam aqui PRONTOS (as variáveis já foram resolvidas
 * em EmailBoasVindasService).
 *
 * Reaproveita \NotificationMailing::sendNotification() - o mesmo método
 * que o core usa internamente depois de resolver um template - em vez
 * de inserir direto em glpi_queuednotifications, pra herdar de graça os
 * headers padrão (Auto-Submitted, etc) e o log em mail.log.
 */
class NotificacaoService
{
    private const EVENTO = 'plugin_admissaoacessos_boasvindas';

    public static function enfileirar(\Ticket $ticket, string $destinatarioEmail, string $destinatarioNome, string $assunto, string $corpo): bool
    {
        global $CFG_GLPI;

        // sendNotification() não confere essas duas chaves (só quem
        // resolve o fluxo completo via raiseEvent() confere) - como
        // pulamos esse fluxo de propósito, confere aqui na mão, pra
        // respeitar o interruptor geral de notificações do GLPI.
        if (empty($CFG_GLPI['use_notifications']) || empty($CFG_GLPI['notifications_mailing'])) {
            return false;
        }

        $entitiesId = (int) $ticket->fields['entities_id'];
        $remetente = \Config::getAdminEmailSender($entitiesId);

        $options = [
            '_itemtype'                 => 'Ticket',
            '_items_id'                 => $ticket->getID(),
            '_notificationtemplates_id' => 0,
            '_entities_id'              => $entitiesId,
            'from'                      => $remetente['email'],
            'fromname'                  => $remetente['name'],
            'to'                        => $destinatarioEmail,
            'toname'                    => $destinatarioNome !== '' ? $destinatarioNome : $destinatarioEmail,
            'subject'                   => $assunto,
            'content_text'              => $corpo,
            'event'                     => self::EVENTO,
            'itemtype_trigger'          => 'Ticket',
            'items_id_trigger'          => $ticket->getID(),
        ];

        return (bool) (new \NotificationMailing())->sendNotification($options);
    }
}
