# Escopo de professor segue o papel ativo ("Atuar como")

## Sintoma
Um usuário com os papéis `professor` e `super_admin` (ex.: usuário 68) trocava para `super_admin` em
**Atuar como** e continuava enxergando só as próprias turmas, disciplinas e avaliações: não conseguia
lançar notas nem frequência de outro professor. O inverso também ocorria: atuando como `professor`,
via tudo em Turmas, Disciplinas, Cronograma, Plano de Aula e Pessoas.

## Causa
Os filtros de "só o que eu leciono" testavam `hasRole('professor')` (Spatie), que olha **todos** os papéis
do usuário e ignora o papel ativo da sessão (`session('active_role')`). Alguns resources faziam o oposto:
`if ($user->hasRole('super_admin')) return $query;` antes de olhar o papel ativo.

As permissões (Shield) já respeitavam o papel ativo (`User::hasPermissionTo()` e o `Gate::before` do
`AppServiceProvider`); só o **escopo de dados** estava preso aos papéis que o usuário possui.

## Regra
O escopo de professor vale quando o usuário **está atuando como professor**: `User::atuaComoProfessor()`.

| Situação | Resultado |
|---|---|
| Professor puro, com ou sem papel ativo na sessão | restrito |
| Professor + outro papel, papel ativo `professor` | restrito |
| Professor + outro papel, papel ativo é o outro papel (`super_admin`, `admin`, `secretaria`, `coordenador`) | sem restrição de professor |
| Professor + papel de gestão, **sem** papel ativo na sessão (console, jobs) | sem restrição (papel de gestão prevalece, igual a `hasPermissionTo`) |
| Papel ativo que o usuário não possui (sessão velha) | ignorado; vale o critério da linha anterior |
| Usuário sem o papel `professor` | nunca restrito |

## Onde se aplica
Resources (`getEloquentQuery`): `Avaliacao`, `NotaHabilidade`, `Turma`, `Disciplina`, `CronogramaAula`,
`PlanoAula`, `Pessoa`. Formulários e tabelas: `AvaliacaoForm`, `AvaliacaoHabilidadeForm`, `CronogramaAulaForm`,
`CronogramaAulasTable`, `PreceptoriaForm`. Páginas/widgets: `LancamentoNotasGrade`, `PendenciasFrequencia`,
`FrequenciaPendenteWidget`. Policies: `AvaliacaoPolicy` (gabarito OMR) e `PlanoAulaAdaptacaoPolicy`.

## Ao criar um recurso novo
- Escopo "só o que eu leciono": use `auth()->user()->atuaComoProfessor()`. **Não** use `hasRole('professor')`
  nem `hasRole('super_admin')` como atalho de escopo.
- Escopos de outros papéis (`responsavel`, `aluno`) seguem `session('active_role')`, como já fazem os resources.
- Quem checa só permissão (`can(...)`) não precisa mudar: o papel ativo já entra via `hasPermissionTo()`.

## Efeito na operação
- Quem acumula `professor` com `admin`/`secretaria`/`coordenador` também passa a ver o escopo amplo ao atuar
  como o outro papel. Quem acumula `super_admin` e `professor` e atua como professor passa a ficar restrito
  onde antes via tudo.
- Fora do escopo: `AlunoResource` filtra por responsável financeiro (não por professor) e continua usando
  `hasRole('super_admin')`.

## Testes
`tests/Feature/ProfessorSuperAdminPapelAtivoTest.php`: matriz do helper, avaliações (+ gabarito), turmas,
disciplinas, pessoas, pendências de frequência e lançamento de notas em grade, nos dois sentidos.
