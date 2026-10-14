# Repositório de Conteúdo Pedagógico Digital (Onda 18) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Repositório de apostilas e vídeo-aulas por disciplina/turma, para o aluno estudar fora do
horário de aula. Não coberto pela lista de marketing do Sponte nem pelas ondas já
levantadas. Distinto de dois módulos já existentes com os quais poderia ser confundido:

- **Planos de Aula** (Onda 4, `PlanoAula`) é uma ferramenta do **professor** para
  planejar a aula — não é conteúdo para o aluno consumir.
- **Provas Online** (Onda 8, ainda não implementada) é **avaliação**, não material de
  estudo.

Este módulo é especificamente conteúdo digital para consumo assíncrono pelo aluno.

## O que já existe (ponto de partida)

- `CronogramaAula` (diário de aula) já registra o conteúdo ministrado em texto
  (`conteudo_ministrado`), mas não anexa nem hospeda material para o aluno baixar/assistir.
- `PlanoAula` (Onda 4) já tem campo de anexos, mas é documento de planejamento do
  professor, não publicado para o aluno ver.
- Portal da Família/Aluno (Onda 2) já é o lugar natural para o aluno acessar isso.

## Escopo

### Modelo novo
- `MaterialAula`: `turma_id`, `disciplina_id`, professor responsável, título, descrição,
  tipo (apostila/PDF, vídeo-aula, link externo), arquivo ou URL, data de publicação,
  visível (boolean — permite preparar e publicar depois).

### Telas
- Cadastro/upload pelo professor (tela própria ou aba dentro da gestão da turma).
- No Portal do Aluno: lista de materiais por disciplina, com filtro por tipo, ordenado do
  mais recente.

## Dependências já satisfeitas

- Portal do Aluno (Onda 2) já existe como lugar de consumo.
- Padrão de upload/anexo já usado em outros módulos (ex.: documentos de matrícula,
  anexos de ocorrência) pode ser reaproveitado para o armazenamento do arquivo.

## Risco e observação

- Armazenamento de vídeo-aula pode pesar em disco/CDN dependendo do volume — para
  vídeos, considerar armazenar só o link (YouTube não-listado, Vimeo etc.) em vez de
  upload direto, pelo menos na primeira versão.
