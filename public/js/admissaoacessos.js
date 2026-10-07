/**
 * Plugin Acessos da admissão - modal do botão "Adicionar tarefas de
 * acesso" (o botão em si é renderizado pelo servidor via hook nativo
 * timeline_actions, não por este arquivo - ver hook.php). Carregado em
 * toda página autenticada (mesmo padrão do assistente), mas só faz algo
 * quando encontra o botão no DOM.
 */
(function () {
    'use strict';

    var raiz = (window.CFG_GLPI && CFG_GLPI.root_doc) || '';

    function escaparHtml(texto) {
        var div = document.createElement('div');
        div.textContent = texto == null ? '' : String(texto);
        return div.innerHTML;
    }

    /**
     * O token CSRF não é exposto por nenhuma variável JS global nesta
     * versão do GLPI - lido do próprio formulário nativo do chamado, que
     * sempre tem um campo `_glpi_csrf_token` escondido pra seu próprio
     * submit (editar campos, etc).
     */
    function tokenCsrf() {
        var campo = document.querySelector('input[name="_glpi_csrf_token"]');
        return campo ? campo.value : '';
    }

    function fecharModal() {
        var modal = document.getElementById('admissaoacessos-modal');
        if (modal) modal.remove();
    }

    function criarModal(ticketsId, sistemas) {
        fecharModal();

        var modal = document.createElement('div');
        modal.id = 'admissaoacessos-modal';
        modal.className = 'admissaoacessos-modal';

        var itensHtml = '';
        if (sistemas.length === 0) {
            itensHtml = '<div class="admissaoacessos-vazio">' +
                'Nenhum sistema ativo no catálogo.</div>';
        } else {
            sistemas.forEach(function (sistema) {
                itensHtml +=
                    '<label class="admissaoacessos-item">' +
                        '<input type="checkbox" value="' + sistema.id + '">' +
                        '<span>' + escaparHtml(sistema.name) + '</span>' +
                    '</label>';
            });
        }

        modal.innerHTML =
            '<div class="admissaoacessos-backdrop"></div>' +
            '<div class="admissaoacessos-caixa">' +
                '<div class="admissaoacessos-cabecalho">' +
                    '<strong>Adicionar tarefas de acesso</strong>' +
                    '<button type="button" class="admissaoacessos-fechar" aria-label="Fechar">&times;</button>' +
                '</div>' +
                '<div class="admissaoacessos-corpo">' +
                    '<div class="admissaoacessos-lista">' + itensHtml + '</div>' +
                '</div>' +
                '<div class="admissaoacessos-rodape">' +
                    '<button type="button" class="btn btn-outline-secondary admissaoacessos-cancelar">Cancelar</button>' +
                    '<button type="button" class="btn btn-primary admissaoacessos-confirmar">Criar tarefas</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);

        modal.querySelector('.admissaoacessos-backdrop').addEventListener('click', fecharModal);
        modal.querySelector('.admissaoacessos-fechar').addEventListener('click', fecharModal);
        modal.querySelector('.admissaoacessos-cancelar').addEventListener('click', fecharModal);

        modal.querySelector('.admissaoacessos-confirmar').addEventListener('click', function () {
            var selecionados = Array.prototype.slice
                .call(modal.querySelectorAll('.admissaoacessos-item input:checked'))
                .map(function (campo) { return campo.value; });

            if (selecionados.length === 0) {
                return;
            }

            var botaoConfirmar = modal.querySelector('.admissaoacessos-confirmar');
            botaoConfirmar.disabled = true;

            var corpo = new URLSearchParams();
            corpo.set('tickets_id', ticketsId);
            corpo.set('_glpi_csrf_token', tokenCsrf());
            selecionados.forEach(function (id) { corpo.append('systems_ids[]', id); });

            fetch(raiz + '/plugins/admissaoacessos/ajax/criar_tarefas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: corpo.toString(),
            })
                .then(function (resposta) { return resposta.json(); })
                .then(function () {
                    // Recarrega a página pra mostrar as tarefas novas na
                    // timeline nativa - mais simples e confiável do que
                    // tentar desenhar os itens na mão aqui.
                    window.location.reload();
                })
                .catch(function () {
                    botaoConfirmar.disabled = false;
                });
        });
    }

    function abrirModal(botao) {
        var ticketsId = botao.dataset.ticketsId;

        fetch(raiz + '/plugins/admissaoacessos/ajax/listar_sistemas.php')
            .then(function (resposta) { return resposta.json(); })
            .then(function (sistemas) {
                criarModal(ticketsId, sistemas);
            });
    }

    document.addEventListener('click', function (evento) {
        var botao = evento.target.closest('.admissaoacessos-abrir-modal');
        if (botao) {
            abrirModal(botao);
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            fecharModal();
        }
    });
})();
