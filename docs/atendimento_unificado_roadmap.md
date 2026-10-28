# Atendimento Unificado (Onda 83) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade. Faz parte do [índice das ondas 80 a 88](ondas_80_a_88_indice.md).

## Contexto

A Central de Atendimento recebe a dúvida da família e a leva a um setor. Funciona como uma caixa de entrada: o chamado abre,
alguém responde se lembrar de abrir a lista, e a família avalia no fim. Falta o que faz de uma caixa de entrada um atendimento:
**prazo**, **aviso**, **respostas prontas** e **uma resposta antes de abrir o chamado**. Hoje a escola não sabe quanto tempo a
família espera, e a família não sabe se alguém viu a mensagem.

## O que já existe (conferido no código)

- `AtendimentoChamado`: protocolo (`ATD-AAAA-XXXXX`), setor, matrícula, solicitante, responsável pelo atendimento, assunto,
  **prioridade** (baixa a urgente), **situação** (aberto, em andamento, aguardando solicitante, resolvido, fechado), **avaliação de
  1 a 5 com comentário** e data de fechamento. `AtendimentoMensagem` (texto e anexo, de usuário ou de pessoa) e `AtendimentoSetor`
  (nome, descrição, e-mail de notificação, ordem).
- Telas: **Central de Atendimento** no Portal (abrir, responder, avaliar) e a lista do painel, com ação de responder.
- Para o CRM já existem as peças que faltam aqui: **SLA de primeira resposta em horário comercial** (`LeadSlaService`, parâmetros em
  `config/crm.php`) e **mensagens rápidas** (`MensagemWhatsappTemplate`).
- O **assistente do painel** (`AssistantChatBubble`, `GeminiAgentService`) responde dúvidas de **uso do sistema** à equipe, com base
  no manual. Não é uma base de respostas para famílias.
- `MacrosDocumentoService` (Onda 69): variáveis do aluno, do responsável e da escola para preencher um texto.

### O que não existe — e dois achados
- **SLA, atribuição automática, modelos de resposta, categorias por assunto e fila na Minha Mesa.**
- **Nenhum aviso**: abrir ou responder um chamado não avisa a equipe nem a família (os `Notification::make()` das telas são só a
  mensagem de tela, não notificação gravada). O campo **`AtendimentoSetor.email_notificacao` é cadastrado e nunca lido**: a tela
  promete um e-mail que o sistema não envia.
- O Portal não tem sino (sem `databaseNotifications`), então a resposta só chega a quem volta à Central para olhar.

## Escopo proposto

1. **Avisos (primeiro, porque é o que falta de mais básico).** Chamado novo avisa o setor (o e-mail cadastrado passa a valer, e o
   sino da equipe); resposta da escola avisa a família pelo canal da [Onda 70](central_avisos_familia_roadmap.md) (e, até lá, por
   e-mail com o `CanalMensagem`); resposta da família reabre e avisa o responsável.
2. **SLA por setor e prioridade:** prazo de primeira resposta e de resolução, contado em horário comercial (reaproveitar a conta do
   `LeadSlaService`). O chamado mostra "vence em", e o setor vê quanto da fila está no prazo.
3. **Atribuição:** *assumir* (como o lead), reatribuir, e distribuição simples por setor (rodízio entre quem atende). Fila
   **Chamados sem resposta / com prazo estourado** na Minha Mesa.
4. **Respostas prontas** por setor, com variáveis do aluno e do responsável (as macros da Onda 69) e revisão antes de enviar.
5. **Categorias de assunto** por setor (segunda via de boleto, horário, material...), que alimentam o relatório e a base de perguntas.
6. **Perguntas frequentes no Portal:** artigos curtos, mantidos pela secretaria, **sugeridos antes de abrir o chamado** (pelo
   assunto digitado). Reduz o volume e dá resposta imediata.
7. **Regras de fechamento:** chamado *aguardando solicitante* há N dias fecha sozinho com aviso; reabrir dentro de um prazo.
8. **Relatório** (fonte da [Onda 72](relatorios_configuraveis_agendados_roadmap.md)): tempo de primeira resposta e de resolução por
   setor e a nota média, por mês.

## Base legal

LGPD, art. 9º (o titular tem direito a informação clara sobre o tratamento) e art. 18 (peticionar ao controlador): o chamado é
também o canal do titular e **tem prazo**. Pedidos do titular entram como assunto próprio, com o prazo de 15 dias do
[Portal do Titular](portal_titular_lgpd_roadmap.md). CDC, art. 4º e 6º: informação adequada e clara ao consumidor.

## Dependências e riscos

- **Avisos antes de SLA:** medir prazo de um chamado que ninguém é avisado de ver só produz um relatório de atraso.
- **Perguntas frequentes com IA para famílias é risco, não atalho.** Uma resposta errada sobre valor, prazo ou contrato vira
  compromisso da escola. Começar com artigos **escritos por pessoas**; se houver IA, restrita a esses artigos e citando a fonte.
- Conteúdo do chamado e anexos são dado pessoal de menores: armazenamento privado e a política de descarte da
  [Onda 79](guarda_descarte_documentos_roadmap.md).
- Atendimento fora do horário: a mensagem de "recebemos, respondemos em até X horas úteis" já reduz a ansiedade e é barata.

## Fusão sugerida

**Atendimento da família (83 + 80 + 70).** Chamado e [requerimento](requerimentos_online_familia_roadmap.md) têm protocolo,
responsável, prazo, resposta e aviso. Um motor de solicitação com tipo evita duas filas; a Central de Avisos é o canal de saída dos
dois. Ver o [índice](ondas_80_a_88_indice.md#fusões-sugeridas).
