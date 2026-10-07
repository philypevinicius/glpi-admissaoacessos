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
