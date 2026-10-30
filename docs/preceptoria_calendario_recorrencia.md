# Preceptoria: filtro por ciclo, calendário com arrastar e soltar e recorrência

> **Status: implementada** em 2026-10-28; troca de professor/aluno pelo calendário, notificação própria
> de reagendamento e histórico na tela em 2026-10-29. Manual: §6.5.1 (cadastro recorrente), §18.3.1
> (calendário) e §18.3.2 (histórico).

## O pedido

Em `admin/preceptorias`: (1) filtrar por **ciclo de preceptoria**; (2) organizar as preceptorias num
**calendário de arrastar e soltar**, tratando a preceptoria como um evento com data, hora e recursos
(professor preceptor e aluno); (3) criar preceptorias **recorrentes** numa janela de tempo,
desconsiderando feriados, dias não letivos e finais de semana (ou não).

## O que foi feito

### 1. Filtro e coluna de ciclo
`PreceptoriasTable`: `SelectFilter` `ciclo_preceptoria_id` (ciclos mais recentes primeiro) e coluna
**Ciclo** (alternável), com `cicloPreceptoria` no eager loading da tabela.

**Ciclos vencidos (2026-10-29)**: a listagem esconde, por padrão, as preceptorias de ciclos cujo
término já passou, e o seletor de ciclo só oferece os ciclos que não venceram.

- "Vencido" = `data_fim` anterior a hoje (`CicloPreceptoria::scopeNaoVencidos`: término hoje ou depois,
  ou sem término). Ciclos que ainda não começaram contam como válidos, ao contrário de `vigentes()`.
- Filtro `ocultar_ciclos_vencidos`: uma chave **ligada por padrão** ("Ocultar ciclos de preceptoria
  vencidos"). Foi feito assim porque o Filament só roda o callback de um filtro quando ele está ativo;
  desligar a chave é o "optar por exibir". "Limpar filtros" volta a ligá-la. Usa
  `excludeWhenResolvingRecord()` para não atrapalhar ações sobre um registro específico.
- Preceptoria **sem ciclo** continua aparecendo (não é de ciclo vencido).
- O seletor **acompanha a chave**: ligada, só os ciclos válidos; desmarcada, todos (os vencidos podem ser
  escolhidos). A chave é `live()` e o seletor lê o estado do formulário de filtros
  (`getTableFilterFormState`, que com filtros adiados é o ainda não aplicado), então a lista muda
  assim que a chave é mexida, sem esperar o "Aplicar". Um ciclo vencido já escolhido permanece na lista
  se a chave for religada (para o rótulo não sumir); a listagem fica vazia até o usuário trocá-lo.
- Só a listagem: o calendário (`/admin/preceptorias/calendario`), o seletor de ciclo dos formulários de
  cadastro e o dashboard não mudaram.

### 2. Recorrência (`PreceptoriaAgendaService`)
O modo "Intervalo de Datas (Range)" do cadastro virou **Recorrente (janela de datas)**:

| Campo | Efeito |
|---|---|
| Data inicial / final | janela; ao escolher o ciclo (ou o modo) a janela do ciclo é sugerida se estiver vazia |
| Dias da semana | vazio = todos os dias da janela |
| Repetir | toda semana, quinzenal, a cada 3 ou 4 semanas (contadas a partir da semana da data inicial) |
| Desconsiderar finais de semana | ligado por padrão; ligado, as opções de dia da semana não mostram sábado/domingo |
| Desconsiderar feriados nacionais | ligado por padrão (`DiaNaoLetivo::getFeriadoNacional`: fixos + Sexta-Feira Santa) |
| Desconsiderar dias não letivos | ligado por padrão; vale o período letivo do ciclo (sem período, qualquer período) |

- A **prévia** no formulário (`PreceptoriaRecorrenciaForm`) mostra o que será criado, o que foi
  ignorado e por quê, e as datas puladas por conflito.
- **Conflito**: data em que o mesmo professor (ou o aluno, se a série já é de um aluno) já tem preceptoria
  com horário sobreposto é pulada (ver "Horários concorrentes"). Rodar a mesma recorrência duas vezes
  não duplica nada.
- **Teto** de 400 preceptorias por geração (`PreceptoriaAgendaService::LIMITE_SERIE`).
- O modo "Datas Específicas" também pula as datas em conflito (`PreceptoriaAgendaService::gerarNasDatas`).
- Quando nada é criado (tudo ignorado ou em conflito) o cadastro avisa e não cria o registro "de
  hoje" que o modo antigo criava como valor de reserva.

### 3. Calendário (`CalendarioPreceptorias`, rota `/admin/preceptorias/calendario`)
FullCalendar 6.1.11 pela CDN (a mesma versão dos outros calendários do projeto) mais o idioma pt-br.

- **Eventos** buscados por período visível (`getEventos`), sempre pela query do próprio resource
  (`PreceptoriaResource::getEloquentQuery()`): professor vê as suas, responsável/aluno as dos
  dependentes. Filtros: ciclo, professores e situação. Feriados nacionais e dias não letivos viram
  faixas de fundo.
- **Arrastar** muda dia/horário e **esticar** muda a duração (`reagendarPreceptoria`, exige
  `Update:Preceptoria`). Recusa choque de horário do professor (o bloco volta), término em outro dia
  e data inválida. Soltar em fim de semana, feriado ou dia não letivo é **permitido com aviso**.
- Preceptoria **já agendada**: confirmação no navegador e, se o novo horário é futuro, aviso a aluno,
  responsáveis e professor pela notificação própria `PreceptoriaAlteradaNotification` (mudança
  `horario`, com o horário anterior e quem alterou). Horário livre: só o professor é avisado. Quem
  fez a mudança não recebe o próprio aviso. Cada reagendamento grava `activity` (`reagendamento_preceptoria`).
- **Selecionar** um intervalo vazio (semana/dia) abre "Novo horário" com dia e hora (`Create:Preceptoria`).
- **Gerar recorrência**: ação do cabeçalho com o mesmo formulário e a mesma prévia do cadastro.
- Clicar no bloco abre o modal **Gerenciar preceptoria** (`gerenciarPreceptoria`, só com
  `Update:Preceptoria`); sem essa permissão, abre a visualização. Ver "Trocar professor e aluno".
- Entrada própria na navegação do grupo Preceptoria e botão "Calendário" na listagem. Acesso: quem
  pode ver a listagem (`viewAny`).

### 4. Trocar professor e aluno pelo calendário (`PreceptoriaAgendaService::atualizarParticipantes`)
Modal com **Professor(a)**, **Aluno (matrícula)** (esvaziar libera o horário), as cinco últimas
alterações e o atalho "Abrir registro completo".

- O aluno só pode ser mexido por quem também tem `Agendar:Preceptoria`; sem ela o campo some e
  `alterarAluno` vai `false`. A busca de matrículas usa a mesma regra da tela "Agendar Preceptoria"
  (`matriculasAcessiveis`, agora no serviço e compartilhada; a restrição ficou agrupada num único
  `where` para a busca por nome não a desfazer).
- **Tudo é conferido antes de gravar**: o novo professor e o novo aluno não podem ter outra
  preceptoria sobreposta ("Horários concorrentes"); o aluno não pode ter outra no mesmo ciclo (como a
  tela de agendar faz); e, havendo aluno, o professor tem de ser **do aluno**
  (`professoresDaMatricula`: conselheiro da turma + professores do cronograma dela), a mesma regra
  que a tela "Agendar Preceptoria" aplica ao listar horários. Vale tanto ao pôr um aluno quanto ao
  trocar o professor de um horário já agendado. Se falha, nada muda (nem o histórico).
- No modal, os dois campos se filtram mutuamente (`live`): escolhido o professor, a busca de alunos
  traz só os dele (`matriculasDoProfessor`); escolhido o aluno, a busca de professores traz só os dele.
  O serviço confere de novo, então a lista é só conforto.
- **Avisos** (quem fez a mudança fica de fora):

  | Mudança | Recebe |
  |---|---|
  | Professor, aluno igual | professor antigo, novo, aluno e responsáveis: `PreceptoriaAlteradaNotification` (professor) |
  | Aluno novo | aluno novo e responsáveis, e o professor (se não trocou): `PreceptoriaNotification` agendamento |
  | Aluno que sai | aluno e responsáveis; o professor, se o horário ficou livre: `PreceptoriaNotification` liberação |
  | Professor e aluno juntos | professores só pelo aviso de professor; alunos pelos avisos de agendamento/liberação |

- O e-mail de liberação leva o nome de quem saiu (`alunoAnterior`): o registro já foi alterado quando
  o job da fila monta a mensagem.

### 5. Histórico na tela
- `Preceptoria::historico()` lê o log de atividades nos eventos de `Preceptoria::EVENTOS_HISTORICO`
  (agendamento, liberação, troca de aluno, troca de professor, troca de ciclo, reagendamento,
  exclusão, restauração, aviso enviado, lembrete enviado). Cada aviso grava
  `notificacao_alteracao_preceptoria` com o número de destinatários. As mudanças feitas pela edição
  completa e em lote entram do mesmo jeito (§7); a exclusão e a restauração, em §8.
- Aparece no botão **Histórico de Alterações** do registro (`ViewPreceptoria`) e, resumido, no modal
  do calendário (partial `filament.components.preceptoria-historico`). Só para quem tem
  `Update:Preceptoria`: o texto traz nomes de alunos.
- As telas "Agendar Preceptoria" (admin e Portal) passaram a gravar `agendamento_preceptoria` e
  `liberacao_preceptoria`. O aviso de liberação delas agora leva o nome de quem saiu
  (`PreceptoriaNotification::alunoAnterior`, passado por `enviarNotificacoes`): o e-mail é montado
  pela fila quando o registro já não tem o aluno, e antes saía "Aluno (era): N/D".

### 6. Horários concorrentes (mesmo professor ou mesmo aluno)
Regra: **um professor ou um aluno não pode ter duas preceptorias em horários que se sobrepõem**.
Horários que só se encostam não conflitam; sem hora de fim vale 30 min.

- **Onde vive**: `Preceptoria::save()` passa por `PreceptoriaAgendaService::gravarSemConflito`. Como
  `create`, `update`, `replicate()->save()` e as factories chamam `save()`, a regra vale em qualquer
  caminho (cadastro avulso e recorrente, edição, clonar, editar em lote, tela de agendar do admin e
  do Portal, calendário). Só a gravação direta no banco (`DB::table`, `Query::update`) escapa.
- **Aluno = a pessoa**: `conflitosDoAluno` compara por `matricula.pessoa_id`, então duas matrículas do
  mesmo aluno também conflitam, com qualquer professor.
- **Só confere o que mudou**: o professor se `professor_id`, data ou hora mudaram (ou é registro novo);
  o aluno se `matricula_id`, data ou hora mudaram. Liberar o aluno de um horário não reabre a conferência
  do professor, então dado antigo que já se sobrepõe não trava o ajuste; só aparece quando alguém
  mexe no professor, na data ou no horário.
- **Corrida entre dois usuários**: a conferência e a gravação rodam numa transação que trava
  (`lockForUpdate`, na ordem do id) a linha de `pessoa` do professor e do aluno. Dois usuários marcando o mesmo
  professor/aluno ao mesmo tempo se enfileiram e o segundo enxerga a gravação do primeiro. Em SQLite
  (testes) o `lockForUpdate` é ignorado, então a trava em si só vale em MySQL/MariaDB e **não tem
  teste automatizado** (o teste cobre a conferência, não a concorrência real).
- **Exceção**: `ConflitoDeHorarioException` (um `DomainException`) com mensagem pronta para o usuário.
  Cada tela a captura e avisa: edição ("Horário em conflito", formulário aberto), cadastro avulso
  (pula a data, como a recorrência), clonar/editar em lote (as que dão choque ficam como estavam e o
  aviso lista os motivos), agendar admin/Portal e calendário (arrastar, "Novo horário", modal).
- **Datas avulsas** passam por `gerarNasDatas`, que pula as datas em conflito e usa o mesmo aviso da
  recorrência (`PreceptoriaRecorrenciaForm::avisarResultado`).
- **Clonar sem nova data** agora é recusado: cairia exatamente em cima do original.

### 7. Edição completa e em lote: histórico e avisos
`PreceptoriaAgendaService::atualizarComHistorico(Preceptoria, array $dados, bool $notificar = true)` é por
onde passam a **edição completa** (`EditPreceptoria::handleRecordUpdate`) e a ação em lote **Editar em
Lote** (uma preceptoria por vez, em `PreceptoriasTable`). Ele tira um retrato do que está gravado, faz
o `update` (com a regra de horários concorrentes) e compara.

- **O "antes" vem do banco (`getOriginal`)**, não dos atributos do model. O seletor de relação do
  Filament (professor, aluno, ciclo) faz `associate()` da chave estrangeira no model *antes* de a página
  chamar `handleRecordUpdate`, então o valor "atual" de `professor_id` já é o novo; data e hora, que são
  campos comuns, ainda não. Lendo o atual, só a troca de horário era percebida (os testes pegaram isso).
- **Histórico**: sempre, na mesma transação da gravação. Eventos: `troca_professor_preceptoria`,
  `agendamento_` / `troca_aluno_` / `liberacao_preceptoria`, `reagendamento_preceptoria` (texto
  "24/08/2026 das 10:00 às 10:30") e o novo `troca_ciclo_preceptoria` ("Troca de ciclo"). Nada mudou:
  nada é registrado. Conflito recusado: a transação desfaz e nada é registrado nem avisado.
- **Avisos**, só se a preceptoria é **futura** (`isAgendamentoFuturo()`): corrigir o cadastro de uma que
  já passou não manda nada, mas o histórico registra. Quem fez a mudança não recebe.

  | Mudou | Recebe |
  |---|---|
  | Só o horário | professor, aluno e responsáveis: `PreceptoriaAlteradaNotification` (horário) |
  | Professor | como no calendário (§4); se o horário mudou junto, o aviso de professor leva também o **horário anterior** (`horarioAnterior`), então ninguém recebe dois |
  | Aluno | como no calendário (§4); os avisos de agendamento/liberação já mostram o horário novo, sem aviso extra de reagendamento |
  | Só o ciclo | ninguém (só o histórico) |

- A tela de edição acrescenta ao "Salvo" quantos destinatários foram avisados; o lote, ao resumo.
- **Push**: `PreceptoriaAlteradaNotification::toPush` chamava `getUrl(..., absolute: false)`, mas o
  parâmetro do Filament é `isAbsolute`; o `Error` era engolido pelo `FcmChannel` (que só registra no
  log) e o push nunca saía. Corrigido nela, com teste. **`PreceptoriaNotification` e
  `LembretePreceptoriaNotification` têm o mesmo defeito** (anterior a esta mudança) e não foram
  alteradas, porque corrigir faz o push passar a ser enviado em produção.

### 8. Exclusão e restauração
Excluir uma preceptoria deixou de apagar a linha: `Preceptoria` usa `SoftDeletes` (migration
`2026_10_30_100000_add_soft_deletes_to_preceptoria_table`, só acrescenta `deleted_at`; validada em
MariaDB: aplica, é idempotente e reverte). O escopo do soft delete tira a excluída das listas, do
calendário, do Portal e **das consultas de horário concorrente**, então o horário volta a ficar livre
para o professor e o aluno.

- **Por onde passa**: `PreceptoriaAgendaService::excluirComHistorico(Preceptoria, bool $notificar = true)`,
  chamado pelo botão **Excluir** de `EditPreceptoria` e pela exclusão em lote de `PreceptoriasTable`
  (ambos com `->using()`; é o `delete()` do model que continua sendo usado, só que agora lógico).
  Uma exclusão feita direto no model (Tinker, outro código) também é lógica, mas não deixa histórico
  nem avisa: o rastro é do serviço, como no resto.
- **Com relatório, não exclui** (`ExclusaoDePreceptoriaRecusadaException`): a chave de `relatorio_preceptoria`
  é `cascade` e o relatório é o registro do encontro (pode ser público para a família). A conferência
  roda dentro da transação, depois de ler o registro. Para devolver o horário à agenda, basta liberar o
  aluno. No lote, as que têm relatório ficam e o aviso de conclusão diz quantas.
- **Histórico** (`exclusao_preceptoria`, "Exclusão"): na mesma transação do `delete()`, com horário,
  professor, aluno e ciclo como texto nas propriedades. Como a tela da excluída não abre, o rastro se
  consulta no log global de atividades (ao restaurar, o histórico completo volta ao registro).
- **Aviso** (`PreceptoriaCanceladaNotification`): só se a preceptoria é **futura e tem aluno**; vai para
  o professor, o aluno e os responsáveis (e-mail, sininho e push), menos quem excluiu. Horário livre e
  preceptoria que já passou só registram. A notificação é enfileirada e **leva só texto** (data,
  horário, nomes, quem cancelou): a fila a remonta depois de a preceptoria sair das listas e ela não
  pode depender de reencontrar o model. O link do e-mail/sininho/push é a listagem.
- **Prévia no modal** (`previaDaExclusao`): diz quantas serão recusadas por relatório e quantos
  destinatários receberão o aviso, antes de confirmar.
- **Restaurar** (`restaurarComHistorico`): filtro **Registros excluídos** (`TrashedFilter`, só para quem tem
  `DeleteAny:Preceptoria`) com a ação de linha e a ação em lote **Restaurar**. Confere de novo o choque
  de horário do professor e do aluno (travando as pessoas, como `gravarSemConflito`; o `restore()`
  sozinho não conferiria, porque só `deleted_at` muda) e, se nesse tempo alguém marcou outra no
  horário, recusa com o motivo. Registra `restauracao_preceptoria` e, sendo futura e com aluno, avisa
  o professor, o aluno e os responsáveis com o aviso de agendamento (o horário voltou).
- **Permissão**: a policy aceita `Delete:`/`DeleteAny:` também para `restore`/`restoreAny`: quem exclui
  restaura, sem uma permissão nova para cadastrar. `ForceDelete` não é oferecido em tela.
- Uma excluída não abre na tela de detalhes nem de edição (a rota não enxerga o soft delete); na
  listagem só a ação **Restaurar** aparece nela.

## Decisões

- **Sem serviço de calendário de terceiros pago**: a visão "uma coluna por professor" é recurso
  premium do FullCalendar (Scheduler); no lugar, filtro por professor e título com professor/aluno.
- **Feriado e dia não letivo são avisos, não bloqueios, no arrastar**: a coordenação às vezes
  precisa marcar num sábado; na recorrência, ao contrário, o padrão é pular.
- **Comparação de horário em PHP**, não em SQL: a coluna de hora guarda formatos diferentes
  (`14:00:00` ou `2026-… 14:00:00`) conforme quem gravou.
- `dia_nao_letivo.curso_id` pode não existir no banco montado pela cadeia de migrations fora do
  SQLite (ver nota da Onda 72–74): o serviço só filtra por ela se a coluna existir, e só conta dias
  gerais (preceptoria não pertence a curso).

## Arquivos

`app/Services/PreceptoriaAgendaService.php` · `app/Filament/Resources/Preceptorias/Schemas/PreceptoriaRecorrenciaForm.php`
· `…/Pages/CalendarioPreceptorias.php` · `resources/views/filament/resources/preceptorias/pages/calendario-preceptorias.blade.php`
· `app/Notifications/Preceptorias/{PreceptoriaAlteradaNotification,PreceptoriaCanceladaNotification}.php` · `app/Exceptions/ExclusaoDePreceptoriaRecusadaException.php`
· `database/migrations/2026_10_30_100000_add_soft_deletes_to_preceptoria_table.php` · `app/Policies/PreceptoriaPolicy.php` · `resources/views/filament/components/preceptoria-historico.blade.php`
· alterados: `PreceptoriaForm`, `CreatePreceptoria`, `ListPreceptorias`, `ViewPreceptoria`, `AgendarPreceptoria`,
`PreceptoriasTable`, `PreceptoriaResource`, `Preceptoria` (`historico()`, `destinatariosDasPessoas()`),
`PreceptoriaNotification` (`alunoAnterior`), `EditPreceptoria`, `Portal\Pages\Preceptoria`, `App\Exceptions\ConflitoDeHorarioException`.
O `LembretePreceptoriaNotification` voltou a ser só lembrete.
Testes: `tests/Feature/Preceptorias/{PreceptoriaAgendaServiceTest,CalendarioPreceptoriasTest,PreceptoriaRecorrenciaCriacaoTest,PreceptoriaHistoricoTest,PreceptoriaConflitoHorarioTest,PreceptoriaCiclosVencidosTest,PreceptoriaEdicaoHistoricoTest,PreceptoriaExclusaoTest}.php`.

## O que ficou de fora

- **Série sem identidade**: as preceptorias geradas não guardam um `serie_id`; não há "editar/excluir a
  série inteira". Hoje: filtrar por ciclo/professor na listagem e usar as ações em lote.
- **Arrastar um aluno** para um horário livre no calendário: a troca/agendamento é pelo modal (clique no bloco).
- **Professor da turma na edição completa**: a regra "só professor do aluno" vale no modal do calendário
  (e na tela "Agendar Preceptoria", que só lista horários deles). A **edição completa** do registro e o
  cadastro ainda deixam a equipe combinar qualquer professor com qualquer aluno (o formulário só filtra a
  lista de alunos pelo professor escolhido); é a porta de exceção, por escolha.
- **Feriados**: só os nacionais fixos e a Sexta-Feira Santa; Carnaval, Corpus Christi e feriados
  estaduais/municipais entram como dia não letivo cadastrado.
- **Dados antigos já sobrepostos** (de antes da regra) continuam no banco; não há relatório que os liste.
- **Histórico e avisos**: calendário, reagendamento, telas de agendar, edição completa, Editar em
  Lote, exclusão e restauração registram e avisam (§4, §7, §8). Continuam **sem rastro**: o
  **cadastro** (avulso e recorrente) e o **Clonar em Lote**, que criam registros novos e livres. O
  modelo não usa `LogsActivity`: o histórico é gravado pelo serviço, então uma alteração feita fora
  dele (Tinker, `DB::table`, outro código) não aparece.
- **Exclusão**: não há exclusão definitiva em tela (`ForceDelete`); uma excluída fica guardada. A
  exclusão em lote manda um aviso por preceptoria (quem tiver várias recebe várias). Preceptoria com
  relatório não é excluída. O `lockForUpdate` da restauração, como o da gravação, só vale em MySQL.
- **Os avisos da edição completa e do lote** saem sempre que a preceptoria é futura (não há chave
  "não avisar"), como no calendário.
- O JavaScript do calendário (arrastar, esticar, selecionar) não tem teste automatizado: os testes
  cobrem o servidor (eventos, reagendar, ações de criar e gerar).
- Push de `PreceptoriaNotification` e `LembretePreceptoriaNotification` não sai (`absolute:` no lugar de
  `isAbsolute:`; ver §7).
