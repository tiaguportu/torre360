# Agenda Diária / Rotina (Educação Infantil) (Onda 26) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Em creches e educação infantil, a família acompanha o dia da criança por um registro
diário (alimentação, sono, higiene, humor, atividades) — recurso de alto engajamento,
distinto de boletim/nota (que não se aplica a bebês) e de comunicado geral.

## O que já existe (ponto de partida)

- `MaterialAula` (Onda 18) é conteúdo do **professor para o aluno estudar** (apostila/
  vídeo/link) — não é o registro do dia a dia da criança.
- `EventoEscolar` (Onda 2) é um evento pontual com confirmação de presença — não é um
  registro diário recorrente.
- `Habilidade`/avaliação híbrida (BNCC infantil, já existente) é o relatório de
  desenvolvimento por período — a Agenda Diária é o registro do **dia a dia**, mais
  simples e frequente, não substitui esse relatório periódico.
- `Pessoa`/`Matricula` e o Portal do Aluno (Onda 2) já são a base de acesso da família.

## Escopo

### Modelo novo
- `RegistroRotinaDiaria`: `matricula_id`, `data`, `turma_id`, campos de rotina
  configuráveis por faixa etária (alimentação: o que/quanto comeu; sono: horários;
  higiene: troca de fralda/idas ao banheiro; humor: ícone/observação; atividades do dia:
  texto curto), registrado por qual professor/auxiliar, foto opcional (reaproveitando o
  mesmo padrão de upload já usado em `MaterialAula`).

### Telas
- Professor/auxiliar de turma: preenchimento rápido em lote (a turma toda) com valores
  padrão e ajuste individual, pensado para ser rápido de preencher ao longo do dia.
- Portal da Família: card do dia da criança, histórico por data.
- Notificação ao publicar o registro do dia (mesmo canal de `OcorrenciaEscolar`/
  `Preceptoria`).

## Dependências já satisfeitas

- Portal do Aluno (Onda 2) e padrão de notificação por e-mail/push/sininho já
  estabelecidos — é reaproveitar, não criar do zero.
- Padrão de upload de arquivo (`FileUpload` com `directory()`) já estabelecido em
  `MaterialAula`/`PlanoAula`.
