# Preceptoria: filtro por ciclo, calendário com arrastar e soltar e recorrência

> **Status: implementada** em 2026-10-28. Manual: §6.5.1 (cadastro recorrente) e §18.3.1 (calendário).

## O pedido

Em `admin/preceptorias`: (1) filtrar por **ciclo de preceptoria**; (2) organizar as preceptorias num
**calendário de arrastar e soltar**, tratando a preceptoria como um evento com data, hora e recursos
(professor preceptor e aluno); (3) criar preceptorias **recorrentes** numa janela de tempo,
desconsiderando feriados, dias não letivos e finais de semana (ou não).

## O que foi feito

### 1. Filtro e coluna de ciclo
`PreceptoriasTable`: `SelectFilter` `ciclo_preceptoria_id` (ciclos mais recentes primeiro) e coluna
**Ciclo** (alternável), com `cicloPreceptoria` no eager loading da tabela.

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
- **Conflito**: data em que o mesmo professor já tem preceptoria com horário sobreposto é pulada
  (horários que só se encostam, 14:00–14:30 e 14:30–15:00, não conflitam; sem hora de fim vale
  30 min). Rodar a mesma recorrência duas vezes não duplica nada.
- **Teto** de 400 preceptorias por geração (`PreceptoriaAgendaService::LIMITE_SERIE`).
- O modo "Datas Específicas" não mudou (continua sem checar conflito).
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
  responsáveis e professor por `LembretePreceptoriaNotification` com o horário anterior
  (`reagendadoDe`). Cada reagendamento grava `activity` (`reagendamento_preceptoria`).
- **Selecionar** um intervalo vazio (semana/dia) abre "Novo horário" com dia e hora (`Create:Preceptoria`).
- **Gerar recorrência**: ação do cabeçalho com o mesmo formulário e a mesma prévia do cadastro.
- Clicar no bloco abre a edição (ou a visualização, sem permissão de editar).
- Entrada própria na navegação do grupo Preceptoria e botão "Calendário" na listagem. Acesso: quem
  pode ver a listagem (`viewAny`).

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
· alterados: `PreceptoriaForm`, `CreatePreceptoria`, `ListPreceptorias`, `PreceptoriasTable`,
`PreceptoriaResource`, `Preceptoria::relembrarAgendamento`, `LembretePreceptoriaNotification`.
Testes: `tests/Feature/Preceptorias/{PreceptoriaAgendaServiceTest,CalendarioPreceptoriasTest,PreceptoriaRecorrenciaCriacaoTest}.php`.

## O que ficou de fora

- **Série sem identidade**: as preceptorias geradas não guardam um `serie_id`; não há "editar/excluir a
  série inteira". Hoje: filtrar por ciclo/professor na listagem e usar as ações em lote.
- **Trocar professor ou aluno pelo calendário**: o clique abre a edição completa. Agendar o aluno num
  horário livre continua na tela "Agendar Preceptoria".
- **Feriados**: só os nacionais fixos e a Sexta-Feira Santa; Carnaval, Corpus Christi e feriados
  estaduais/municipais entram como dia não letivo cadastrado.
- **Corrida entre dois usuários**: o conflito é checado na hora, sem restrição no banco.
- **Aviso de reagendamento** reaproveita o canal do lembrete (e-mail, push, sininho); não há uma
  notificação própria nem histórico na tela.
- O JavaScript do calendário (arrastar, esticar, selecionar) não tem teste automatizado: os testes
  cobrem o servidor (eventos, reagendar, ações de criar e gerar).
- Suspeita **não verificada**, fora do escopo: a ação em lote "Clonar" chama `replicate()` sobre
  registros carregados com `withExists('relatorios')`; o atributo `relatorios_exists` pode ir para o
  INSERT (o mesmo problema que já apareceu em `TurmaDuplicacaoService`). Vale testar a ação.
