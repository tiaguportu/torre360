# Plano de Ensino Anual e Cobertura Curricular (Onda 29) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Antes do ano começar, cada professor planeja o que vai ensinar em cada disciplina (objetivos,
habilidades da BNCC, conteúdos por bimestre). Ao longo do ano, a coordenação precisa saber **o que
foi realmente trabalhado** em relação ao planejado, para corrigir o rumo antes do fim do período.

## O que já existe (ponto de partida)

- `MatrizCurricular` e `docs/estrutura_pedagogica_matriz_grade_planos.md`: disciplinas e carga
  horária por série.
- `Habilidade` (códigos BNCC), `AreaConhecimento`, `CampoExperiencia`.
- `PlanoAula` (com `Copiloto Pedagógico`): plano **por aula**, com status de execução
  (`PlanoAulaService::executar`) e habilidades trabalhadas.
- `CronogramaAula` e `GradeHorario`: calendário das aulas.
- Não existe o plano **anual/bimestral** nem o comparativo planejado × executado.

## Escopo

### Modelos novos
- `PlanoEnsino`: `turma_id` + `disciplina_id` + período letivo, professor, status (rascunho,
  enviado, aprovado pela coordenação).
- `PlanoEnsinoUnidade`: etapa/bimestre, título, objetivos, conteúdos, metodologia, avaliação,
  **habilidades planejadas** (N:N com `Habilidade`).

### Telas
- Elaboração do plano pelo professor, com sugestão de habilidades da matriz da série (e, opcional,
  rascunho do Copiloto, seguindo a minimização de dados da Onda 30).
- Aprovação pela coordenação, com comentários.
- **Cobertura curricular**: por turma e disciplina, % das habilidades planejadas já trabalhadas em
  `PlanoAula` executados, por bimestre, com alerta de atraso.
- Visão da coordenação: turmas/disciplinas com cobertura abaixo do esperado.

### Regras
- Aula planejada em `PlanoAula` pode vincular-se à unidade do plano de ensino.
- Habilidade "coberta" = presente em ao menos um plano de aula **executado** da turma.

## Dependências já satisfeitas

- Matriz, habilidades e plano de aula executável.

## Risco e observação

- Adoção: se o professor já planeja em outro formato, o plano anual vira burocracia. Priorizar
  importação (CSV) e reaproveitar o plano do ano anterior.
- A cobertura só é confiável se os planos de aula forem marcados como executados.
