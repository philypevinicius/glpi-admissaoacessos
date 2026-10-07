<?php

namespace GlpiPlugin\Admissaoacessos;

/**
 * Orquestra o envio do e-mail de boas-vindas quando o chamado de
 * admissão é fechado: valida que está tudo pronto, monta o texto por
 * sistema (um por tarefa concluída que bate com o catálogo, na ordem do
 * catálogo) e enfileira pela fila nativa de notificação.
 *
 * Nunca lê/loga CPF nem qualquer dado pessoal além de Nome e E-mail
 * (ver ExtracaoCampoService) - é só o que o processo autoriza o plugin a
 * tocar.
 */
class EmailBoasVindasService
{
    private const AVISO_TROCA_SENHA_PADRAO = 'Por segurança, troque sua senha provisória no primeiro acesso.';

    /**
     * Ponto de entrada chamado pelo hook item_update do Ticket, quando o
     * status muda pra Fechado. Nunca lança exceção pra fora - qualquer
     * problema vira acompanhamento privado no chamado, pra não travar o
     * fechamento em si por causa do e-mail.
     */
    public static function processarFechamento(\Ticket $ticket): void
    {
        $categoriasAdmissao = Config::getConfig()->categoriasIds();
        if (empty($categoriasAdmissao) || !in_array((int) $ticket->fields['itilcategories_id'], $categoriasAdmissao, true)) {
            return; // Não é chamado de admissão - nada a fazer.
        }

        if (RegistroEnvio::jaEnviadoParaChamado((int) $ticket->getID())) {
            return; // Trava anti-duplicidade - reabrir/fechar de novo não reenvia sozinho.
        }

        self::tentarEnviar($ticket);
    }

    /**
     * Reenvio manual (botão "Reenviar e-mail de acessos") - mesma
     * validação; ignora a trava de "já enviado" de propósito (só
     * `processarFechamento()` checa isso, pra não barrar um reenvio
     * pedido explicitamente).
     */
    public static function reenviarManualmente(\Ticket $ticket): array
    {
        return self::tentarEnviar($ticket);
    }

    /**
     * Pré-visualização (botão "Ver e-mail") - roda a MESMA validação e
     * montagem do envio de verdade, mas não enfileira nem grava log.
     *
     * @return array{ok: bool, motivo: string, assunto?: string, corpo?: string, destinatario?: string}
     */
    public static function prever(\Ticket $ticket): array
    {
        $validacao = self::validar($ticket);
        if ($validacao !== null) {
            return ['ok' => false, 'motivo' => $validacao];
        }

        $montagem = self::montarEmail($ticket);
        if ($montagem === null) {
            return ['ok' => false, 'motivo' => __('Nenhuma tarefa concluída bate com um sistema do catálogo.', 'admissaoacessos')];
        }

        return [
            'ok'           => true,
            'motivo'       => '',
            'assunto'      => $montagem['assunto'],
            'corpo'        => $montagem['corpo'],
            'destinatario' => $montagem['destinatario'],
        ];
    }

    /**
     * @return array{enviado: bool, motivo: string, assunto?: string, corpo?: string}
     */
    private static function tentarEnviar(\Ticket $ticket): array
    {
        $validacao = self::validar($ticket);
        if ($validacao !== null) {
            self::registrarFalha($ticket, $validacao);
            self::avisarNoChamado($ticket, $validacao);
            return ['enviado' => false, 'motivo' => $validacao];
        }

        $montagem = self::montarEmail($ticket);
        if ($montagem === null) {
            $motivo = __('Nenhuma tarefa concluída bate com um sistema do catálogo.', 'admissaoacessos');
            self::registrarFalha($ticket, $motivo);
            self::avisarNoChamado($ticket, $motivo);
            return ['enviado' => false, 'motivo' => $motivo];
        }

        ['destinatario' => $destinatario, 'nome' => $nome, 'assunto' => $assunto, 'corpo' => $corpo, 'sistemas_ids' => $sistemasIds] = $montagem;

        $enfileirou = NotificacaoService::enfileirar($ticket, $destinatario, $nome, $assunto, $corpo);

        $log = new RegistroEnvio();
        $log->add([
            'tickets_id'        => $ticket->getID(),
            'date_send'         => date('Y-m-d H:i:s'),
            'destinatario'      => $destinatario,
            'sistemas_enviados' => RegistroEnvio::formatarListaSistemas($sistemasIds),
            'resultado'         => $enfileirou ? RegistroEnvio::SUCESSO : RegistroEnvio::FALHA,
            'mensagem_erro'     => $enfileirou ? '' : __('Falha ao enfileirar a notificação.', 'admissaoacessos'),
        ]);

        return ['enviado' => $enfileirou, 'motivo' => '', 'assunto' => $assunto, 'corpo' => $corpo];
    }

    /** @return string|null motivo do bloqueio, ou null se pode seguir */
    private static function validar(\Ticket $ticket): ?string
    {
        $config = Config::getConfig();
        $tarefas = self::tarefasDoChamado((int) $ticket->getID());

        if (empty($tarefas)) {
            return __('O chamado não tem nenhuma tarefa.', 'admissaoacessos');
        }

        foreach ($tarefas as $tarefa) {
            if ((int) $tarefa['state'] !== \Planning::DONE) {
                return __('Ainda há tarefas não concluídas.', 'admissaoacessos');
            }
        }

        $tasktemplateBase = (int) $config->fields['tasktemplates_id_base'];
        if ($tasktemplateBase > 0) {
            $baseConcluida = false;
            foreach ($tarefas as $tarefa) {
                if ((int) $tarefa['tasktemplates_id'] === $tasktemplateBase) {
                    $baseConcluida = true;
                    break;
                }
            }
            if (!$baseConcluida) {
                return __('A tarefa base (AD) ainda não foi criada/concluída.', 'admissaoacessos');
            }
        }

        $email = self::buscarEmail($tarefas, $tasktemplateBase);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return __('Nenhuma tarefa concluída tem um E-mail: válido preenchido.', 'admissaoacessos');
        }

        $sistemasPorTemplate = self::sistemasAtivosPorTemplate();
        $usaSenhaPadrao = false;
        foreach ($tarefas as $tarefa) {
            $sistema = $sistemasPorTemplate[(int) $tarefa['tasktemplates_id']] ?? null;
            if ($sistema && str_contains((string) $sistema['email_text'], '{{senha_padrao}}')) {
                $usaSenhaPadrao = true;
                break;
            }
        }
        if ($usaSenhaPadrao && $config->senhaPadraoDecriptada() === '') {
            return __('Algum sistema concluído usa a senha padrão, mas ela não está configurada.', 'admissaoacessos');
        }

        return null;
    }

    /**
     * @return array{destinatario: string, assunto: string, corpo: string, sistemas_ids: int[]}|null
     */
    private static function montarEmail(\Ticket $ticket): ?array
    {
        $config = Config::getConfig();
        $tarefas = self::tarefasDoChamado((int) $ticket->getID());
        $sistemasPorTemplate = self::sistemasAtivosPorTemplate();

        $nome = '';
        $email = self::buscarEmail($tarefas, (int) $config->fields['tasktemplates_id_base']);
        $linhasPorSistema = []; // ordem do catálogo (display_order)
        $sistemasIds = [];

        foreach ($tarefas as $tarefa) {
            if ($nome === '') {
                $candidatoNome = ExtracaoCampoService::nome((string) $tarefa['content']);
                if ($candidatoNome !== '') {
                    $nome = $candidatoNome;
                }
            }

            $sistema = $sistemasPorTemplate[(int) $tarefa['tasktemplates_id']] ?? null;
            if (!$sistema) {
                continue; // Tarefa concluída sem sistema correspondente no catálogo - ignorada aqui, citada no acompanhamento se o envio falhar antes.
            }

            $textoSistema = str_replace(
                ['{{senha_padrao}}', '{{nome}}', '{{email}}'],
                [$config->senhaPadraoDecriptada(), $nome, $email],
                (string) $sistema['email_text']
            );

            $linhasPorSistema[(int) $sistema['display_order'] . '_' . $sistema['id']] = $sistema['name'] . ': ' . $textoSistema;
            $sistemasIds[] = (int) $sistema['id'];
        }

        if (empty($linhasPorSistema)) {
            return null;
        }

        ksort($linhasPorSistema, SORT_NATURAL);
        $listaAcessos = implode("\n", $linhasPorSistema);

        $corpo = str_replace(
            ['{{nome}}', '{{lista_acessos}}', '{{aviso_troca_senha}}'],
            [$nome, $listaAcessos, self::AVISO_TROCA_SENHA_PADRAO],
            (string) $config->fields['email_body']
        );
        $assunto = str_replace('{{nome}}', $nome, (string) $config->fields['email_subject']);

        return [
            'destinatario' => $email,
            'nome'         => $nome,
            'assunto'      => $assunto,
            'corpo'        => $corpo,
            'sistemas_ids' => $sistemasIds,
        ];
    }

    private static function buscarEmail(array $tarefas, int $tasktemplateBase): string
    {
        // Prioriza o E-mail: da tarefa BASE (AD) - é ela que efetivamente
        // criou o e-mail corporativo, as demais só reaproveitam o valor
        // que o coordenador copiou pra lá.
        if ($tasktemplateBase > 0) {
            foreach ($tarefas as $tarefa) {
                if ((int) $tarefa['tasktemplates_id'] === $tasktemplateBase) {
                    $email = ExtracaoCampoService::email((string) $tarefa['content']);
                    if ($email !== '') {
                        return $email;
                    }
                }
            }
        }

        foreach ($tarefas as $tarefa) {
            $email = ExtracaoCampoService::email((string) $tarefa['content']);
            if ($email !== '') {
                return $email;
            }
        }

        return '';
    }

    /** @return array<int, array{state:int, tasktemplates_id:int, content:string}> */
    private static function tarefasDoChamado(int $ticketsId): array
    {
        global $DB;

        $tarefas = [];
        foreach ($DB->request([
            'SELECT' => ['state', 'tasktemplates_id', 'content'],
            'FROM'   => \TicketTask::getTable(),
            // glpi_tickettasks não tem `is_deleted` (diferente de glpi_tickets) -
            // confirmado via DESCRIBE real na tabela.
            'WHERE'  => ['tickets_id' => $ticketsId],
        ]) as $linha) {
            $tarefas[] = $linha;
        }
        return $tarefas;
    }

    /** @return array<int, array{id:int, name:string, email_text:string, display_order:int}> indexado por tasktemplates_id */
    private static function sistemasAtivosPorTemplate(): array
    {
        global $DB;

        $sistemas = [];
        foreach ($DB->request([
            'FROM'  => Sistema::getTable(),
            'WHERE' => ['is_active' => 1],
        ]) as $linha) {
            if ((int) $linha['tasktemplates_id'] > 0) {
                $sistemas[(int) $linha['tasktemplates_id']] = $linha;
            }
        }
        return $sistemas;
    }

    private static function registrarFalha(\Ticket $ticket, string $motivo): void
    {
        $log = new RegistroEnvio();
        $log->add([
            'tickets_id'        => $ticket->getID(),
            'date_send'         => date('Y-m-d H:i:s'),
            'destinatario'      => '',
            'sistemas_enviados' => RegistroEnvio::formatarListaSistemas([]),
            'resultado'         => RegistroEnvio::FALHA,
            'mensagem_erro'     => $motivo,
        ]);
    }

    private static function avisarNoChamado(\Ticket $ticket, string $motivo): void
    {
        $followup = new \ITILFollowup();
        $followup->add([
            'itemtype'   => 'Ticket',
            'items_id'   => $ticket->getID(),
            'content'    => sprintf(__('Acessos da admissão: e-mail de boas-vindas NÃO enviado. Motivo: %s', 'admissaoacessos'), $motivo),
            'is_private' => 1,
        ]);
    }
}
