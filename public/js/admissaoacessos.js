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
                var jaTem = !!sistema.ja_adicionado;
                itensHtml +=
                    '<label class="admissaoacessos-item' + (jaTem ? ' admissaoacessos-item-desabilitado' : '') + '">' +
                        '<input type="checkbox" value="' + sistema.id + '"' + (jaTem ? ' checked disabled' : '') + '>' +
                        '<span>' + escaparHtml(sistema.name) +
                        (jaTem ? ' <em class="admissaoacessos-ja-adicionado">(já adicionado)</em>' : '') +
                        '</span>' +
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
                    '<div class="admissaoacessos-lista admissaoacessos-lista-duas-colunas">' + itensHtml + '</div>' +
                '</div>' +
                '<div class="admissaoacessos-rodape admissaoacessos-rodape-com-contador">' +
                    '<span class="admissaoacessos-contador">0 sistemas selecionados</span>' +
                    '<div class="d-flex gap-2">' +
                        '<button type="button" class="btn btn-outline-secondary admissaoacessos-cancelar">Cancelar</button>' +
                        '<button type="button" class="btn btn-primary admissaoacessos-confirmar" disabled>Criar tarefas</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);

        var contador = modal.querySelector('.admissaoacessos-contador');
        var botaoConfirmar = modal.querySelector('.admissaoacessos-confirmar');

        function atualizarContador() {
            var marcados = modal.querySelectorAll('.admissaoacessos-item input:checked:not(:disabled)').length;
            contador.textContent = marcados + (marcados === 1 ? ' sistema selecionado' : ' sistemas selecionados');
            botaoConfirmar.textContent = marcados > 0 ? ('Criar ' + marcados + (marcados === 1 ? ' tarefa' : ' tarefas')) : 'Criar tarefas';
            botaoConfirmar.disabled = marcados === 0;
        }
        modal.querySelectorAll('.admissaoacessos-item input:not(:disabled)').forEach(function (campo) {
            campo.addEventListener('change', atualizarContador);
        });
        atualizarContador();

        modal.querySelector('.admissaoacessos-backdrop').addEventListener('click', fecharModal);
        modal.querySelector('.admissaoacessos-fechar').addEventListener('click', fecharModal);
        modal.querySelector('.admissaoacessos-cancelar').addEventListener('click', fecharModal);

        botaoConfirmar.addEventListener('click', function () {
            var selecionados = Array.prototype.slice
                .call(modal.querySelectorAll('.admissaoacessos-item input:checked:not(:disabled)'))
                .map(function (campo) { return campo.value; });

            if (selecionados.length === 0) {
                return;
            }

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

        fetch(raiz + '/plugins/admissaoacessos/ajax/listar_sistemas.php?tickets_id=' + ticketsId)
            .then(function (resposta) { return resposta.json(); })
            .then(function (sistemas) {
                criarModal(ticketsId, sistemas);
            });
    }

    /** Modal simples de texto - reusado tanto pra "Ver e-mail" quanto
     *  pro resultado de "Reenviar". */
    function criarModalTexto(titulo, corpoHtml) {
        fecharModal();

        var modal = document.createElement('div');
        modal.id = 'admissaoacessos-modal';
        modal.className = 'admissaoacessos-modal';
        modal.innerHTML =
            '<div class="admissaoacessos-backdrop"></div>' +
            '<div class="admissaoacessos-caixa">' +
                '<div class="admissaoacessos-cabecalho">' +
                    '<strong>' + escaparHtml(titulo) + '</strong>' +
                    '<button type="button" class="admissaoacessos-fechar" aria-label="Fechar">&times;</button>' +
                '</div>' +
                '<div class="admissaoacessos-corpo">' + corpoHtml + '</div>' +
            '</div>';

        document.body.appendChild(modal);
        modal.querySelector('.admissaoacessos-backdrop').addEventListener('click', fecharModal);
        modal.querySelector('.admissaoacessos-fechar').addEventListener('click', fecharModal);
    }

    function abrirPreviaEmail(botao) {
        var ticketsId = botao.dataset.ticketsId;

        fetch(raiz + '/plugins/admissaoacessos/ajax/previsualizar_email.php?tickets_id=' + ticketsId)
            .then(function (resposta) { return resposta.json(); })
            .then(function (dados) {
                if (!dados.ok) {
                    criarModalTexto('Ver e-mail', '<p class="admissaoacessos-vazio">' + escaparHtml(dados.motivo) + '</p>');
                    return;
                }
                // corpo_html já vem escapado pelo servidor (EmailBoasVindasService::
                // montarCorpoHtml() passa todo texto dinâmico por Html::entities_deep
                // antes de montar a tabela) - seguro inserir direto, sem escapar de novo.
                criarModalTexto('Ver e-mail',
                    '<div class="admissaoacessos-preview-cabecalho">' +
                        '<div><strong>De:</strong> ' + escaparHtml(dados.remetente || '') + '</div>' +
                        '<div><strong>Para:</strong> ' + escaparHtml(dados.destinatario) + '</div>' +
                        '<div><strong>Assunto:</strong> ' + escaparHtml(dados.assunto) + '</div>' +
                    '</div>' +
                    '<div class="admissaoacessos-preview-corpo">' + dados.corpo_html + '</div>'
                );
            });
    }

    function reenviarEmail(botao) {
        var ticketsId = botao.dataset.ticketsId;
        if (!window.confirm('Reenviar o e-mail de acessos deste chamado?')) {
            return;
        }

        var corpo = new URLSearchParams();
        corpo.set('tickets_id', ticketsId);
        corpo.set('_glpi_csrf_token', tokenCsrf());

        fetch(raiz + '/plugins/admissaoacessos/ajax/reenviar_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: corpo.toString(),
        })
            .then(function (resposta) { return resposta.json(); })
            .then(function (dados) {
                criarModalTexto('Reenviar e-mail de acessos',
                    '<p>' + (dados.enviado
                        ? 'E-mail reenviado.'
                        : 'Não foi possível reenviar: ' + escaparHtml(dados.motivo)) + '</p>'
                );
            });
    }

    document.addEventListener('click', function (evento) {
        var botaoTarefas = evento.target.closest('.admissaoacessos-abrir-modal');
        if (botaoTarefas) {
            abrirModal(botaoTarefas);
            return;
        }

        var botaoVerEmail = evento.target.closest('.admissaoacessos-ver-email');
        if (botaoVerEmail) {
            abrirPreviaEmail(botaoVerEmail);
            return;
        }

        var botaoReenviar = evento.target.closest('.admissaoacessos-reenviar-email');
        if (botaoReenviar) {
            reenviarEmail(botaoReenviar);
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            fecharModal();
        }
    });
})();
