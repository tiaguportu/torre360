# Tarefas e Lições de Casa (Onda 51) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda.
>
> **Correção de escopo (2026-10-21):** a primeira análise desta onda dizia que não existia nada parecido.
> Existe o campo `dever_casa` em cada aula; a onda passa a ser a **evolução** dele, não uma novidade.

## Contexto

O professor registra o dever de casa num texto livre ao lançar a aula, e a família vê esse texto na página
**Horários** do Portal. Falta o que a família e o professor mais pedem: **prazo**, **entrega** e **aviso**.
Hoje ninguém sabe se a tarefa foi feita, quando vence nem quanta tarefa a turma recebeu no dia.

## O que já existe

- `CronogramaAula.dever_casa` (texto, por aula) e o campo no formulário da aula e da chamada.
- Portal, página **Horários**: mostra "Dever de casa: ..." junto da aula (`horarios.blade.php`).
- `MaterialAula` (apostilas, vídeos e links por turma), `Avaliacao` (provas) e o calendário do Portal.
- Notificações push/e-mail já usadas em outras ondas.
- **Falta:** tarefa com prazo, estado por aluno, aviso e visão de carga por turma.

## Escopo

### Dados
- `TarefaEscolar`: turma, disciplina, professor, título, descrição, publicada em, **prazo**, anexo opcional
  e, se nascer de uma aula, o `cronograma_aula_id` (ação "Transformar o dever de casa em tarefa").
- `EntregaTarefa`: matrícula, estado (pendente, feita, atrasada, dispensada), data, comentário do professor.
  Primeira versão **sem envio de arquivo pelo aluno**: o aluno ou o responsável marca como feita e o
  professor confirma. Upload entra depois, com a lista branca de tipos de arquivo do sistema.

### Telas
- Professor: criar e acompanhar a tarefa da turma, ver quem entregou, dispensar aluno (por exemplo, com
  adaptação do plano de aula/AEE).
- Portal: lista de tarefas por aluno, com prazo e filtros; resumo da semana.
- Aviso na véspera do prazo e das tarefas atrasadas, só para o responsável do aluno.

### Carga por turma
- Soma das tarefas e provas (Onda 52) por dia/semana, com aviso à coordenação quando passar do limite
  configurado.

## Regras e limites

- Mantém `dever_casa` funcionando como está; a migração é opcional e por turma.
- Dado de menor: o Portal mostra ao aluno e à família só as tarefas da própria turma.

## Dependências

- Cronograma, turmas e Portal já existem. Combina com a Onda 52 (calendário de avaliações).

## Riscos e observações

- Volume de avisos: um resumo diário por responsável, não um aviso por tarefa.
- Evitar virar sistema de provas online (Onda 8): tarefa é acompanhamento, não correção automática.

## Esforço

Médio.
