<?php

/**
 * Teste standalone (sem GLPI) da extração de campos do conteúdo de uma
 * tarefa - este é o ÚNICO pedaço de lógica do plugin sem nenhuma
 * dependência de $DB/Session/classes nativas, então carrega e testa a
 * classe REAL (não uma reimplementação) - mesmo padrão de
 * plugins/dashglpi/tests/filtro_logica_test.php.
 *
 * Dois escopos bem diferentes (ver docblock de ExtracaoCampoService):
 *  - nome()/email(): usados pro e-mail de boas-vindas - NUNCA leem CPF.
 *  - valor()/todos(): usados pra copiar campos entre tarefas do mesmo
 *    chamado - leem QUALQUER rótulo, incluindo CPF, a pedido explícito
 *    do usuário (confirmado por ele, entendendo a implicação de LGPD).
 *
 * Uso:
 *   php tests/extracao_campo_test.php
 */

require_once __DIR__ . '/../src/ExtracaoCampoService.php';

use GlpiPlugin\Admissaoacessos\ExtracaoCampoService;

$total = 0;
$falhas = [];

function verificar(string $descricao, callable $teste): void
{
    global $total, $falhas;
    $total++;
    try {
        $teste();
        echo "  ok    {$descricao}\n";
    } catch (Throwable $e) {
        $falhas[] = "{$descricao}: {$e->getMessage()}";
        echo "  FALHA {$descricao}\n        {$e->getMessage()}\n";
    }
}

function igual($esperado, $obtido, string $contexto = ''): void
{
    if ($esperado !== $obtido) {
        throw new RuntimeException(sprintf(
            '%sesperava %s, obteve %s',
            $contexto ? "{$contexto}: " : '',
            var_export($esperado, true),
            var_export($obtido, true)
        ));
    }
}

echo "\nExtração de Nome:/E-mail: do conteúdo da tarefa (admissaoacessos)\n";
echo str_repeat('-', 70) . "\n";

// Conteúdo real, no formato que o CatalogoService gera (nl2br + <br />
// intercalado com \n de verdade - ver CatalogoService::corpoParaHtml()).
$conteudoCompleto =
    "Favor liberar acesso no FLUIG para o(a) usuário(a) abaixo:<br />\n<br />\n" .
    "Nome: João da Silva<br />\n" .
    "CPF: 123.456.789-00<br />\n" .
    "Matrícula: 00123<br />\n" .
    "Cargo: Analista<br />\n" .
    "Telefone: (11) 99999-0000<br />\n" .
    "E-mail: joao.silva@empresa.com<br />\n" .
    "Início da contratação: 01/10/2026<br />\n" .
    "Término da contratação:<br />\n" .
    "Unidade: UTIC<br />\n" .
    "Empresa:<br />\n" .
    "Perfil a ser clonado: Técnico padrão";

verificar('le o Nome: do meio do conteudo', function () use ($conteudoCompleto) {
    igual('João da Silva', ExtracaoCampoService::nome($conteudoCompleto));
});

verificar('le o E-mail: do meio do conteudo', function () use ($conteudoCompleto) {
    igual('joao.silva@empresa.com', ExtracaoCampoService::email($conteudoCompleto));
});

verificar('nome()/email() (usados no e-mail de boas-vindas) NUNCA trazem CPF', function () use ($conteudoCompleto) {
    $nome = ExtracaoCampoService::nome($conteudoCompleto);
    $email = ExtracaoCampoService::email($conteudoCompleto);
    if (str_contains($nome, '123.456.789-00') || str_contains($email, '123.456.789-00')) {
        throw new RuntimeException('CPF vazou para nome()/email()');
    }
});

verificar('valor() le CPF explicitamente quando pedido (copia entre tarefas, nao vai no e-mail)', function () use ($conteudoCompleto) {
    igual('123.456.789-00', ExtracaoCampoService::valor($conteudoCompleto, 'CPF'));
});

verificar('todos() le varios rotulos de uma vez, incluindo CPF', function () use ($conteudoCompleto) {
    $campos = ExtracaoCampoService::todos($conteudoCompleto, ['Nome:', 'CPF:', 'Matrícula:', 'Telefone:']);
    igual('João da Silva', $campos['Nome:']);
    igual('123.456.789-00', $campos['CPF:']);
    igual('00123', $campos['Matrícula:']);
    igual('(11) 99999-0000', $campos['Telefone:']);
});

verificar('todos() so inclui no resultado os rotulos com valor preenchido', function () use ($conteudoCompleto) {
    $campos = ExtracaoCampoService::todos($conteudoCompleto, ['Empresa:', 'Unidade:']);
    igual(false, array_key_exists('Empresa:', $campos), 'Empresa: vazio no conteudo de teste');
    igual('UTIC', $campos['Unidade:']);
});

verificar('campo nao preenchido (so o rotulo) devolve string vazia', function () {
    $conteudo = "Nome: Maria<br />\nE-mail:<br />\nCPF:";
    igual('', ExtracaoCampoService::email($conteudo));
});

verificar('rotulo ausente devolve string vazia (nao quebra)', function () {
    igual('', ExtracaoCampoService::nome('Conteudo qualquer sem rotulos.'));
});

verificar('funciona com <br> e <br/> alem de <br />', function () {
    $variado = "Nome: Ana<br>CPF: 1<br/>E-mail: ana@ex.com<br />Telefone: 1";
    igual('Ana', ExtracaoCampoService::nome($variado));
    igual('ana@ex.com', ExtracaoCampoService::email($variado));
});

verificar('entidades HTML (acento) decodificadas no valor extraido', function () {
    $comEntidade = 'Nome: Jo&atilde;o<br />E-mail: joao@ex.com';
    igual('João', ExtracaoCampoService::nome($comEntidade));
});

verificar('busca e-mail em QUALQUER tarefa quando a base nao tem (fallback simulado aqui so pelo metodo puro)', function () {
    // ExtracaoCampoService em si so olha UM conteudo por vez - o fallback
    // entre tarefas e responsabilidade de EmailBoasVindasService::buscarEmail(),
    // que precisa de $DB/Ticket reais e por isso nao entra neste teste
    // standalone (ver docblock do arquivo).
    igual('x@x.com', ExtracaoCampoService::email('E-mail: x@x.com'));
});

echo str_repeat('-', 70) . "\n";
if ($falhas === []) {
    echo "{$total} testes, todos passaram.\n\n";
    exit(0);
}

echo count($falhas) . " de {$total} FALHARAM:\n";
foreach ($falhas as $falha) {
    echo "  - {$falha}\n";
}
echo "\n";
exit(1);
