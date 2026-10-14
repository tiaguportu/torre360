# Repositório de Conteúdo Pedagógico Digital (Onda 18)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam nos textos de Ajuda das telas (**Materiais de Aula** no admin,
> grupo **Acadêmico**, e **Materiais** no Portal do Aluno).

## Contexto

Repositório de apostilas e vídeo-aulas por disciplina/turma, para o aluno estudar fora do
horário de aula. Distinto de dois módulos já existentes com os quais poderia ser
confundido:

- **Planos de Aula** (Onda 4, `PlanoAula`) é uma ferramenta do **professor** para
  planejar a aula — não é conteúdo para o aluno consumir.
- **Provas Online** (Onda 8, ainda não implementada) é **avaliação**, não material de
  estudo.

## Como foi implementado

- `MaterialAula` (`app/Models/MaterialAula.php`): `turma_id`, `disciplina_id`,
  `professor_id` (opcional), título, descrição, tipo (`TipoMaterialAula`: Apostila/PDF,
  Vídeo-aula, Link Externo), `arquivo_path` (upload, só para apostila) ou `url` (só para
  vídeo/link), data de publicação, `visivel` (permite preparar e publicar depois).
  Resource em `/admin/material-aulas` (grupo **Acadêmico**).
- **Campos condicionais no formulário:** o campo de upload (apostila) ou de URL
  (vídeo/link) aparece e se torna obrigatório de acordo com o `tipo` selecionado, via
  closures `Get`-based (`->visible()`/`->required()`).
- **Portal do Aluno** (`/portal/materiais`, página `Materiais`): lista os materiais
  visíveis da turma do aluno selecionado, com filtro por tipo e disciplina — mesmo padrão
  de seleção de aluno usado em Notas/Frequência (`InteractsWithMatriculaSelecionada`).
  Ação "Abrir" baixa a apostila ou abre o vídeo/link em nova aba.
- `MaterialAula::url_acesso` (accessor): resolve para `Storage::url()` quando apostila,
  ou para a `url` direta quando vídeo/link — ponto único de acesso ao conteúdo,
  independente do tipo.
- Vídeos por link externo (YouTube não-listado, Vimeo etc.), não upload direto, para não
  pesar em armazenamento — conforme já previsto na proposta original.
- Não é dado sensível: permissões liberadas para `professor`, `coordenador`,
  `secretaria`, além de `admin`/`super_admin` — o professor publica o próprio material.
- Testes em `tests/Feature/MaterialAulaTest.php` (admin, model e Portal).

### Nota técnica (para quem for mexer neste código)

O `Select` de `tipo` usa `options(TipoMaterialAula::class)` — o Filament entrega o valor
de `$get('tipo')` já como **instância do enum**, não como a string crua, tanto em
`$data['tipo']` (nas actions) quanto dentro de closures `Get` de campos irmãos no mesmo
formulário. Comparar com `->value` (`$get('tipo') === Enum::Caso->value`) **falha
silenciosamente** (visibilidade/obrigatoriedade nunca ativam). O formulário usa um helper
`MaterialAulaForm::tipoSelecionado()` que normaliza para a instância do enum antes de
comparar. O mesmo cuidado já apareceu na Onda 16 (`BemPatrimonialsTable::mudarStatusAction`).

## Dependências já satisfeitas

- Portal do Aluno (Onda 2) e `InteractsWithMatriculaSelecionada` já existiam como base de
  seleção de aluno.
- Padrão de `FileUpload` com `directory()`/`preserveFilenames()` já estabelecido em
  `PlanoAula` foi reaproveitado.
