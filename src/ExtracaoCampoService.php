<?php

namespace GlpiPlugin\Admissaoacessos;

/**
 * Lê `Nome:` e `E-mail:` do CONTEÚDO de uma tarefa (texto que o
 * coordenador digitou em cima do modelo padrão) - nunca `CPF:` nem
 * qualquer outro dado pessoal, por decisão explícita do processo.
 */
class ExtracaoCampoService
{
    public static function nome(string $conteudoHtml): string
    {
        return self::valorDoRotulo($conteudoHtml, 'Nome');
    }

    public static function email(string $conteudoHtml): string
    {
        return self::valorDoRotulo($conteudoHtml, 'E-mail');
    }

    /**
     * O conteúdo é HTML (TinyMCE) com uma linha por rótulo, ex.:
     * "Nome: João Silva<br>CPF: ...<br>E-mail: joao@ex.com<br>...". Vira
     * texto puro (uma linha por rótulo) antes de procurar o valor.
     */
    private static function valorDoRotulo(string $conteudoHtml, string $rotulo): string
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
}
