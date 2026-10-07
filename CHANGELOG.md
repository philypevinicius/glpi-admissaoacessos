# Changelog

## 0.3.1

- Corrige `EmailBoasVindasService::tarefasDoChamado()`: a query filtrava
  por `is_deleted`, mas `glpi_tickettasks` não tem essa coluna (diferente
  de `glpi_tickets`) - achado ao fechar um chamado de verdade.
- Testes standalone (`tests/*.php`) para a extração de Nome:/E-mail: e
  pra ordenação por catálogo/formato do log anti-duplicidade.

## 0.2.0

- Botão "Adicionar tarefas de acesso" na timeline do chamado (hook
  nativo `timeline_actions`), criando `TicketTask` a partir dos
  `TaskTemplate` do catálogo.
- Conversão de tarefa em chamado: reaproveita o "Promote to Ticket"
  nativo do GLPI, sem código extra.
- Envio do e-mail de boas-vindas ao fechar o chamado, com validação
  (tarefas concluídas, tarefa base, e-mail encontrado, senha padrão
  configurada quando necessária), trava anti-duplicidade, botões "Ver
  e-mail" (pré-visualização) e "Reenviar e-mail de acessos".

## 0.1.0

- Esqueleto do plugin (direito `plugin_admissaoacessos_config`
  restrito a Coordenador/Admin/Super-Admin).
- Catálogo de sistemas (CRUD, com geração automática do modelo de
  tarefa nativo a partir do texto padrão).
- Configuração geral (categorias ITIL, modelo de tarefa base, senha
  padrão criptografada, texto do e-mail).
- Seed inicial com os 13 sistemas do processo de admissão.
