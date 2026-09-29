# Portal da Família: Notas, Frequência, Horários e Calendário de Aulas

Onda 2 do roadmap. Páginas do panel `portal` (`/portal`), acessível só a usuários com
papel `aluno` ou `responsavel` (`User::canAccessPanel`).

> Eventos escolares com confirmação de presença e a central de atendimento já foram
> entregues no commit `71f0a5b` (`EventosEscolares`, `CentralAtendimento`) e não fazem
> parte desta onda.

## 1. Escopo de dados e seleção de aluno

Trait: `App\Filament\Portal\Concerns\InteractsWithMatriculaSelecionada`

- As matrículas visíveis são as das `User::pessoasAcessiveis()` (a própria pessoa e seus
  dependentes), ordenadas com as **ativas primeiro**, depois período letivo e id mais
  recentes.
- A propriedade Livewire `matriculaId` (querystring `?aluno=`) só é aceita se pertencer a
  essa lista; qualquer outro valor cai na primeira matrícula. Isso vale a cada request,
  então alterar o parâmetro ou o estado no cliente não expõe matrícula de outra família.
- Com mais de um aluno, o partial `filament/portal/partials/seletor-aluno.blade.php`
  mostra um seletor "Aluno — Turma (Período)".
- O partial `filament/portal/partials/estilos.blade.php` traz o CSS das telas (classes
  `pf-*`), independente do bundle do Filament e neutro em tema claro/escuro. As tabelas
  rolam horizontalmente em telas pequenas e os cards/semana empilham abaixo de 640px.

## 2. Notas (`/portal/notas`)

`App\Filament\Portal\Pages\Notas` (menu **Meus Dados**, ordem 1).

- Reaproveita `BoletimService::getDadosBoletim()`: mesmas médias ponderadas, substituição
  por categoria, média da turma e frequência por disciplina do boletim em PDF. Só entram
  etapas que têm nota lançada para o aluno.
- Destaque em vermelho: média abaixo de `PeriodoLetivo::nota_aprovacao` (padrão 7,0 quando
  não definida) e frequência abaixo de `FrequenciaAlunoService::FREQUENCIA_MINIMA` (75%).
  Categorias substituídas aparecem riscadas.
- **Habilidades (BNCC):** `NotaHabilidade` do aluno agrupadas por etapa avaliativa, com
  código/nome da habilidade, badge do `ConceitoHabilidade` e observação.

## 3. Frequência (`/portal/frequencia`)

`App\Filament\Portal\Pages\Frequencia` (ordem 2) + `App\Services\FrequenciaAlunoService`.

- `resumo(Matricula)` agrega `frequencia_escolar` × `cronograma_aula` só com situação
  `presente`/`ausente` (registros sem situação são ignorados): total, presenças, faltas,
  percentual (1 casa) e `abaixo_minimo` (`< 75%`; exatamente 75% **não** é abaixo), geral e
  por disciplina (ordenada pelo nome).
- A página mostra aviso de frequência baixa, cards de resumo, tabela por disciplina e a
  lista aula a aula (Filament Table sobre `FrequenciaEscolar` com join no cronograma, com
  filtros de situação e disciplina, ordenada pela data da aula).

## 4. Horários (`/portal/horarios`)

`App\Filament\Portal\Pages\Horarios` (ordem 3) + `App\Services\HorarioAlunoService`.

- Agenda semanal montada do **cronograma datado** (`cronograma_aula`) da turma da
  matrícula: segunda a sexta sempre; sábado e domingo só com aula ou dia não letivo.
  Ordena por `hora_inicio`, mostra disciplina, professor, conteúdo ministrado e dever de
  casa.
- Dias não letivos: `DiaNaoLetivo` ativos do período letivo da turma, gerais
  (`curso_id` nulo) ou do curso da turma.
- Navegação: `semanaAnterior()`, `proximaSemana()`, `semanaAtual()`; a semana fica na
  querystring `?semana=Y-m-d`. Valor inválido cai na semana atual.
- Ainda não existe grade semanal recorrente; quando a Onda 4 criar `GradeHorario` esta
  página deve passar a usá-la (o serviço concentra a consulta).

## 5. Calendário

`Calendario::getEvents()` agora também devolve as aulas (`aula-{id}`) das turmas do
usuário, na janela `Calendario::AULAS_DIAS_PASSADOS` (45) a `AULAS_DIAS_FUTUROS` (90) em
torno de hoje, pois o calendário carrega todos os eventos de uma vez. Aulas com horário
viram eventos com início/fim; sem horário, eventos de dia inteiro. Em telas menores que
640px o calendário abre em `listMonth`.

## 6. Dashboard do portal

Cada aluno ganhou os atalhos **Notas**, **Frequência** e **Horários** apontando para a
matrícula mais recente (`?aluno=`).

## 7. Ajustes para telas pequenas (telas do professor)

- `LancamentoNotasGrade`: nome e nota lado a lado no celular (`columns default 3 / md 4`,
  nome com span 2) e teclado decimal (`inputMode('decimal')`).
- `CronogramaAulas\Pages\LancarFrequencia` (chamada): rótulos das linhas repetidas viram
  somente-leitor-de-tela (`hiddenLabel()`) e o toggle Presente/Ausente é agrupado
  (`grouped()`), reduzindo a altura de cada aluno.

## 8. Testes

`PortalNotasFrequenciaHorariosTest` (acesso, isolamento entre famílias, dois filhos,
notas, habilidades, frequência, horários, calendário) e `ProfessorTelasMobileTest`
(lançamento de notas em grade e chamada continuam renderizando e salvando).
