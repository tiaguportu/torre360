# Calendário de Avaliações e Limite de Provas por Dia (Onda 52)

> **Status: Implementada em 2026-10-23.** Service `CalendarioAvaliacaoService` para detecção de conflitos, limite diário de até 2 provas por turma, limite semanal de 4 provas e bloqueio/alerta de dias não letivos e feriados nacionais (`DiaNaoLetivo`). Integração reativa no `AvaliacaoForm` com helperText de aviso pedagógico. Testado em `CalendarioAvaliacaoTest`.

## Contexto

Cada professor marca a data da própria prova. Sem uma visão da turma, a mesma turma pode ter três provas no
mesmo dia, prova em dia não letivo ou na véspera de feriado. Ninguém é avisado na hora de marcar.

## O que já existe

- `Avaliacao` com `data_prevista`, `data_ocorrencia`, turma, disciplina e categoria.
- O calendário do Portal, que já mostra as provas e os dias não letivos.
- `DiaNaoLetivo` (feriados, recessos) por período e curso.
- **Falta:** conferir, ao marcar a data, o que mais a turma tem naquele dia e naquela semana.

## Escopo

### Regras configuráveis (`config/avaliacoes.php`, com ajuste na tela)
- Máximo de avaliações por turma **por dia** e **por semana** (valores padrão a definir com a escola).
- Aviso quando a data cai em dia não letivo ou na véspera dele.
- Semana de provas: período em que o limite diário muda.

### Onde aparece
- Ao criar ou editar a avaliação: "A turma já tem prova de Matemática em 12/11" (aviso; bloqueio opcional
  por perfil de coordenação).
- Calendário da turma (mês e semana) para coordenação e professores, com as provas e os dias não letivos.
- Quando a Onda 51 existir, a carga soma tarefas e provas.

## Regras e limites

- Padrão da casa: configuração em arquivo com ajuste persistido em banco, como no Lead Score e no Risco de
  Evasão.
- Avaliações já marcadas não são alteradas; o aviso vale para a próxima edição.
- O professor de apoio ou de AEE não entra na contagem da turma.

## Dependências

- Nenhuma além do que já existe.

## Riscos e observações

- Bloqueio rígido atrapalha reposições e provas de segunda chamada: começar só com aviso.

## Esforço

Pequeno.
