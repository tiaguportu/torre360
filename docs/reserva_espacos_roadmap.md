# Reserva de Espaços e Recursos (Onda 21) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Escolas com auditório, quadra, laboratório ou biblioteca precisam agendar o uso pontual
desses espaços para eventos e atividades extraclasse (ensaio de formatura, aula extra de
Educação Física, palestra, gravação) sem conflitar com a grade horária fixa nem com outros
agendamentos pontuais.

## O que já existe (ponto de partida)

- `Sala` (Onda 4) já cadastra o espaço físico (unidade, nome, capacidade, tipo, ativa).
- `GradeHorario` (Onda 4) já liga `Sala` à grade semanal **recorrente** das turmas, com
  validação de conflito (mesmo espírito do `VerificaConflitos` usado em `CronogramaAula`).
- O que falta é o agendamento **pontual** (uma data/hora específica, fora da grade fixa) —
  hoje `Sala` só se relaciona com `GradeHorario`, não existe nenhum model de reserva
  avulsa.

## Escopo

### Modelo novo
- `ReservaEspaco`: `sala_id`, `data`, `hora_inicio`, `hora_fim`, `solicitante_user_id`,
  `motivo`/`descricao`, `turma_id` (opcional, quando a reserva é para uma turma específica),
  status (solicitada/confirmada/cancelada — útil se a escola quiser aprovação prévia de
  espaços concorridos, como o auditório).

### Validação
- Conflito de horário no mesmo espaço (duas reservas não podem se sobrepor na mesma
  `sala_id`) e, se a sala já estiver ocupada pela grade fixa (`GradeHorario`) no mesmo
  dia/horário, bloquear também — reaproveitando a mesma lógica de checagem de intervalo já
  usada em `VerificaConflitos`, adaptada de "dia da semana recorrente" para "data
  específica".

### Telas
- Resource de reserva (grupo Secretaria/Acadêmico), com calendário/lista por sala e por
  período, para qualquer papel que precise reservar um espaço (coordenação, professor,
  secretaria) ver rapidamente o que já está ocupado antes de pedir.

## Dependências já satisfeitas

- `Sala` (Onda 4) já existe como o espaço físico a ser reservado.
- Padrão de validação de conflito de horário já estabelecido em `GradeHorario`/
  `VerificaConflitos` (Onda 4) — é adaptar a mesma ideia para data específica em vez de dia
  da semana recorrente, não criar do zero.
