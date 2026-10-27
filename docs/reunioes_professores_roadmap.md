# Reuniões com Professores (Onda 32) — Proposta para o Futuro

> **Status: implementada** em 2026-10-28, junto com a Onda 27, como uma entrega só: veja
> [Reuniões e atas](reunioes_e_atas_roadmap.md), que traz o que foi feito, as decisões em relação a
> esta proposta e o que ficou de fora. O texto abaixo é a proposta original.

## Contexto

A coordenação se reúne com a equipe (reunião pedagógica semanal, planejamento, formação) e com
professores individualmente (devolutiva de observação de aula). As decisões e tarefas combinadas
costumam se perder em conversas e atas soltas.

## O que já existe (ponto de partida)

- `Preceptoria` (agendamento de reuniões com **famílias**) e `EventoEscolar`: calendário e convite.
- `AvaliacaoDocente` (Onda 19): devolutiva individual ao professor.
- `ReservaEspaco` (Onda 21): sala para a reunião.
- `NotificationService` e `FcmService`: lembretes.
- Não existe reunião interna com pauta, presença, ata e **tarefas com responsável e prazo**.

## Escopo

### Modelos novos
- `ReuniaoEquipe`: título, tipo (pedagógica, individual, formação), data/hora, local (sala),
  organizador, pauta, status.
- `ReuniaoParticipante`: pessoa, convite (aceito/recusado), presença.
- `ReuniaoEncaminhamento`: descrição, responsável, prazo, status (aberto, feito, vencido).
- `ReuniaoAta`: texto, anexos; versão fechada por quem organiza.

### Telas
- Agendamento com convite e reserva de sala (checando conflito).
- Registro de presença, ata e encaminhamentos na própria tela.
- **Meus encaminhamentos**: lista do professor, com vencidos em destaque.
- Painel da coordenação: encaminhamentos abertos por pessoa.

### Regras
- Reunião individual de devolutiva é visível só ao organizador e ao professor.
- Encaminhamento vencido gera lembrete ao responsável e à coordenação (reaproveitando o digest da
  Onda 47).

## Dependências já satisfeitas

- Calendário, reserva de espaços e notificações.

## Risco e observação

- Conteúdo sensível (avaliação de pessoas): permissões estritas, sem aparecer no calendário geral.
- Evitar duplicar a Onda 27 (Conselho de Classe), que é uma reunião com deliberação por aluno e
  formato próprio.
