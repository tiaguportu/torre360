# Atendimento Educacional Especializado (AEE) / Plano Individualizado (Onda 22) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Alunos com deficiência, transtorno de aprendizagem ou altas habilidades precisam de um
plano pedagógico próprio (metas, adaptações por disciplina, acompanhamento do professor de
apoio) — distinto do simples cadastro censitário que já existe hoje.

## O que já existe (ponto de partida)

- `NecessidadeEducacaoEspecial` + `CategoriaNecessidadeEducacaoEspecial`: já classificam o
  aluno (`pessoa_id`, categoria, observação) para fins do Censo Escolar/INEP
  (`EducacensoPessoaExporter` já usa isso nos registros 29-35 e no campo 17). É só a
  classificação — não há plano de metas, adaptação por disciplina nem registro de
  atendimento.
- `AtendimentoEnfermagem` (ficha de saúde) já estabelece o padrão de "registro de
  atendimento individual" (data, descrição, quem atendeu) que esta onda reaproveita para o
  atendimento pedagógico de apoio.

## Escopo

### Modelos novos
- `PlanoEducacionalIndividualizado`: `matricula_id`, `necessidade_educacao_especial_id`
  (liga ao diagnóstico/classificação já existente), período de vigência, metas gerais,
  professor de apoio responsável (`pessoa_id`).
- `AdaptacaoDisciplina`: `plano_educacional_individualizado_id`, `disciplina_id`,
  adaptações específicas (ex.: tempo adicional em avaliação, material em fonte ampliada),
  meta da disciplina.
- `AtendimentoAEE`: `plano_educacional_individualizado_id`, data, descrição do atendimento,
  registrado por (professor de apoio) — mesmo padrão de `AtendimentoEnfermagem`.

### Telas
- Resource no admin (grupo Acadêmico/RH, acesso restrito: professor de apoio da matrícula
  + coordenação + admin/super_admin — dado sensível, mesma lógica de restrição já usada em
  `FichaMedica`).
- Relation manager de adaptações e atendimentos dentro do plano.
- Indicador no Diário/Lançamento de Notas para o professor da disciplina ver rapidamente
  que o aluno tem adaptação cadastrada (sem expor o diagnóstico completo).

## Dependências já satisfeitas

- `NecessidadeEducacaoEspecial` (classificação) já existe como ponto de partida/diagnóstico.
- Padrão de registro de atendimento individual já estabelecido em `AtendimentoEnfermagem`.
- Padrão de restrição de acesso a dado sensível já estabelecido em `FichaMedica`/RH
  (Onda 10).
