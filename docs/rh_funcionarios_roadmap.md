# RH e Gestão de Funcionários (Onda 10) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

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
