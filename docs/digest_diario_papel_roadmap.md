# Digest Diário por Papel (Onda 47) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Cada papel tem um conjunto de pendências que muda todo dia, e o sistema só mostra isso a quem
abre a tela certa. Um resumo curto por e-mail ou push, no início do dia, com **só o que exige
ação**, reduz esquecimento e dá motivo para abrir o sistema.

## O que já existe (ponto de partida)

- Agendador (`routes/console.php`) com rotinas diárias e `Notification` do Filament
  (`NotificationService`, `FcmService`).
- `CanalMensagemManager` com canais de e-mail (`EmailCanal`) e push (`FcmCanal`).
- Fontes das pendências, cada uma com seu serviço ou widget:
  - secretaria: matrículas pendentes (`MatriculasPendentesWidget`), contratos pendentes,
    solicitações de documentos;
  - financeiro: contas a pagar vencendo, inadimplência (`ReguaCobrancaService`);
  - coordenação: pareceres aguardando revisão (Onda 30), ajustes de ponto pendentes (Onda 41),
    reservas aguardando aprovação (Onda 21), planos de manutenção vencidos (Onda 39);
  - professor: frequência pendente (`FrequenciaPendenteWidget`), planos de aula do dia;
  - comercial: follow-ups (`AlertaLeadsService`). O SLA de 1ª resposta (`LeadSlaService`) foi retirado em 2026-10-28.
- Não existe um resumo consolidado nem preferência de recebimento por usuário.

## Escopo

### Modelos novos
- `DigestPreferencia`: usuário, ativo, canal (e-mail, push), horário, quais blocos receber.
- `DigestEnvio` (opcional): registro do envio para auditoria e para evitar duplicidade.

### Estrutura
- Interface `DigestBloco` implementada por cada fonte (`titulo()`, `itens(User)`,
  `permissao()`), registrada num catálogo. Novos módulos acrescentam o seu bloco sem alterar o
  núcleo.
- Comando agendado que monta o digest por usuário, **só com blocos que ele pode ver** e só se houver
  item; não envia digest vazio.

### Telas
- Preferências do usuário (ligar/desligar, horário, canal e blocos).
- Prévia do digest para o próprio usuário.

### Regras
- Respeito às permissões do papel ativo e ao recorte do professor (`EscopoProfessor`).
- Conteúdo mínimo: contagem e link; **nenhum dado sensível no corpo do e-mail** (nome de aluno com
  dado de saúde, valores individuais).
- Limite de itens por bloco, com "e mais N".

## Dependências já satisfeitas

- Agendador, canais de notificação e fontes de pendência.

## Risco e observação

- Fadiga de notificação: começar com poucos blocos, medir abertura e permitir silenciar.
- E-mail com link para o painel exige que o destinatário esteja autenticado ao abrir.

## Fusão com a Mesa de Pendências (Onda 71)

A [Mesa de Pendências](mesa_pendencias_roadmap.md) já foi implementada e criou o que esta onda previa como "interface
`DigestBloco` + catálogo": `App\Contracts\FontePendencia`, com `config/mesa_pendencias.php`. O digest não precisa de
um segundo catálogo: ele passa a ser **outro consumidor das mesmas filas** (`MesaDePendencias::filas($usuario)`),
mandando por e-mail ou push só a contagem e o link de cada fila. O que continua faltando nesta onda é a preferência por
usuário (ativo, canal, horário), o comando agendado, a prévia e a regra de não enviar digest vazio. Cada módulo que
ganhar uma fila na Mesa ganha o bloco no digest sem trabalho extra.
