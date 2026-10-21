# Avaliação de Desempenho Docente (Onda 19)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda das telas (**Avaliações de Desempenho**,
> **Ciclos de Avaliação Docente** e **Instrumentos de Avaliação Docente**, grupo **RH** do
> menu) e na seção 62 do `MANUAL_USUARIO.md`.

## Contexto

Ciclo de observação/avaliação do professor pela coordenação, distinto de três módulos que já
existem:

- **RH e Gestão de Funcionários** (Onda 10): administrativo e contratual (cargo, salário,
  férias), não avalia desempenho pedagógico.
- **Avaliação do aluno** (notas, BNCC): mede o aluno, não o professor.
- **Questionários e Avaliação Institucional**: pesquisa genérica; não cobre um **ciclo de
  observação em sala** com critérios pedagógicos e histórico por professor.

## Como foi implementado

### Decisão: instrumento próprio em vez do motor de Questionários
A proposta original sugeria reaproveitar `Questionarios` como motor de formulário. Na
implementação isso foi **descartado** por três motivos:

1. O formulário de resposta dos questionários vive dentro da página `ResponderQuestionario`
   (montagem dos campos e gravação acopladas às regras de pesquisa: anonimato, alvos,
   limite de respostas por usuário, visibilidade). Reaproveitá-lo exigiria refatorar uma
   tela recém-blindada contra IDOR, com risco de regressão.
2. `QuestionarioResposta` não tem a figura do **avaliado**: guarda quem respondeu, mas não
   sobre quem.
3. O que a avaliação docente precisa (nota numa escala, peso por critério, pontuação
   consolidada, imutabilidade) é diferente de pergunta/resposta livre.

O objetivo da proposta (**critérios configuráveis, não um schema fixo**) foi mantido: a
escola monta o próprio roteiro em **Instrumentos de Avaliação Docente**.

### Modelos
- `InstrumentoAvaliacaoDocente` (`instrumentos_avaliacao_docente`): nome, descrição,
  `escala_maxima` (1 a 4, 5 ou 10), `ativo`.
- `CriterioAvaliacaoDocente` (`criterios_avaliacao_docente`): critério do instrumento, com
  `peso` e `ordem`.
- `CicloAvaliacaoDocente` (`ciclos_avaliacao_docente`): nome, início, fim, instrumento e
  `ativo` (ex.: "1º Semestre 2026").
- `AvaliacaoDocente` (`avaliacoes_docente`): `ciclo_id`, `funcionario_id` (professor
  avaliado, o `Funcionario` da Onda 10), `avaliador_user_id`, `turma_id` (opcional),
  `data_observacao`, `pontuacao_percentual`, `parecer` (**cifrado**), `status`
  (`StatusAvaliacaoDocente`: rascunho / finalizada) e `finalizada_em`. Única por ciclo,
  professor, avaliador e data.
- `AvaliacaoDocenteCriterio` (`avaliacao_docente_criterios`): nota por critério e
  `observacao` (**cifrada**).

### Regras (`AvaliacaoDocenteService`)
- `iniciar()`: o ciclo precisa estar aberto e a data dentro dele (e não futura); o funcionário
  não pode estar desligado na data; o instrumento precisa ter critérios; **ninguém avalia o
  próprio desempenho**; não repete a mesma observação (ciclo, professor, avaliador, data). Cria
  a avaliação em rascunho já com uma linha por critério.
- `finalizar()`: exige nota em todos os critérios, dentro da escala. A **pontuação** é a média
  das notas ponderada pelo peso, em **% da nota máxima da escala** (ex.: escala 5, pesos 2 e 1,
  notas 5 e 3 → 13/15 = 86,67%). Assim ela é comparável entre instrumentos de escalas
  diferentes e não muda se a escala for alterada depois.
- Depois de finalizada, a avaliação é **imutável**. `reabrir()` (permissão própria) devolve a
  rascunho e zera a pontuação.
- **Histórico congelado:** instrumento já usado em avaliação tem os critérios bloqueados para
  edição (crie um novo instrumento para mudar o roteiro); critério usado não é apagado do
  banco; ciclo com avaliações e instrumento com ciclos não podem ser excluídos (encerre/desative).

### Telas
- **Avaliações de Desempenho** (`/admin/avaliacao-docentes`): lista, nova observação (já cai
  na tela de notas), edição do rascunho com nota e observação por critério e parecer, ação
  **Finalizar avaliação** (grava o que está na tela e calcula a pontuação), página de
  visualização e ação **Reabrir**.
- **Ciclos** e **Instrumentos** de avaliação docente (cadastros).
- Aba **Avaliações de Desempenho** na ficha do **Funcionário** (histórico), visível apenas para
  quem consulta avaliações e não está atuando como professor.

### Acesso e LGPD (dado sensível de funcionário)
- `parecer` e `observacao` ficam **cifrados** no banco (`encrypted`); o log de atividade registra
  só o estado (nunca parecer ou notas).
- **Quem atua como professor** (`User::atuaComoProfessor()`) vê **somente as próprias avaliações
  já finalizadas**: nunca rascunhos, nunca as de colegas. A regra está na consulta do Resource
  (`AvaliacaoDocente::visiveisPara()`) e na `AvaliacaoDocentePolicy`.
- Coordenação e administração veem todas. Só o avaliador altera o próprio rascunho (a
  administração, com `Reabrir`, corrige o de qualquer um).
- Permissões (migration `2026_10_19_110100_create_avaliacao_docente_permissions`):
  - Instrumentos e ciclos: `super_admin`, `admin`, `coordenador` (excluir: `super_admin`, `admin`).
  - Avaliações: ver para `super_admin`, `admin`, `coordenador` e `professor` (recortado);
    criar, editar e `Finalizar` para `super_admin`, `admin`, `coordenador`; `Reabrir` e excluir
    para `super_admin` e `admin`.

## Melhoria: autoavaliação e plano de desenvolvimento (2026-10-21)

A avaliação só registrava a visão da coordenação e terminava na nota. Duas peças fecham o ciclo:

### Autoavaliação do professor (`AutoavaliacaoDocente`, `AutoavaliacaoNota`)
- O professor inicia a autoavaliação de um ciclo **aberto** (*RH → Autoavaliação Docente*) e dá
  nota aos **mesmos critérios** do instrumento, com observação opcional e comentário geral.
- Só o próprio professor inicia, preenche e envia; abrir duas vezes não duplica (um por ciclo e
  funcionário). Enviar exige todas as notas dentro da escala e calcula a pontuação com a mesma média
  ponderada da avaliação da coordenação, para os números serem comparáveis.
- O **rascunho é privado**: a coordenação e a administração só veem a autoavaliação **enviada**.
  Depois de enviada não muda; `Reabrir:AutoavaliacaoDocente` (administração) devolve ao professor.
- Comentário e observações cifrados; o log registra só o fluxo.

### Comparativo
`DesenvolvimentoDocenteService::comparativo()` cruza, por critério, a nota da autoavaliação enviada
com a **média das observações finalizadas** do ciclo e mostra a diferença (positiva: o professor se
vê melhor do que foi visto). Aparece na tela do plano de desenvolvimento.

### Plano de desenvolvimento individual (`PlanoDesenvolvimentoDocente`, `AcaoDesenvolvimentoDocente`)
- Nasce de uma **avaliação finalizada** (ação "Criar plano de desenvolvimento" na lista e na tela
  da avaliação), um por avaliação, em **rascunho**, com uma ação sugerida para cada critério com nota
  abaixo de **70% da escala** (prazo padrão de 90 dias).
- A coordenação ajusta objetivo, data de revisão e ações e **ativa** o plano (exige ao menos uma ação
  e nenhuma com prazo vencido). Só então o professor o enxerga.
- O professor **dá ciência** (registra a data, não concordância) e **registra o andamento** de cada
  ação (em andamento; concluída com resultado de pelo menos 10 caracteres).
- **Segregação:** quem é avaliado não edita, não ativa, não conclui e não cancela ações do próprio
  plano, mesmo que tenha papel de coordenação. Cancelar ação é da coordenação, com justificativa.
- A coordenação **conclui** o plano quando não há ação em aberto. A lista mostra ações atrasadas.
- Objetivo, descrição e resultado das ações cifrados; o log registra só o fluxo.

### Permissões
`ViewAny/View/Create/Update/Enviar:AutoavaliacaoDocente` (equipe, inclusive professor),
`Reabrir:AutoavaliacaoDocente` (administração); `ViewAny/View:PlanoDesenvolvimentoDocente`
(equipe, recortado), `Create/Update/Ativar/Concluir` (coordenação e administração), `Delete`
(administração, só rascunho), `DarCiencia/AtualizarAcao` (equipe, só o dono do plano ou quem
conduz).

## Decisões e limites

- Não há avaliação por pares nem avaliação 360° (alunos e famílias avaliando o professor).
- O professor avaliado não contesta a avaliação pelo sistema; o canal dele é a autoavaliação e a
  ciência/andamento do plano.
- Não há relatório comparativo entre professores nem exportação em PDF. O histórico por
  professor está na ficha do funcionário.
- A avaliação não se vincula a bônus ou remuneração.

## Testes

`tests/Feature/AvaliacaoDocenteTest.php`: início e validações, pontuação ponderada, finalizar e
reabrir, cifragem do parecer e das observações, histórico congelado, visibilidade do professor
(só as próprias finalizadas), policies e telas (formulários, ações, aba na ficha do
funcionário). A melhoria tem `tests/Feature/DesenvolvimentoDocenteTest.php` (regras, escopo,
policies, cifragem) e `tests/Feature/DesenvolvimentoDocenteTelasTest.php` (telas e bloqueios).
