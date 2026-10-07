<?php

namespace GlpiPlugin\Admissaoacessos;

/**
 * Lê rótulo(s) do CONTEÚDO de uma tarefa (texto que o coordenador digitou
 * em cima do modelo padrão).
 *
 * Dois usos bem separados, cada um com sua própria regra:
 *  - `nome()`/`email()`: usados por EmailBoasVindasService pra montar o
 *    e-mail de boas-vindas - NUNCA leem CPF nem qualquer outro dado
 *    pessoal (regra original do processo: nada de dado pessoal no e-mail
 *    nem no log de envio).
 *  - `valor()`/`todos()`: usados por CatalogoService::preencherCamposConhecidos()
 *    pra copiar os campos da tarefa do AD pras tarefas seguintes DO MESMO
 *    chamado (pedido explícito do usuário, que entende e aceita que isso
 *    inclui CPF/Matrícula/etc - diferente do e-mail, que continua restrito).
 */
class ExtracaoCampoService
{
    public static function nome(string $conteudoHtml): string
    {
        return self::valor($conteudoHtml, 'Nome');
    }

    public static function email(string $conteudoHtml): string
    {
        return self::valor($conteudoHtml, 'E-mail');
    }

    /**
     * O conteúdo é HTML (TinyMCE) com uma linha por rótulo, ex.:
     * "Nome: João Silva<br>CPF: ...<br>E-mail: joao@ex.com<br>...". Vira
     * texto puro (uma linha por rótulo) antes de procurar o valor.
     */
    public static function valor(string $conteudoHtml, string $rotulo): string
    {
        $texto = html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $conteudoHtml)), ENT_QUOTES, 'UTF-8');
        $linhas = preg_split('/\r\n|\r|\n/', $texto);

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (preg_match('/^' . preg_quote($rotulo, '/') . '\s*:\s*(.+)$/iu', $linha, $m)) {
                return trim($m[1]);
            }
        }

        return '';
    }

    /**
     * @param string[] $rotulos aceita com ou sem o ":" final (ex.: "Nome" ou "Nome:")
     * @return array<string, string> rótulo (exatamente como veio em $rotulos) => valor, só os não-vazios
     */
    public static function todos(string $conteudoHtml, array $rotulos): array
    {
        $valores = [];
        foreach ($rotulos as $rotulo) {
            $valor = self::valor($conteudoHtml, rtrim($rotulo, ':'));
            if ($valor !== '') {
                $valores[$rotulo] = $valor;
            }
        }
        return $valores;
    }
}
