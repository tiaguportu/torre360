# Estrutura Pedagógica: Matriz Curricular, Salas, Grade Horária e Planos de Aula

Onda 4 do roadmap de funcionalidades (planejamento pedagógico antes do início das aulas).

## 0. Nota sobre "ensalamento"

Esta onda usa o termo **"sala"** para o ambiente físico (laboratório, quadra etc.) usado
na grade horária. Isso é diferente do **"Remanejamento de Turmas"** (antigo "Ensalamento
em Lote Assistido"; `/admin/ensalamento`, `EnsalamentoService`) já existente no sistema,
que move **alunos entre turmas** (homeroom), não salas físicas. O slug e a classe mantêm
o nome "ensalamento" por compatibilidade (links e permissões `View:/Manage:Ensalamento`);
o cadastro de `Sala` desta onda não interfere nele.

## 1. Matriz Curricular

Model: `App\Models\MatrizCurricular` (tabela `matriz_curricular`).
Serviço: `App\Services\MatrizCurricularService`.

Define, por série, quais disciplinas ela deve ter e a carga horária semanal esperada —
é a origem do vínculo `turma_disciplina`, que continua editável manualmente a qualquer
momento.

- **Onde cadastrar:** aba **Matriz Curricular** na tela de Série (`SerieResource`).
  Campos: disciplina, carga horária semanal (aulas/semana), obrigatória (sim/não) e ordem.
  Único por `(serie_id, disciplina_id)`.
- **Sincronização:** `MatrizCurricularService::sincronizarTurmaDisciplinas(Turma $turma)`
  adiciona à turma as disciplinas da matriz da sua série que **ainda não** estiverem
  vinculadas. Nunca remove ou sobrescreve uma disciplina já vinculada manualmente (mesmo
  que ela não conste na matriz) — é estritamente aditivo.
- **Quando roda automaticamente:** ao criar uma turma pelo admin
  (`CreateTurma::afterCreate()`).
- **Quando rodar manualmente:** botão **"Sincronizar Disciplinas da Matriz"** na edição
  da Turma (uma turma) ou **"Sincronizar Turmas Existentes"** na aba Matriz Curricular da
  Série (todas as turmas da série de uma vez) — útil depois de alterar a matriz ou para
  turmas criadas antes de a matriz existir.

## 2. Salas

Model: `App\Models\Sala` (tabela `sala`).
Resource: `App\Filament\Resources\Salas\SalaResource` (menu **Acadêmico → Salas**).

Cadastro simples: unidade, nome, capacidade (opcional, só informativa), tipo (texto
livre: "Sala de aula", "Laboratório de Informática" etc.) e um toggle `ativa` (salas
inativas somem das opções de sala na grade horária, sem serem excluídas).

## 3. Grade Horária Semanal

Model: `App\Models\GradeHorario` (tabela `grade_horario`).
Serviço de conflito: `App\Services\GradeHorarioConflitoService`.
Serviço de geração: `App\Services\GradeHorarioService`.
UI: aba **Grade Horária** na edição da Turma
(`Turmas\RelationManagers\GradeHorariosRelationManager`).

Cada linha da grade é um horário **recorrente** (vale para todas as semanas do período
letivo): turma (implícita, é a turma dona), disciplina, professor (opcional), sala
(opcional), dia da semana e hora de início/fim.

- **`dia_semana`** segue a mesma convenção já usada em `turma_horario`: `0=Domingo` até
  `6=Sábado` (não é ISO — different de `Carbon::isoWeekday()`; usa `Carbon::dayOfWeek`).
- **Detecção de conflito** (`GradeHorarioConflitoService::conflitos()`): compara a linha
  candidata com as demais linhas de `grade_horario` no **mesmo dia da semana** cujo
  intervalo `hora_inicio`–`hora_fim` se sobrepõe, checando três dimensões
  independentemente — mesma **turma**, mesmo **professor** (entre turmas diferentes) e
  mesma **sala** (entre turmas diferentes). Ao editar, o próprio registro é excluído da
  checagem. Mesma lógica de sobreposição de intervalo já usada em
  `CronogramaAulas\Pages\VerificaConflitos`.
- **Bloqueio no formulário:** `GradeHorariosRelationManager` intercepta os hooks
  `CreateAction::before()`/`EditAction::before()`, roda o serviço de conflito com os
  dados submetidos e, havendo conflito, mostra uma notificação com a mensagem específica
  e chama `$action->halt()` (mesmo padrão de `EditDisciplina::beforeSave()` com `Halt`).

### Gerar Cronograma do Período

Ação **"Gerar Cronograma do Período"** na listagem de **Turmas**
(`GradeHorarioService::gerarCronograma()`):

1. Usa `turma.periodoLetivo.data_inicio`/`data_fim` (ou datas explícitas) como janela.
2. Para cada dia do intervalo, casa o `dayOfWeek` da data com o `dia_semana` de cada
   linha da grade da turma.
3. Pula datas marcadas em `DiaNaoLetivo` (do período letivo, gerais ou do curso da
   turma), mesmo critério de `docs/portal_familia_notas_frequencia_horarios.md`.
4. Não duplica: se já existe `CronogramaAula` para a mesma turma/disciplina/data/horário,
   a linha é ignorada. Rodar a ação de novo depois de ajustar a grade é seguro.
5. Insere em lote (`insert()` em chunks de 200) para não sobrecarregar uma turma com
   período letivo inteiro (algumas centenas de aulas).

A ação só aparece para turmas que já têm ao menos uma linha na grade horária, e exige a
permissão `GerarCronograma:Turma` (`TurmaPolicy::gerarCronograma()`).

## 4. Planos de Aula

Model: `App\Models\PlanoAula` (tabela `plano_aula`, pivô `plano_aula_habilidade`).
Serviço: `App\Services\PlanoAulaService`.
Resource: `App\Filament\Resources\PlanoAulas\PlanoAulaResource` (menu **Acadêmico →
Planos de Aula**).

O que o professor planeja lecionar antes da aula acontecer: turma, disciplina (só
mostra as disciplinas já vinculadas à turma), data prevista, objetivos, metodologia,
recursos, avaliação prevista, habilidades da BNCC previstas e anexos.

- **Escopo por professor:** mesmo critério de `CronogramaAulaResource` — vê os próprios
  planos, os de turmas onde é professor conselheiro, ou onde tem vínculo em
  `turma_disciplina`.
- **Executar** (`PlanoAulaService::executar()`): cria o `CronogramaAula` real (turma,
  disciplina, professor, data — por padrão a prevista, mas pode ser ajustada no momento
  da execução —, `conteudo_ministrado` = objetivos do plano, anexos), copia as
  habilidades previstas para `cronograma_aula_habilidade`, e marca o plano com
  `executado_em` e `cronograma_aula_id`. Não pode ser executado duas vezes
  (`InvalidArgumentException`).
- **Bloqueio pós-execução:** um plano executado não aparece mais com a ação "Executar" e
  não pode ser editado (`EditPlanoAula::beforeSave()` bloqueia com `Halt`, mesmo padrão
  de `EditDisciplina`) — o `CronogramaAula` gerado passa a ser a fonte da verdade.

## 5. Correção de bug pré-existente: tabela `habilidades` sumia numa instalação do zero

Durante os testes desta onda, uma migração do banco de dados já existente na `main`
(não relacionada a esta onda) quebrou toda funcionalidade de habilidades BNCC numa
instalação **do zero** (banco de testes, ou uma nova instalação): a sequência de
migrações antigas rebatizava `habilidades` para o singular `habilidade`, e uma migração
posterior (`2026_04_18_000000_create_habilidades_tables.php`) considerava "a tabela já
existe com o schema certo" só por checar o **nome**, sem checar se ela realmente tinha as
colunas novas (`codigo`, `nome`, `tipo`). Como a versão renomeada ainda tinha o schema
**antigo** (sem essas colunas), e a migração seguinte (`2026_04_19_...`) excluía essa
tabela por completo (achando que era só uma duplicata legada), o resultado numa
instalação do zero era **nenhuma tabela de habilidades**, quebrando `Habilidade` e tudo
que depende dela (inclusive `PlanoAula`, desta onda).

Corrigido trocando o critério de "a tabela já existe" para "a tabela já existe **com a
coluna `codigo`**" em `2026_04_18_000000_create_habilidades_tables.php`. No banco de
dados já em uso (onde `habilidades` já tem o schema novo) isso não muda nada — a correção
só afeta uma migração do zero. `2026_04_19_171249_drop_duplicated_habilidade_table.php`
não precisou mudar.

## 6. Migrations desta onda

| Migration | O que faz |
|---|---|
| `create_matriz_curricular_table` | Tabela `matriz_curricular`. |
| `create_sala_table` | Tabela `sala`. |
| `create_grade_horario_table` | Tabela `grade_horario`. |
| `create_plano_aula_table` | Tabelas `plano_aula` e `plano_aula_habilidade`. |
| `create_estrutura_pedagogica_permissions` | Permissões das quatro entidades acima, mais `GerarCronograma:Turma`, concedidas a super_admin/admin/coordenador (e secretaria para Sala; professor para PlanoAula). |
| `2026_04_18_000000_create_habilidades_tables` (editado) | Corrige a checagem de schema descrita na seção 5. |

## 7. Testes

`MatrizCurricularTest`, `SalaResourceTest`, `GradeHorarioTest` (conflitos, relation
manager, geração de cronograma), `PlanoAulaTest` (execução, escopo por professor,
bloqueio pós-execução). A correção da seção 5 também é coberta indiretamente por
`BNCCEvaluationTest`, que passou a rodar em um banco de testes do zero.
