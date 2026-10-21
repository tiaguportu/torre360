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

## Melhoria: alerta de férias vencidas (2026-10-21)

O cadastro de férias registrava o que foi gozado, mas ninguém era avisado quando o prazo legal de
concessão estava acabando. Agora o sistema acompanha esse prazo para os empregados **CLT ativos**.

- **Regra legal:** o empregador concede as férias nos 12 meses seguintes ao fim do período
  aquisitivo (CLT, art. 134); concedidas depois, a remuneração é paga em dobro (art. 137; TST,
  Súmula 81, para os dias gozados fora do prazo). O limite é calculado por
  `PeriodoFerias::limiteDeConcessao()` (fim do aquisitivo + 12 meses).
- **Situação calculada** (`SituacaoConcessaoFerias`, nunca gravada; o `status` antigo do período
  não muda): em aquisição, a conceder, **prazo acabando** (90 dias ou menos), **vencida** (com os
  dias que seriam pagos em dobro), gozada, gozada fora do prazo e **período sem registro**.
- **Período sem registro:** pela data de admissão, o `FeriasVencimentoService` sabe quais
  períodos aquisitivos já deveriam existir. Se um deles não foi cadastrado e o prazo de concessão
  venceu há menos de um ano (ou ainda não venceu), ele aparece como pendência. Histórico mais
  antigo é ignorado, porque não há como saber se foi gozado.
- **Painel de Férias** (`/admin/rh/painel-ferias`, grupo RH): contadores, filtro e link para a
  ficha do funcionário; o número no menu mostra vencidas + prazo acabando (cache de 10 minutos).
  A aba **Férias** da ficha ganhou as colunas "Conceder até" e "Prazo de concessão" (só para CLT).
- **Aviso semanal:** `rh:avisar-ferias` (segundas, 07:30) manda e-mail/push a quem tem
  `VerPainelFerias:Funcionario` e ao `super_admin`, **só com contagens** (sem nome de empregado).
- **Permissão:** `VerPainelFerias:Funcionario` para `super_admin` e `admin`.
- **Limites:** não modela o fracionamento em até três períodos (art. 134, § 1º) nem a redução do
  direito por faltas (art. 130); usa os dias informados pelo RH. Estatutário, PJ e estágio não
  entram (estágio tem recesso próprio, Lei nº 11.788/2008).
- Testes em `tests/Feature/FeriasVencimentoTest.php`.
