# Reserva de Espaços e Recursos (Onda 21)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda das telas (**Reservas de Espaços** e **Ocupação dos
> Espaços**, grupo **Calendário e Horários** do menu) e na seção 63 do `MANUAL_USUARIO.md`.

## Contexto

Escolas com auditório, quadra, laboratório ou biblioteca precisam agendar o uso pontual
desses espaços para eventos e atividades extraclasse (ensaio de formatura, aula extra de
Educação Física, palestra, gravação) sem conflitar com a grade horária fixa nem com outros
agendamentos pontuais.

`Sala` (Onda 4) já cadastrava o espaço e `GradeHorario` já o ligava à grade semanal
**recorrente**, com validação de conflito. Faltava o agendamento **pontual** (uma data e hora
específicas, fora da grade fixa).

## Como foi implementado

### Modelo
- `ReservaEspaco` (`reservas_espaco`): `sala_id`, `data`, `hora_inicio`, `hora_fim`,
  `solicitante_user_id`, `motivo`, `descricao`, `turma_id` (opcional), `status`
  (`StatusReservaEspaco`: **solicitada** / **confirmada** / **cancelada**),
  `decidido_por_user_id`, `decidido_em`, `justificativa_decisao`. Registra auditoria
  (`LogsActivity`, log `reserva_espaco`).
- `Sala` ganhou `exige_aprovacao_reserva` (boolean, padrão `false`), configurável no cadastro
  da sala. É o único `ALTER` em tabela existente desta onda.
- Os horários são sempre gravados como `HH:MM:SS` (mutators no model): a checagem de
  sobreposição compara texto em alguns bancos, e `14:00` ordena antes de `14:00:00`.

### Regras (serviços)
- `ReservaEspacoConflitoService::conflitos()` devolve as mensagens de conflito para uma sala,
  data e faixa de horário:
  1. **Outra reserva** que ocupa o espaço (solicitada ou confirmada) e cruza a faixa. Faixas que
     apenas se tocam (uma termina quando a outra começa) **não** conflitam. Reserva cancelada
     libera o horário.
  2. **Grade horária fixa** (`GradeHorario`) da mesma sala no mesmo dia da semana e horário,
     **somente se a turma tem aula naquela data**: fora do período letivo da turma ou num dia não
     letivo (`DiaNaoLetivo`, geral ou do curso) a sala está livre. Por isso uma reserva num
     feriado ou no recesso é permitida.
- `ReservaEspacoService`:
  - `solicitar()`: valida (sala ativa, horário final depois do inicial, horário que não passou,
    conflitos) dentro de uma transação que **trava a linha da sala**, para dois pedidos
    simultâneos do mesmo horário não passarem juntos.
  - Status inicial: `confirmada` se a sala não exige aprovação **ou** se quem pede tem a
    permissão `Confirmar:ReservaEspaco` (a coordenação não precisa aprovar a si mesma);
    `solicitada` nos demais casos. Um pedido **solicitado também segura o horário**.
  - `atualizar()`: reconfere conflitos ignorando a própria reserva. Mudar sala, data ou horário
    de uma reserva confirmada numa sala com aprovação devolve para `solicitada`, a menos que
    quem altera possa aprovar. Reserva cancelada ou que já ocorreu não é alterada.
  - `confirmar()`: só pedido `solicitada`; **reconfere os conflitos** (uma aula pode ter entrado
    na grade depois do pedido) antes de confirmar.
  - `cancelar()`: idempotente; reserva que já ocorreu não é cancelada; quem cancela a reserva de
    **outra pessoa** (ex.: recusar um pedido) precisa informar a justificativa.
  - `avisos()`: aviso não bloqueante quando a turma tem mais alunos ativos que a capacidade da
    sala.
- `OcupacaoEspacoService::mapa()` monta, dia a dia (máximo 31 dias), reservas e aulas da grade
  que de fato acontecem na data.

### Telas
- `ReservaEspacoResource` (`/admin/reserva-espacos`, grupo **Calendário e Horários**): lista com
  filtros (de hoje em diante por padrão, minhas reservas, espaço, situação, período), criação e
  edição passando pelo serviço (conflito vira notificação e nada é gravado, mesmo padrão da
  grade horária), ações **Confirmar** e **Cancelar** (com justificativa).
- Página **Ocupação dos Espaços** (`/admin/reserva-espacos/ocupacao`): escolha o espaço, a data
  inicial e o período (1, 7, 14 ou 30 dias) para ver reservas e grade fixa antes de pedir.

### Permissões (Shield) e políticas de acesso
- `ViewAny`, `View`, `Create`, `Update`, `Confirmar` e `Delete`/`DeleteAny` de `ReservaEspaco`
  (migration `2026_10_19_100200_create_reserva_espaco_permissions`).
- Concessão padrão: **ver, pedir e alterar as próprias** para `super_admin`, `admin`,
  `secretaria`, `coordenador` e `professor`; **confirmar** para `super_admin`, `admin`,
  `secretaria` e `coordenador`; **excluir** para `super_admin` e `admin`.
- `ReservaEspacoPolicy`: todos veem todas as reservas (é preciso saber o que está ocupado), mas
  só o solicitante altera ou cancela a própria; quem tem `Confirmar` gerencia as de qualquer
  pessoa.

## Decisões e limites

- Dois pedidos **solicitados** não disputam o mesmo horário: o primeiro segura até ser
  confirmado ou cancelado.
- Não há notificação ao solicitante quando o pedido é confirmado ou recusado; ele vê a
  situação e a justificativa na listagem.
- Não há reserva recorrente (toda terça, por exemplo): cada data é uma reserva. Reserva
  recorrente seria uma extensão natural, reaproveitando `ReservaEspacoConflitoService`.
- Equipamentos e recursos móveis (projetor, notebooks) não fazem parte desta entrega: o
  escopo cobre espaços físicos (`Sala`).

## Testes

`tests/Feature/ReservaEspacoTest.php`: status inicial, conflitos entre reservas e com a grade
(inclusive dia não letivo e fora do período), validações, atualizar/confirmar/cancelar,
policy, telas e mapa de ocupação. O relógio é fixado numa segunda-feira para as datas serem
estáveis.
