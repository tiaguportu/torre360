# Requerimentos Online da Família (Onda 80) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade. Faz parte do [índice das ondas 80 a 88](ondas_80_a_88_indice.md).

## Contexto

A família pede coisas à secretaria o ano inteiro: segunda via de declaração ou de histórico, atualização de endereço e
telefone, troca de turma ou de turno, mudança do responsável financeiro, autorização de retirada, transferência. Hoje cada
pedido existe como uma tela própria **ou** chega por WhatsApp e balcão, sem protocolo, sem prazo e sem registro de quem
atendeu. A secretaria não consegue dizer quantos pedidos estão abertos, nem a família consegue acompanhar o que pediu.

## O que já existe (conferido no código)

- **Declarações** (`SolicitacaoDocumento`, Portal → *Solicitações de documentos*): protocolo, situação (solicitado, em análise,
  disponível, rejeitado), modelo editável (`TemplateDocumento`, Onda 69), verificação por QR e assinatura digital (Onda 74).
  É o único fluxo de "pedido com resposta em documento" que a família já usa.
- **Cancelamento de matrícula e distrato** (Onda 61, Portal → *Cancelamento de matrícula*) e **rematrícula** (Portal →
  *Rematrícula*): cada um com tela, regra e fila da Minha Mesa próprias.
- **Central de Atendimento** (`AtendimentoChamado`, Portal e painel): texto livre, setor, prioridade e avaliação. Não tem
  formulário por tipo, prazo nem resultado estruturado (ver [Onda 83](atendimento_unificado_roadmap.md)).
- **Transferência escolar** (`TransferenciasEscolares`): só a secretaria registra; a família não pede.
- `aluno_responsavel.permissao_retirada`: o dado de quem pode retirar o aluno existe, mas só a secretaria o altera.
- **Dados cadastrais**: a família não edita a ficha da pessoa pelo Portal (só o Portal de Admissão, do candidato, recebe
  dados antes da matrícula).
- A **Minha Mesa** (`FontePendencia`) é o lugar natural da fila de pedidos para atender.

## Escopo proposto

### Catálogo de tipos de requerimento
`TipoRequerimento`: nome, orientação à família, campos do formulário (texto, data, escolha, anexo), **prazo de resposta em dias
úteis**, setor que atende, se exige anexo, se **gera documento** (um `TemplateDocumento`) e se o responsável do documento assina.
Entram de saída: segunda via de declaração e de histórico, atualização de contato e de endereço, troca de responsável
financeiro, autorização de retirada, pedido de troca de turma ou de turno e pedido de transferência. Os fluxos que já existem
(declaração, cancelamento, rematrícula) aparecem no catálogo como **atalhos para a tela atual**, sem migrar nada.

### Pedido e atendimento
- `Requerimento`: protocolo, matrícula, quem pediu, tipo, dados do formulário, situação (solicitado, em análise, aguardando a
  família, atendido, indeferido), prazo, responsável pelo atendimento, resposta e anexos; `RequerimentoEvento` guarda cada
  passo.
- **Portal:** *Requerimentos* com o catálogo e *Meus pedidos* (situação, prazo e resposta).
- **Secretaria:** lista com filtros por tipo, situação e prazo; o atendimento mostra o que foi pedido ao lado do dado atual.
- **Minha Mesa:** fila *Requerimentos para atender*, atrasada quando o prazo do tipo passa.

### Regras
- **Nenhuma alteração é aplicada sozinha.** Atualização cadastral e troca de responsável mostram a diferença (antes e depois) e só
  vão para a ficha depois da conferência da secretaria, que registra quem aplicou.
- Só pede quem é responsável legal ou financeiro da matrícula (a mesma regra de acesso da família que o sistema já usa).
- Requerimento que gera documento usa o modelo, o QR e a assinatura já existentes.

## Base legal

LGPD, art. 18, III: a correção de dados incompletos, inexatos ou desatualizados é **direito do titular**. O tipo *Atualização
cadastral* é o canal natural desse direito e deve entrar no registro do [Portal do Titular (Onda 34)](portal_titular_lgpd_roadmap.md),
em vez de nascer como uma tela paralela.

## Dependências e riscos

- **Aviso à família:** o Portal da família **ganhou o sino** (`databaseNotifications`) em 2026-10-28, mas o chamado atual não grava
  notificação nem avisa ninguém. Sem a [Central de Avisos (Onda 70)](central_avisos_familia_roadmap.md) ou, no mínimo, um e-mail por mudança
  de situação, o pedido responde para ninguém. É pré-requisito prático.
- **Formulário configurável é a parte cara.** Começar com campos fixos por tipo (código) e só depois abrir a edição pelo
  administrador.
- **Anexos de pessoas e menores:** seguir a lista de tipos de arquivo (`App\Support\TiposArquivo`), o armazenamento privado e o
  descarte da [Onda 79](guarda_descarte_documentos_roadmap.md).
- Não duplicar a declaração: ela continua no fluxo atual; migrar para o catálogo é decisão posterior.

## Fusão sugerida

**Atendimento da família (80 + 83 + 70).** Requerimento e chamado têm o mesmo ciclo (protocolo, responsável, prazo, resposta,
aviso). A diferença é o resultado: o requerimento termina em documento ou alteração; o chamado, numa conversa. Um motor de
"solicitação" com tipo atende os dois e evita duas filas e dois históricos. Ver o [índice](ondas_80_a_88_indice.md#fusões-sugeridas).
