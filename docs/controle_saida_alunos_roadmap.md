# Controle de Saída de Alunos (Onda 9) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Item comum em sistemas de gestão escolar (controle de quem está autorizado a retirar o
aluno e registro de cada retirada), não coberto pela lista de marketing do Sponte nem pelas
Ondas 1-8, mas considerado relevante por ser uma preocupação de segurança frequente,
principalmente na Educação Infantil e no Ensino Fundamental I.

## O que já existe (ponto de partida)

- `aluno_responsavel` (pivot entre `Pessoa` e `Pessoa`, via `App\Models\AlunoResponsavel`)
  já tem a coluna **`permissao_retirada`** (boolean, padrão `false`) e `tipo_vinculo_id`
  (ex.: Pai, Mãe). Hoje é só um dado estático guardado no banco — não há tela dedicada que
  o exponha, nem qualquer registro de uso (quem efetivamente retirou o aluno e quando).
- `ContatoEmergencia` (ligado a `FichaMedica`) é um cadastro adjacente mas distinto: serve
  para "quem acionar numa emergência de saúde", não "quem pode retirar o aluno".

## Escopo

### Modelo novo
- `RegistroRetirada`: `matricula_id`, `pessoa_id` (quem retirou — deve ter
  `permissao_retirada = true` na relação com o aluno, validado na criação),
  `horario_retirada`, `registrado_por_user_id` (funcionário que lançou o registro),
  `observacao` (nullable, para casos de exceção/autorização pontual).

### Telas
- Resource/Relation manager para gerenciar `permissao_retirada` por responsável (hoje só
  existe no banco, sem UI).
- Ação "Registrar Retirada" na secretaria (busca o aluno, lista só os responsáveis com
  `permissao_retirada = true`, confirma o horário).
- Painel simples "quem já saiu hoje" filtrável por turma — útil no fim do turno.

## Dependências já satisfeitas

- `AlunoResponsavel` / `permissao_retirada` já existem na tabela `aluno_responsavel`.
- `TipoVinculo` já existe para qualificar a relação (Pai, Mãe, Responsável Legal etc.).
