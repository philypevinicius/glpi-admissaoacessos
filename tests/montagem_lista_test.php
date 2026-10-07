<?php

/**
 * Teste standalone (sem GLPI) de duas peças de lógica que vivem dentro
 * de classes que estendem \CommonDBTM (RegistroEnvio, EmailBoasVindasService
 * usa \Planning/\Ticket/$DB) - não dá pra carregar esses arquivos fora do
 * bootstrap do GLPI, então o ALGORITMO é reimplementado aqui, idêntico ao
 * código real, pra conferir a regra isolada. Mesmo padrão de
 * plugins/dashglpi/tests/filtro_logica_test.php - ver esse arquivo pro
 * precedente.
 *
 * Cobre:
 *   - RegistroEnvio::formatarListaSistemas() (formato ",1,2,3,")
 *   - a ordenação por catálogo (display_order) em
 *     EmailBoasVindasService::montarEmail() - monta um array indexado por
 *     "{display_order}_{id}" e dá ksort(SORT_NATURAL)
 *
 * Uso:
 *   php tests/montagem_lista_test.php
 */

// --- reimplementação isolada da lógica (ver docblock acima) -----------------

/** Idêntico a RegistroEnvio::formatarListaSistemas(). */
function formatarListaSistemas(array $idsSistemas): string
{
    if (empty($idsSistemas)) {
        return ',,';
    }
    return ',' . implode(',', array_map('intval', $idsSistemas)) . ',';
}

/** Idêntico à checagem usada em Sistema::podeExcluir() contra esse formato. */
function sistemaFoiUsado(string $sistemasEnviados, int $sistemaId): bool
{
    return str_contains($sistemasEnviados, ',' . $sistemaId . ',');
}

/**
 * Idêntico ao miolo de EmailBoasVindasService::montarEmail(): recebe
 * sistemas já filtrados (tarefa concluída + bate com o catálogo) como
 * [id, display_order, linha] e devolve as linhas na ordem do catálogo.
 */
function montarListaNaOrdemDoCatalogo(array $sistemas): string
{
    $porChave = [];
    foreach ($sistemas as $sistema) {
        $porChave[$sistema['display_order'] . '_' . $sistema['id']] = $sistema['linha'];
    }
    ksort($porChave, SORT_NATURAL);
    return implode("\n", $porChave);
}

// --- mini-runner (igual ao de filtro_logica_test.php) -----------------------

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

echo "\nMontagem do e-mail de boas-vindas - formato de log e ordenacao (admissaoacessos)\n";
echo str_repeat('-', 70) . "\n";

// --- formatarListaSistemas / sistemaFoiUsado -------------------------------

verificar('lista vazia vira ",,"', function () {
    igual(',,', formatarListaSistemas([]));
});

verificar('um sistema vira ",N,"', function () {
    igual(',7,', formatarListaSistemas([7]));
});

verificar('varios sistemas ficam separados por virgula, com virgula nas pontas', function () {
    igual(',1,4,13,', formatarListaSistemas([1, 4, 13]));
});

verificar('sistemaFoiUsado acha id no meio da lista', function () {
    igual(true, sistemaFoiUsado(',1,4,13,', 4));
});

verificar('sistemaFoiUsado NAO confunde prefixo (1 nao bate com 13)', function () {
    igual(false, sistemaFoiUsado(',4,13,', 1));
});

verificar('sistemaFoiUsado NAO confunde sufixo (4 nao bate com 14)', function () {
    igual(false, sistemaFoiUsado(',1,14,', 4));
});

verificar('sistemaFoiUsado em lista vazia nunca acha nada', function () {
    igual(false, sistemaFoiUsado(',,', 1));
});

// --- ordenacao por catalogo --------------------------------------------------

verificar('sistemas concluidos aparecem na ordem do catalogo, nao na ordem em que a tarefa foi concluida', function () {
    // SGTEC (ordem 1) concluido DEPOIS do FLUIG (ordem 4) - a lista final
    // tem que respeitar o catalogo, nao a ordem de chegada.
    $sistemas = [
        ['id' => 4, 'display_order' => 4, 'linha' => 'FLUIG: ...'],
        ['id' => 1, 'display_order' => 1, 'linha' => 'SGTEC: ...'],
    ];
    igual("SGTEC: ...\nFLUIG: ...", montarListaNaOrdemDoCatalogo($sistemas));
});

verificar('ordenacao numerica de verdade (10 depois de 2, nao antes)', function () {
    $sistemas = [
        ['id' => 10, 'display_order' => 10, 'linha' => 'GESCON: ...'],
        ['id' => 2,  'display_order' => 2,  'linha' => 'SGTEC/SGF: ...'],
    ];
    igual("SGTEC/SGF: ...\nGESCON: ...", montarListaNaOrdemDoCatalogo($sistemas));
});

verificar('sistema unico nao quebra (sem \\n sobrando)', function () {
    $sistemas = [['id' => 13, 'display_order' => 13, 'linha' => 'FOCO: ...']];
    igual('FOCO: ...', montarListaNaOrdemDoCatalogo($sistemas));
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
