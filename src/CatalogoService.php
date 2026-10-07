<?php

namespace GlpiPlugin\Admissaoacessos;

/**
 * Gera/atualiza o \TaskTemplate nativo a partir do texto padrão do
 * processo de admissão - usado tanto no seed da instalação quanto no
 * botão "Criar/atualizar o modelo de tarefa nativo" da tela de cadastro
 * de sistema.
 */
class CatalogoService
{
    /**
     * Corpo padrão da tarefa (ver especificação do processo) - texto puro
     * com quebras de linha reais; convertido pra HTML só na hora de
     * gravar no TaskTemplate (TinyMCE espera HTML).
     */
    public static function montarCorpoPadrao(string $nomeSistema, bool $usaProjetoAcao): string
    {
        $linhas = [
            sprintf(__('Favor liberar acesso no %s para o(a) usuário(a) abaixo:', 'admissaoacessos'), $nomeSistema),
            '',
            __('Nome:', 'admissaoacessos'),
            __('CPF:', 'admissaoacessos'),
            __('Matrícula:', 'admissaoacessos'),
            __('Cargo:', 'admissaoacessos'),
            __('Telefone:', 'admissaoacessos'),
            __('E-mail:', 'admissaoacessos'),
            __('Início da contratação:', 'admissaoacessos'),
            __('Término da contratação:', 'admissaoacessos'),
            __('Unidade:', 'admissaoacessos'),
            __('Empresa:', 'admissaoacessos'),
            __('Perfil a ser clonado:', 'admissaoacessos'),
        ];

        if ($usaProjetoAcao) {
            $linhas[] = __('Projeto:', 'admissaoacessos');
            $linhas[] = __('Ação:', 'admissaoacessos');
        }

        return implode("\n", $linhas);
    }

    public static function corpoParaHtml(string $corpoTextoPuro): string
    {
        return nl2br(\Html::entities_deep($corpoTextoPuro));
    }

    /**
     * Cria o TaskTemplate se `tasktemplatesId` for 0, ou sobrescreve o
     * conteúdo de um já existente - devolve o ID (novo ou o mesmo).
     * Quem decide SE deve sobrescrever (mostrando a diferença antes) é a
     * tela de formulário; aqui é só a gravação em si.
     */
    public static function criarOuAtualizarModelo(
        int $tasktemplatesId,
        string $nomeSistema,
        bool $usaProjetoAcao,
        int $entitiesId,
        bool $isRecursive
    ): int {
        $corpoHtml = self::corpoParaHtml(self::montarCorpoPadrao($nomeSistema, $usaProjetoAcao));

        $template = new \TaskTemplate();
        $input = [
            'name'        => $nomeSistema,
            'content'     => $corpoHtml,
            'entities_id' => $entitiesId,
            'is_recursive' => $isRecursive ? 1 : 0,
        ];

        if ($tasktemplatesId > 0 && $template->getFromDB($tasktemplatesId)) {
            $input['id'] = $tasktemplatesId;
            $template->update($input);
            return $tasktemplatesId;
        }

        $novoId = $template->add($input);
        return $novoId ? (int) $novoId : 0;
    }

    /**
     * Pré-preenche `Nome:`/`E-mail:` no conteúdo de uma tarefa recém-criada
     * com o que o GLPI "já sabe" (geralmente copiado de outra tarefa do
     * mesmo chamado já preenchida, ex.: a do AD) - só entra se a linha
     * ainda estiver vazia, NUNCA sobrescreve texto que o coordenador já
     * escreveu (regra explícita do processo). Nunca mexe em CPF nem
     * qualquer outro campo.
     */
    public static function preencherCamposConhecidos(string $conteudoHtml, string $nome, string $email): string
    {
        if ($nome !== '' && ExtracaoCampoService::nome($conteudoHtml) === '') {
            $conteudoHtml = str_ireplace(
                __('Nome:', 'admissaoacessos') . '<br',
                __('Nome:', 'admissaoacessos') . ' ' . \Html::entities_deep($nome) . '<br',
                $conteudoHtml
            );
        }

        if ($email !== '' && ExtracaoCampoService::email($conteudoHtml) === '') {
            $conteudoHtml = str_ireplace(
                __('E-mail:', 'admissaoacessos') . '<br',
                __('E-mail:', 'admissaoacessos') . ' ' . \Html::entities_deep($email) . '<br',
                $conteudoHtml
            );
        }

        return $conteudoHtml;
    }
}
