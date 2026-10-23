# Comunicados com Ciência e Enquetes (Onda 33) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Comunicados importantes (mudança de horário, surto de doença, passeio, regras) precisam de **prova
de que a família leu**. Para decisões simples (data de reunião, interesse em uma atividade), uma
enquete de uma pergunta é mais rápida que um questionário completo. Hoje a escola envia mensagem em
massa e não sabe quem viu.

## O que já existe (ponto de partida)

- `ComunicacaoEmMassa`/`ComunicacaoEmMassaService` e `CanalMensagemManager` (canais de e-mail e de
  push por FCM): disparo para públicos filtrados, com descadastro (`DescadastroController`).
  WhatsApp hoje é apoio manual à equipe (`MensagemWhatsappTemplate`), não canal de disparo.
- `Questionario*` (blocos, perguntas, alvo, resposta): pesquisas completas, com resultado.
- `EventoEscolar` com `EventoConfirmacao` (confirmação de presença) e a página **Eventos** do portal.
- `Consentimentos` (portal): padrão de "aceite" pelo responsável.
- Não existe comunicado com **ciente rastreado por responsável** nem enquete rápida.

## Escopo

### Modelos novos
- `Comunicado`: título, corpo, anexos, público (turmas, séries, todos), exige ciência (bool),
  prazo de ciência, canais de aviso.
- `ComunicadoDestinatario`: responsável/matrícula, entregue em, **ciente em** (data/hora, IP).
- `Enquete`: pergunta, opções, público, prazo, anônima ou identificada; `EnqueteVoto`.

### Telas
- Criação do comunicado com pré-visualização e envio programado.
- **Painel de ciência**: quem leu, quem não, botão de reenvio só para pendentes.
- Portal: lista de comunicados, botão "Estou ciente", votação de enquetes.
- Resultado da enquete com totais (sem identificar votos quando anônima).

### Regras
- Um voto por responsável e aluno; o responsável com dois filhos na mesma turma vota uma vez.
- "Ciente" é por responsável (não por aluno), com data e hora registradas.
- Lembrete automático aos pendentes antes do prazo.

## Dependências já satisfeitas

- Disparo em massa, portal e notificações.

## Risco e observação

- A ciência prova que o responsável **abriu e confirmou**, não que concordou: o texto do botão deve
  ser claro.
- Respeitar o descadastro de canais; comunicado obrigatório deve ter canal de portal sempre ativo.

## Fusão sugerida

Implementar junto com a **Onda 70** ([Central de Avisos à Família](central_avisos_familia_roadmap.md)) como uma onda só, "Comunicação com a família". As duas precisam do mesmo destinatário (responsáveis do aluno e responsável financeiro), do mesmo registro de entrega e do mesmo sino no Portal. O aviso transacional (cancelamento aprovado, documento para assinar) e o comunicado com ciência rastreada passam a ser dois tipos sobre o mesmo modelo, em vez de dois mecanismos de entrega.

O resumo diário da equipe (Onda 47) usa as filas da Mesa de Pendências (Onda 71) e fica fora desta fusão.

Veja o [índice das ondas 68 a 71](ondas_68_a_71_indice.md#fusões-sugeridas-para-dar-completude).
