# LGPD — Policies do Shield para dados de saúde de alunos

## Contexto

`FichaMedica`, `AtendimentoEnfermagem` e `OcorrenciaEscolar` guardam dados sensíveis de
menores (alergias, medicamentos de uso contínuo, nº da carteira do SUS, atendimentos de
enfermagem, relatos disciplinares). Os três `Resources` do Filament já declaravam
`implements HasShieldPermissions`, mas nenhum tinha uma `Policy` correspondente em
`app/Policies/`.

## Problema encontrado

Sem `Policy`, o Filament cai no comportamento padrão de "sem política registrada" e libera
o acesso (`Response::allow()`), a menos que a autorização estrita do Filament esteja ativada
(não está neste projeto). Combinado com `User::canAccessPanel()` — que permite qualquer
usuário ativo acessar o painel `admin`, independente do papel — isso significava que
**qualquer usuário autenticado no `/admin` (inclusive `aluno`/`responsavel`) conseguia
listar, ver, criar, editar e apagar fichas médicas, atendimentos de enfermagem e ocorrências
escolares de todos os alunos**, sem nenhuma permissão explícita de saúde/enfermagem.

`MedicamentoAluno` e `ContatoEmergencia` (relacionamentos embutidos via `Repeater` no
formulário de `FichaMedica`) não têm Resource próprio — ficam protegidos automaticamente
pela Policy de `FichaMedica`, já que só são acessíveis através do form dela.

## Correção aplicada

1. Geradas as três Policies via `php artisan shield:generate --resource=FichaMedicaResource,AtendimentoEnfermagemResource,OcorrenciaEscolarResource --option=policies_and_permissions --panel=admin`,
   no mesmo padrão usado pelos ~55 models já protegidos no projeto (`app/Policies/*.php`).
   Isso criou:
   - `app/Policies/FichaMedicaPolicy.php`
   - `app/Policies/AtendimentoEnfermagemPolicy.php`
   - `app/Policies/OcorrenciaEscolarPolicy.php`
   - 36 permissions no banco (`ViewAny:FichaMedica`, `View:FichaMedica`, ... para os 3 models),
     atribuídas apenas ao papel `super_admin` (comportamento padrão do Shield).
2. A action customizada `reenviarNotificacao` (em `OcorrenciaEscolarResource`) não tem
   verificação automática do Filament — seguindo o mesmo padrão já usado em
   `AvaliacaoResource::lancarNotas`, foi adicionado:
   - Permission `ReenviarNotificacao:OcorrenciaEscolar` (criada manualmente, mesma convenção).
   - Método `reenviarNotificacao()` em `OcorrenciaEscolarPolicy`.
   - `->authorize(fn ($record) => auth()->user()?->can('reenviarNotificacao', $record))` na
     action, em `app/Filament/Resources/OcorrenciaEscolarResource.php`.

## Estado após a correção

Por padrão, **somente `super_admin`** enxerga esses três Resources — nenhum outro papel
(`admin`, `secretaria`, `professor`, `coordenador`, `responsavel`, `aluno`) tem a permissão.
Isso é intencional: não existe hoje um papel "saúde/enfermagem" dedicado no sistema
(`database/seeders/RolesSeeder.php`), então a autorização explícita passa a ser controlada
pelas permissions do Shield.

**Ação manual necessária:** um `super_admin` deve entrar em *Roles* no painel Shield e
conceder as permissions `ViewAny:FichaMedica` / `View:...` / etc. (e o equivalente para
`AtendimentoEnfermagem` e `OcorrenciaEscolar`) apenas aos papéis que de fato atendem saúde
ou precisam desses dados no dia a dia (ex.: `secretaria`, `coordenador`), decisão que é de
negócio e não deve ser assumida automaticamente pelo código.

## Recomendação para trabalho futuro (fora do escopo desta correção)

`NecessidadeEducacaoEspecial`, `TranstornoAprendizagem` e `RecursoAcessibilidade` — expostos
como Relation Managers dentro de `PessoaResource` — têm a mesma lacuna (sem Policy própria).
Vale aplicar o mesmo tratamento.
