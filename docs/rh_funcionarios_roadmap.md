# RH e Gestão de Funcionários (Onda 10)

> **Status: implementado** (10a, 10b e 10c completas). Este documento descreve o escopo e
> as decisões de modelagem; detalhes de uso ficam nos textos de Ajuda das telas
> (**Funcionários** e **Substituições de Professor**, grupo **RH** do menu).

## Contexto

Módulo clássico de sistemas de gestão escolar completos (contrato de trabalho, férias,
substituição de professor), não coberto pela lista de marketing do Sponte nem pelas Ondas
1-8. É a mais ampla das propostas deste addendum — por isso já nasce dividida em
sub-ondas, no mesmo espírito da Onda 8.

## O que já existe (ponto de partida)

- `Pessoa` é a tabela genérica compartilhada por aluno, responsável, professor,
  coordenador etc. — não tem nenhum campo de vínculo empregatício (cargo, admissão,
  regime).
- `Coordenador` (tabela `coordenador`) já segue o padrão "tabela própria ligada a
  `pessoa_id`" que uma ficha de funcionário deveria seguir, mas é específico de curso
  (`curso_id`, `cargo` como texto livre, `data_inicio`, sem `data_fim`) — não serve como
  cadastro geral de funcionário.
- "Professor" não é um model — é só `Pessoa` referenciada via FK/pivot
  (`Turma.professor_conselheiro_id`, `turma_disciplina.professor_id`,
  `turma_habilidade.professor_id`), sem histórico de substituição.
- Papéis de acesso (`professor`, `coordenador`, `secretaria` etc.) já existem via Spatie
  Permission (`database/seeders/RolesSeeder.php`) — são permissões de sistema, não dados
  de RH.
- **Atenção ao nome:** `Contrato` já é usado para o contrato de matrícula/mensalidade do
  aluno (`app/Models/Contrato.php`). Um contrato de trabalho precisa de nome distinto —
  proposto `ContratoTrabalho` — para não colidir.

## Escopo e sub-ondas

### 10a — Cadastro de funcionário
- Novo model `Funcionario` (tabela própria ligada a `pessoa_id`, mesmo padrão de
  `Coordenador`/`ContatoEmergencia`): cargo, data de admissão, data de desligamento,
  regime (CLT/estatutário/PJ), carga horária, unidade(s) de lotação.

### 10b — Contrato de trabalho e férias
- `ContratoTrabalho` (vinculado a `Funcionario`): vigência, salário, aditivos.
- Controle de férias: período aquisitivo, período de gozo, saldo.

### 10c — Substituição de professor
- Histórico de cobertura temporária (professor titular × substituto, período, turma/
  disciplina), hoje inexistente — o sistema só tem o FK estático do professor atual.

## Dependências já satisfeitas

- Padrão de "tabela própria ligada a `pessoa_id`" já estabelecido por `Coordenador` e
  `ContatoEmergencia` — é só seguir a mesma receita (migration + model + Resource + Policy
  + teste Feature, como em `app/Filament/Resources/Coordenadors/`).
- Papéis de acesso (`professor`, `coordenador`) já existem; RH só precisa de permissões
  novas no mesmo padrão `Ação:Modelo` do Shield.

## Como foi implementado

- `Funcionario` (`app/Models/Funcionario.php`): `pessoa_id`, `cargo`, `regime` (enum
  `RegimeContratacao`: CLT/Estatutário/PJ/Estágio), `data_admissao`, `data_desligamento`,
  `carga_horaria_semanal`, `unidade_id`. Resource em `/admin/funcionarios` (grupo **RH**).
- **Aditivos simplificados:** em vez de um model `AditivoContratoTrabalho` separado,
  `ContratoTrabalho` é uma tabela *append-only* — cada linha é uma vigência (admissão ou
  reajuste). A ação "Registrar Aditivo" encerra a vigência atual (`vigencia_fim`) e cria
  uma nova linha com o novo salário a partir do dia seguinte
  (`ContratoTrabalho::registrarAditivo()`). O salário vigente é sempre a linha sem
  `vigencia_fim`. Gerenciado via aba "Contratos de Trabalho" dentro do funcionário.
- **Férias:** `PeriodoFerias` (período aquisitivo, dias de direito/gozados, status
  Pendente/Parcial/Gozado/Vencido). Ação "Registrar Gozo" soma os dias informados e marca
  Gozado quando atinge o total. Aba "Férias" dentro do funcionário.
- **Substituição de professor:** `SubstituicaoProfessor` (turma, disciplina opcional,
  professor titular/substituto, período, motivo) como Resource próprio em
  `/admin/substituicao-professors`, já que não se limita a um único funcionário.
- **Permissões:** `Funcionario`/`ContratoTrabalho`/`PeriodoFerias` restritos a
  `admin`/`super_admin` (dado de salário é sensível). `SubstituicaoProfessor` também
  liberado para `secretaria`/`coordenador` (é só agenda, não dado financeiro).
- Testes em `tests/Feature/FuncionarioTest.php` e
  `tests/Feature/SubstituicaoProfessorTest.php`.
