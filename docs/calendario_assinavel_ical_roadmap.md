# Calendário Assinável (iCal) para a Família (Onda 92) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

A família que quer saber "quando é a prova" ou "quando é a reunião" precisa abrir o Portal. Quase todo responsável já usa a
agenda do celular, e é lá que a data precisa estar: um **feed `.ics`** que o Google Agenda, o Apple Calendário e o Outlook
assinam uma vez e mantêm atualizado, sem que a escola precise reenviar nada.

## O que já existe (conferido no código)

- **Calendário do Portal** (`Portal/Pages/Calendario`): provas (`Avaliacao.data_prevista`), dias não letivos (`DiaNaoLetivo`, por período
  letivo e, quando há, por curso) e as aulas do cronograma numa janela em torno de hoje (`CronogramaAula`).
- **Os eventos da escola não estão nesse calendário.** `EventoEscolar` (reunião de pais, passeio, festa) tem página própria
  (*Eventos* do Portal). Quem olha o calendário não vê a reunião.
- `CalendarioAvaliacaoService` (Onda 52): a grade de provas da turma e o limite por dia.
- `User::pessoasAcessiveis()`: a regra de quais alunos o responsável enxerga. O feed deve usar a mesma, sem regra nova de acesso.
- Reuniões com a família não existem como agenda individual (ver [Onda 100](agenda_atendimento_individual_roadmap.md)).
- **Não existe nenhuma saída `.ics`** (busca por `VCALENDAR` e `.ics` em `app`, `routes`, `config` e `database` sem resultado).

## Escopo proposto

### Feed por responsável
- `CalendarioFeedToken`: usuário, hash do token, criado em, **último acesso**, revogado em. O token é longo e aleatório, **aparece uma vez**
  (só o hash é guardado) e "gerar novo" invalida o anterior.
- Rota pública `/calendario/{token}.ics`, com limite de requisições, `ETag`/`Last-Modified` (o Google consulta de tempos em tempos) e
  resposta `404` igual para token inexistente e revogado.
- O feed reúne, para todos os alunos que o responsável enxerga: provas, dias não letivos, eventos da escola (com data de confirmação), reuniões
  agendadas e, opcional por responsável, as aulas. `UID` estável por item, para a atualização não duplicar.

### Portal
- Botão **Assinar no meu celular** no Calendário: copia o link, mostra o QR e explica os três passos para cada aplicativo. Lista o que o feed contém
  e deixa o responsável desligar tipos (por exemplo, sem aulas).
- Aproveita para **mostrar os eventos da escola no calendário do Portal**, que hoje só tem provas, feriados e aulas.

### Regras
- **Nada sensível no feed.** Título genérico ("Prova de Matemática — 5º A"), sem nome do aluno, nota, motivo de ocorrência nem valor. O feed é um
  link; quem o tiver lê tudo o que ele contém.
- Evento cancelado vira item cancelado (`STATUS:CANCELLED`), não some em silêncio.
- Fuso `America/Sao_Paulo` declarado no arquivo.

## Base legal

LGPD, art. 14 e art. 6º, III (necessidade): dado de criança sai da plataforma para um serviço de terceiros (a agenda do responsável). Por isso o
conteúdo mínimo e o token revogável.

## Dependências e riscos

- **Link vazado** dá acesso ao calendário. Mitigação: conteúdo mínimo, revogação em um clique e `último acesso` visível.
- Volume: um feed com aulas pode ter centenas de itens por aluno. As aulas ficam desligadas por padrão e limitadas a uma janela.
- Agendas externas atualizam de tempos em tempos (de minutos a um dia): a página deve dizer que não é tempo real.

## Esforço

Pequeno.

## Pacote

Independente. Quando a [Central de Avisos (Onda 70)](central_avisos_familia_roadmap.md) existir, o aviso "data mudou" pode apontar para o feed. Ver a
[proposta de unificação](unificacao_de_ondas_proposta.md).
