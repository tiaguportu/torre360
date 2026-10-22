# Grade Horária Assistida (Onda 64)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda das telas e na seção 76 do
> `MANUAL_USUARIO.md`. Tela: `/admin/calendario/grade-assistida` (grupo Calendário e Horários) e a aba
> **Tempos de aula** em Configurações → Turnos.

## Contexto

O sistema tinha a grade horária de cada turma (dia, horário, disciplina, professor e sala) com detecção de
conflito, e uma matriz curricular com as aulas por semana de cada disciplina na série, mas **as duas nunca se
falavam**: ninguém sabia se a turma tinha as 5 aulas de Matemática da matriz, e montar a grade era à mão, tempo
a tempo, descobrindo o conflito só ao salvar. Também faltavam os tempos de aula do turno e a disponibilidade dos
professores.

## O que já existia

- `GradeHorario` (por turma), `GradeHorarioConflitoService`, `MatrizCurricular` (`carga_horaria_semanal` em
  aulas por semana), `turma_disciplina.professor_id` (quem leciona cada disciplina na turma), `Turno` (só
  início e fim) e o cronograma de aulas gerado a partir da grade.
- **Defeito encontrado:** o conflito de professor e de sala olhava **todas** as turmas, de qualquer período
  letivo. A grade do ano passado, que continua no banco, travava a deste ano.

## O que foi feito

### Tempos de aula do turno
Tabela `tempos_aula`: número, início e fim de cada tempo do turno (o "sinal"). O intervalo é o espaço entre dois
tempos. Em Configurações → Turnos, a aba **Tempos de aula** cadastra um a um (sem cruzar tempos) ou **gera todos**
(primeiro horário, quantidade, duração e um intervalo). Um horário da grade vale tantas aulas quantos tempos
inteiros couberem nele (aula dupla = 2); horário fora do sinal vale 1.

### Disponibilidade do professor
Tabela `disponibilidades_professor`: janelas por dia da semana, **disponível** ou **indisponível**, com
observação. Regra:

- sem janela nenhuma, o professor está livre;
- **indisponível** sempre bloqueia;
- havendo ao menos uma janela **disponível**, ele só dá aula dentro delas (dia sem janela = não dá aula);
- janelas que se encostam valem como uma só.

Edita-se na própria tela da grade assistida, só para professores que lecionam na turma escolhida. Na grade
manual da turma, marcar horário fora da disponibilidade **avisa** sem impedir de salvar.

### Conferência com a matriz
Para cada turma: por disciplina da matriz, aulas esperadas, aulas na grade, professor da turma e a situação
(completa, faltam, excedente, sem carga na matriz, fora da matriz). Cartões com o total. A **visão geral do
período** lista as turmas ativas com quantas aulas faltam em cada uma e as que têm turno sem tempos.

### Sugestão de horários
Calculada sem gravar e mostrada em um modal; **aplicar recalcula na hora** e confere cada horário contra os
conflitos antes de gravar (o que conflitar fica de fora e é avisado).

- Procura lugar nos dias de `config/grade_horaria.php` (`dias_da_semana`, padrão segunda a sexta), em tempos
  **livres da turma**, onde o professor **não está em outra turma** de um período que se cruza, **está
  disponível** e a disciplina não passa do limite diário (`maximo_aulas_por_dia_da_disciplina`, padrão 2).
- Espalha cada disciplina pela semana (dia com menos aulas dela primeiro); a segunda aula no mesmo dia vai de
  preferência ao lado da primeira. Quem tem menos horários possíveis escolhe primeiro.
- **Nunca troca nem apaga** horário que já existe. A mesma grade dá sempre a mesma sugestão.
- Aula que não coube vem com o motivo: turma sem tempo livre, professor sem disponibilidade ou ocupado,
  limite diário, ou **disciplina sem professor definido** na turma (a menos que
  `sugerir_sem_professor` esteja ligado).
- O log (`calendario`) guarda as contagens.

### Correção do conflito
`GradeHorarioConflitoService` passou a considerar, para professor e sala, só turmas cujo **período letivo se
cruza** com o da turma do horário, e a ignorar turma **cancelada**.

### Permissão
`GradeAssistida:Turma` (administração, secretaria e coordenação) cobre ver a conferência, editar a
disponibilidade e aplicar a sugestão.

## Decisões e limites

- É uma **sugestão gulosa, não uma otimização**: pode sobrar aula sem lugar mesmo havendo solução trocando
  horários já colocados. A tela diz o porquê para a pessoa liberar horários ou ajustar a disponibilidade.
- A sugestão **não escolhe sala** (fica em branco) e não limita aulas seguidas nem a carga semanal do professor.
- Só sugere disciplinas da matriz com professor definido em `turma_disciplina`.
- A disponibilidade vale para qualquer período (não é por ano letivo).
- Depois de aplicar, o cronograma de aulas datado continua sendo gerado em Turmas, como antes.

## Testes

`tests/Feature/GradeAssistidaTest.php` (42 testes: tempos de aula, disponibilidade, conflito por período e
turma cancelada, conferência com a matriz, sugestão em vários cenários, aplicação e telas) e o
`GradeHorarioTest` já existente.
