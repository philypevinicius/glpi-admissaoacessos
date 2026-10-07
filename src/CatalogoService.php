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
     * Rótulos padrão de toda tarefa (sem os dois condicionais de
     * Projeto:/Ação:, só no FOCO) - lista única reusada tanto pra montar
     * o corpo quanto pra saber quais campos tentar copiar entre tarefas
     * (ver preencherCamposConhecidos()).
     *
     * @return string[]
     */
    public static function rotulosPadrao(): array
    {
        return [
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
    }

    /**
     * Corpo padrão da tarefa (ver especificação do processo) - texto puro
     * com quebras de linha reais; convertido pra HTML só na hora de
     * gravar no TaskTemplate (TinyMCE espera HTML).
     */
    public static function montarCorpoPadrao(string $nomeSistema, bool $usaProjetoAcao): string
    {
        $linhas = array_merge(
            [sprintf(__('Favor liberar acesso no %s para o(a) usuário(a) abaixo:', 'admissaoacessos'), $nomeSistema), ''],
            self::rotulosPadrao()
        );

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
     * Pré-preenche no conteúdo de uma tarefa recém-criada todos os campos
     * que o GLPI "já sabe" (copiados de outra tarefa do mesmo chamado já
     * preenchida, normalmente a do AD) - inclui CPF/Matrícula/etc, a
     * pedido explícito do usuário (diferente do e-mail de boas-vindas,
     * que continua restrito a Nome/E-mail - ver ExtracaoCampoService).
     * Só entra campo por campo se a linha ainda estiver vazia, NUNCA
     * sobrescreve texto que o coordenador já escreveu.
     *
     * @param array<string, string> $camposConhecidos rótulo (ex.: "Nome:") => valor
     */
    public static function preencherCamposConhecidos(string $conteudoHtml, array $camposConhecidos): string
    {
        foreach ($camposConhecidos as $rotulo => $valor) {
            if ($valor === '' || ExtracaoCampoService::valor($conteudoHtml, rtrim($rotulo, ':')) !== '') {
                continue;
            }
            $conteudoHtml = str_ireplace(
                $rotulo . '<br',
                $rotulo . ' ' . \Html::entities_deep($valor) . '<br',
                $conteudoHtml
            );
        }

        return $conteudoHtml;
    }
}
