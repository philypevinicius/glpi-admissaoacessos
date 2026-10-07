/**
 * Filtros de tabela genéricos, usados nas 3 listas do Controle Interno
 * (Funcionários/Equipamentos/Notas fiscais). Tudo client-side de propósito:
 * as listas são pequenas (registro interno manual, não o volume de um
 * cadastro de ativos inteiro), então filtrar na hora, sem recarregar a
 * página, é mais simples que inventar um endpoint de busca por tela.
 *
 * Suporta, combinados com "E" entre si (uma linha só aparece se passar em
 * TODOS os filtros ativos ao mesmo tempo):
 *  - Busca livre:  <input data-busca-tabela="idDaTabela">
 *                  compara com o texto inteiro da linha.
 *  - Filtro de coluna: <select data-filtro-select="idDaTabela" data-filtro-col="chave">
 *                  compara com <td data-col="chave" data-valor="..."> da linha.
 *  - Filtro de data: <input type="date" data-filtro-data-de="idDaTabela">
 *                  e/ou data-filtro-data-ate="idDaTabela">, compara com
 *                  <tr data-data-iso="AAAA-MM-DD">.
 */
(function () {
    'use strict';

    var tabelasControladas = {};

    function registrarTabela(idTabela) {
        if (tabelasControladas[idTabela]) {
            return tabelasControladas[idTabela];
        }
        var tabela = document.getElementById(idTabela);
        if (!tabela) {
            return null;
        }
        var estado = {
            tabela: tabela,
            linhas: tabela.querySelectorAll('tbody tr'),
            busca: '',
            filtrosColuna: {}, // { chave: valor }
            dataDe: '',
            dataAte: '',
        };
        tabelasControladas[idTabela] = estado;
        return estado;
    }

    function linhaPassa(linha, estado) {
        if (estado.busca !== '' && linha.textContent.toLowerCase().indexOf(estado.busca) === -1) {
            return false;
        }

        for (var chave in estado.filtrosColuna) {
            var valorFiltro = estado.filtrosColuna[chave];
            if (!valorFiltro) {
                continue;
            }
            var celula = linha.querySelector('[data-col="' + chave + '"]');
            var valorCelula = celula ? (celula.dataset.valor !== undefined ? celula.dataset.valor : celula.textContent.trim()) : '';
            if (valorCelula !== valorFiltro) {
                return false;
            }
        }

        if (estado.dataDe || estado.dataAte) {
            var dataLinha = linha.dataset.dataIso || '';
            if (dataLinha === '') {
                return false;
            }
            if (estado.dataDe && dataLinha < estado.dataDe) {
                return false;
            }
            if (estado.dataAte && dataLinha > estado.dataAte) {
                return false;
            }
        }

        return true;
    }

    function aplicarFiltros(idTabela) {
        var estado = tabelasControladas[idTabela];
        if (!estado) {
            return;
        }
        estado.linhas.forEach(function (linha) {
            linha.style.display = linhaPassa(linha, estado) ? '' : 'none';
        });
    }

    document.querySelectorAll('[data-busca-tabela]').forEach(function (campo) {
        var idTabela = campo.dataset.buscaTabela;
        var estado = registrarTabela(idTabela);
        if (!estado) {
            return;
        }
        campo.addEventListener('input', function () {
            estado.busca = campo.value.trim().toLowerCase();
            aplicarFiltros(idTabela);
        });
    });

    document.querySelectorAll('[data-filtro-select]').forEach(function (select) {
        var idTabela = select.dataset.filtroSelect;
        var coluna = select.dataset.filtroCol;
        var estado = registrarTabela(idTabela);
        if (!estado) {
            return;
        }
        select.addEventListener('change', function () {
            estado.filtrosColuna[coluna] = select.value;
            aplicarFiltros(idTabela);
        });
    });

    document.querySelectorAll('[data-filtro-data-de]').forEach(function (campo) {
        var idTabela = campo.dataset.filtroDataDe;
        var estado = registrarTabela(idTabela);
        if (!estado) {
            return;
        }
        campo.addEventListener('change', function () {
            estado.dataDe = campo.value;
            aplicarFiltros(idTabela);
        });
    });

    document.querySelectorAll('[data-filtro-data-ate]').forEach(function (campo) {
        var idTabela = campo.dataset.filtroDataAte;
        var estado = registrarTabela(idTabela);
        if (!estado) {
            return;
        }
        campo.addEventListener('change', function () {
            estado.dataAte = campo.value;
            aplicarFiltros(idTabela);
        });
    });
})();
