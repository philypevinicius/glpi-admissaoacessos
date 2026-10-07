# Acessos da admissão

Plugin GLPI que organiza o processo de liberação de acessos de um(a)
novo(a) funcionário(a), sem mudar o fluxo que o time já usa:

1. O requerente abre um chamado de admissão.
2. O coordenador clica em **Adicionar tarefas de acesso** (dentro do
   próprio chamado) e escolhe os sistemas - o plugin cria uma tarefa
   nativa do GLPI pra cada um, já com o texto padrão.
3. Os técnicos liberam o acesso e marcam cada tarefa como concluída.
4. Ao fechar o chamado, o plugin manda sozinho um e-mail de boas-vindas
   pro e-mail corporativo da pessoa admitida, listando o que foi
   liberado.

Tudo rodando em cima de mecanismos nativos do GLPI (tarefa de chamado,
"promover tarefa em chamado", fila de notificação) - o plugin não
inventa telas novas onde o GLPI já resolve.

## Instalação

```
php bin/console plugin:install admissaoacessos
php bin/console plugin:activate admissaoacessos
```

A instalação já cria o catálogo com os sistemas do processo atual
(SGTEC, FLUIG, FOCO etc.), cada um com seu modelo de tarefa.

## Configuração inicial

Em **Configurar > Acessos da admissão**:

1. **Catálogo de sistemas** - revise os 13 sistemas já cadastrados
   (texto do e-mail, tipo de acesso). Ajuste o que for preciso.
2. **Configuração**:
   - Escolha a(s) categoria(s) de chamado que são de admissão - sem
     isso, o botão de adicionar tarefas não aparece em NENHUM chamado.
   - Escolha o modelo de tarefa "base" (o do AD) - o envio do e-mail
     espera que ela esteja concluída antes de mandar.
   - Defina a senha padrão (só necessária se algum sistema usar
     `{{senha_padrao}}`).
   - Ajuste o assunto/texto do e-mail de boas-vindas.
3. Em **Configurar > Perfis > [perfil]**, aba "Acessos da admissão",
   libere "Gerenciar catálogo de sistemas e configuração" pro(s)
   perfil(is) de coordenação.

## Cadastrar um sistema novo

1. **Configurar > Acessos da admissão > Novo sistema**.
2. Preencha nome, tipo de acesso e o texto que vai pro e-mail.
3. Marque **"Criar/atualizar o modelo de tarefa nativo com o texto
   padrão"** pra já gerar o modelo de tarefa certo (nome/CPF/matrícula/
   etc, no formato que o processo usa).
4. Salve.

## Testes

Este ambiente GLPI não tem PHPUnit/PHPStan instalados (é a imagem Docker
oficial, sem as dependências de desenvolvimento) - os testes aqui são
scripts PHP standalone, mesmo padrão já usado em
`plugins/dashglpi/tests/filtro_logica_test.php`:

```
php tests/extracao_campo_test.php
php tests/montagem_lista_test.php
```

O primeiro carrega a classe REAL `ExtracaoCampoService` (não depende de
`$DB`/GLPI, então dá pra testar direto) - inclui o caso de segurança mais
importante: confirmar que `CPF:` nunca é extraído. O segundo reimplementa
isoladamente o formato do log anti-duplicidade e a ordenação por catálogo
(as duas peças que vivem dentro de classes que dependem de `$DB`/GLPI pra
carregar). Todo o resto do plugin foi verificado ao vivo neste ambiente
(ver commits) - sem suíte de testes com bootstrap completo do GLPI
disponível aqui, essa é a cobertura automatizada possível.

## Compatibilidade com o glpipulseui

Os dois plugins rodam ativos juntos neste ambiente (GLPI 11.0.8) desde
que o `admissaoacessos` foi construído, sem nenhum conflito observado.
Checagem formal, comparando `setup.php`/`hook.php`/`src/` dos dois:

| Item | Resultado |
|---|---|
| Tabelas de banco | Sem colisão - glpipulseui não cria tabela própria |
| Direito de perfil (ProfileRight) | Sem colisão - nomes distintos |
| Hooks ($PLUGIN_HOOKS) | Sem colisão - `item_update[Ticket]` e `timeline_actions` são exclusivos do admissaoacessos |
| Namespace/classes PHP | Sem colisão - `GlpiPlugin\Admissaoacessos\*` vs `GlpiPlugin\Glpipulseui\*` |
| Menu (redefine_menus) | Sem colisão - chaves diferentes no array `$menus` |
| Assets JS/CSS | Sem colisão de nome/ID/classe (só herda visualmente a classe nativa `.btn-outline-secondary`, que o tema do glpipulseui retematiza - efeito cosmético esperado, não é bug) |

## Solução de problemas

- **O botão "Adicionar tarefas de acesso" não aparece no chamado** -
  confira se a categoria do chamado está marcada em Configuração, e se
  o usuário logado tem direito de criar tarefa nesse chamado.
- **O e-mail não foi enviado ao fechar o chamado** - o plugin grava um
  acompanhamento PRIVADO no próprio chamado explicando o motivo (tarefa
  pendente, e-mail não encontrado, senha padrão não configurada, etc).
  Resolva o que falta e use o botão "Reenviar e-mail de acessos".
- **Quero ver o e-mail antes de mandar** - botão "Ver e-mail" na
  timeline do chamado (só visível pra quem tem o direito de
  configuração).
