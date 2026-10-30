# Resumo Diário de Faltas à Família (Onda 91)

> **Status: implementada** (2026-10-29). Troca o e-mail, o sino e o push **por falta** por um único aviso por responsável, enviado
> uma vez por dia. Substitui o comportamento descrito na seção 2 de [comunicacao_canais_alerta_falta_massa.md](comunicacao_canais_alerta_falta_massa.md).

## Contexto

Cada falta lançada gerava um aviso imediato **por aula e por responsável**, nos três canais. Um aluno com 6 faltas no dia rendia 6
e-mails, 6 itens no sino e 6 pushes para cada responsável, e quem tem dois filhos na escola recebia o dobro. A reclamação era o volume.

## O que mudou

- **Lançar a falta não avisa mais ninguém na hora.** O `FrequenciaFaltaObserver` só marca a falta como *pendente de resumo*
  (`frequencia_escolar.aviso_resumo_pendente`), no evento `saving`, junto com a gravação da própria falta.
- **O comando `frequencia:enviar-resumo-diario`** roda todo dia às **18:00** (`FALTAS_RESUMO_HORARIO` no `.env`, padrão em
  `config/frequencia.php`), junta tudo o que está pendente e manda **um aviso por responsável**, com os alunos dele e, por aluno, as
  aulas em ordem de data e horário (disciplina e professor). E-mail, sino e push saem consolidados em uma única
  `FrequenciaResumoDiarioNotification`.
- **Sem pendência, nada sai.** O comando diz "Nenhuma falta pendente de aviso" e termina.
- A página **Frequência** do Portal continua mostrando a falta aula a aula e ao vivo; só o aviso passou a ser diário.

## Regras

| Situação | O que acontece |
|---|---|
| Falta lançada antes do horário de corte | Sai no resumo do mesmo dia. |
| Falta lançada **depois** do corte, ou de uma aula antiga (a chamada aceita até 60 dias para trás) | Sai no próximo resumo, com a **data de cada aula** visível. A chave é "pendente", não "aulas de hoje". |
| Corrigida para presente antes do corte | Sai da fila; o responsável não é avisado. |
| Apagada antes do corte (inclusive em cascata) | Não aparece: os pendentes são relidos do banco na hora do envio. |
| Marcada como justificada | Fica fora do resumo. (Nenhuma tela grava `justificada` hoje; a regra vale para quando existir.) |
| Aula fora da vigência da matrícula (`data_ativacao` / `data_desativacao`) | Não é marcada, como já era. |
| Já avisada e salva de novo como falta | Não volta à fila. Corrigir para presente e depois voltar a falta marca de novo. |
| Aluno sem responsável com usuário **na hora do envio** | A falta é descartada (não acumula) e conta como "sem responsável com usuário" na saída do comando. |
| Responsável cadastrado **antes** do corte | Recebe, mesmo que a falta tenha sido lançada de manhã: os responsáveis só são resolvidos no envio. |
| Responsável com dois filhos | Um aviso só, com os dois alunos. |
| Dois responsáveis para o mesmo aluno | Cada um recebe o seu. |

## Como funciona o envio

`App\Services\ResumoFaltasDiarioService`:

1. Dentro de uma transação, relê os pendentes (`aviso_resumo_pendente`, `situacao = 'ausente'`, `justificada = false`) com
   `lockForUpdate` e **reserva** todos (`aviso_resumo_pendente = false`, `aviso_resumo_enviado_em = agora`). Duas rodadas simultâneas
   não duplicam; o padrão é o mesmo de `RelatorioExecutorService::executarAgendados`.
2. Agrupa por usuário responsável, depois por aluno, e despacha uma notification por usuário. A notification recebe **arrays**, não
   models, então o job enfileirado não quebra se a falta for corrigida ou apagada antes de o worker rodar.
3. Se o despacho de um usuário falhar, as faltas dele voltam para a fila, exceto as que outro responsável já recebeu (para ninguém
   receber em dobro). O comando termina com erro e diz quantos responsáveis falharam; elas saem no próximo resumo.

`php artisan frequencia:enviar-resumo-diario --dry-run` mostra quem receberia quantas faltas e **não reserva nada**.

## Como ligar em produção

- O resumo depende do `schedule:run` a cada minuto no cron **e** do worker da fila (`queue:work`, agendado na primeira linha de
  `routes/console.php`). Sem o cron, nada sai, nem o resumo nem os demais avisos.
- A migration (`add_aviso_resumo_to_frequencia_escolar_table`) tem padrão `false`: as faltas que já existem **não** entram no primeiro
  resumo. Não há backfill.
- No dia da publicação, as faltas já enfileiradas saem do jeito antigo (um e-mail por aula); a partir do corte seguinte vem o resumo.
  Vale avisar secretaria e professores da mudança.
- Para trocar o horário: `FALTAS_RESUMO_HORARIO=17:30` no `.env` (formato `HH:MM`, fuso da aplicação).

## Fora do escopo

- **Responsável sem `User` continua sem aviso**, como antes. A cobrança cai para `Pessoa.email`; aqui não. Candidato a follow-up.
- `Pessoa.aceita_comunicacao` continua ignorado, pela decisão já registrada de que aviso individual obrigatório não é comunicação em massa.
- **Sem retratação:** uma falta já avisada e depois corrigida não gera aviso de correção (também não gerava).
- Horário editável pela secretaria numa tela (padrão `*Configuracao`) e segunda rodada de manhã para o que ficou da noite anterior foram
  consideradas e deixadas de fora: um horário só, ajustável por `.env`.
- Não absorve a [Onda 47](digest_diario_papel_roadmap.md) (digest da equipe) nem a [Onda 70](central_avisos_familia_roadmap.md)
  (central de avisos da família). Se a Onda 70 sair, este resumo é um dos avisos que ela passa a orquestrar.

## Arquivos

- `app/Observers/FrequenciaFaltaObserver.php`: marca a pendência (e a limpa na correção).
- `app/Services/ResumoFaltasDiarioService.php`: reserva, agrupa e despacha.
- `app/Notifications/FrequenciaResumoDiarioNotification.php`: e-mail, sino e push (substitui `FrequenciaAusenciaNotification`, removida).
- `app/Console/Commands/EnviarResumoFaltasCommand.php` e `routes/console.php`: comando e agenda.
- `config/frequencia.php`: `resumo_horario`.
- Migration `2026_10_29_120000_add_aviso_resumo_to_frequencia_escolar_table.php`.
- Testes: `tests/Feature/ResumoFaltasDiarioTest.php` e `tests/Feature/FrequenciaFaltaPendenteResumoTest.php`.
