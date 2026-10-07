<?php

namespace GlpiPlugin\Admissaoacessos;

/**
 * Acesso ao catálogo/configuração: só Coordenador e perfis acima (Admin,
 * Super-Admin). Técnicos e observadores não veem nada disso - eles só
 * interagem com o botão "Adicionar tarefas de acesso" dentro do chamado,
 * que não depende deste direito (usa o direito nativo de UPDATE no
 * próprio Ticket).
 */
class AccessService
{
    public const RIGHTNAME = 'plugin_admissaoacessos_config';

    public static function canAccess(): bool
    {
        if (!\Session::getLoginUserID()) {
            return false;
        }
        return \Session::haveRight(self::RIGHTNAME, READ);
    }

    public static function exigirAcesso(): void
    {
        if (!self::canAccess()) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }
    }
}
