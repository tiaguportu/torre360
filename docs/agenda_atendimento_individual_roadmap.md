# Agenda de Atendimento Individual (Família com Professor ou Coordenação) (Onda 100) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

A família que quer conversar com o professor ou com a coordenação escreve pelo chamado, liga ou espera a reunião de pais. A conversa combinada por mensagem ("pode
ser quinta?") gasta tempo dos dois lados e se perde. Falta um **horário oferecido**: o professor ou a coordenação abre janelas e o responsável escolhe uma.

## O que já existe (conferido no código)

- **Reunião de pais** é só um tipo de `EventoEscolar` (`reuniao_pais`): evento coletivo com confirmação, vagas e prazo. Não é conversa individual.
- **Preceptoria** (`Preceptoria`: professor, matrícula, data, hora, ciclo): o professor e o aluno têm horários marcados, e a família escolhe no Portal.
  `PreceptoriaAgendaService` já resolve **conflito de horário, recorrência, dias não letivos, lembrete, reagendamento, cancelamento e histórico**, e as notificações
  correspondentes. É acompanhamento pedagógico regular, não atendimento à família sob demanda.
- `DisponibilidadeProfessor` (dia da semana, faixa, tipo), `GradeHorario` (o que o professor já tem em aula) e `ReservaEspaco` (sala sem conflito).
- **Reuniões internas** com ata e encaminhamentos (Ondas 27 e 32, `Reuniao`); `AtendimentoChamado` (conversa por texto, sem horário).
- **Não existe** janela de atendimento oferecida à família.

## Escopo proposto

### Oferta
- `JanelaAtendimento`: dono (professor ou coordenador), dias da semana e faixa, **duração do atendimento**, antecedência mínima e máxima, vigência, modalidade
  (presencial na sala indicada, por telefone ou por vídeo) e limite por semana. Não oferece horário em que o professor está em aula na grade, em dia não letivo ou fora da
  disponibilidade cadastrada.
- A coordenação pode abrir **janelas por série** ("coordenação do 5º ano, quartas à tarde").

### Marcação
- `AgendamentoAtendimento`: janela, início, matrícula, responsável solicitante, **motivo** (lista curta: desempenho, comportamento, saúde, financeiro-pedagógico, outro),
  situação (marcado, confirmado, realizado, faltou, cancelado) e anotação breve de depois. Travar o horário para duas famílias não marcarem o mesmo.
- **Portal:** *Marcar conversa*: escolhe com quem (professores do aluno e coordenação da série), o dia e o horário; ver, reagendar e cancelar até N horas antes. Limite de
  marcações abertas por família.
- **Lembretes** 24 h e 2 h antes pelos canais da [Onda 70](central_avisos_familia_roadmap.md); aviso ao professor.
- **Minha Mesa:** *Atendimentos de hoje* (professor e coordenação) e *Atendimentos realizados sem anotação*.

### Depois do atendimento
- Anotação curta e restrita (quem pode ver: o próprio professor e a coordenação). Quando o motivo é dificuldade do aluno, **oferece abrir uma intervenção**
  ([P4](unificacao_de_ondas_proposta.md)) com prazo. Reuniões com a coordenação podem gerar a ata da [Onda 27/32](reunioes_e_atas_roadmap.md).

## Base legal

LGPD, art. 14 (dado de criança) e art. 6º, III: o registro guarda o mínimo; a anotação clínica ou disciplinar não vai ao Portal.

## Dependências e riscos

- **Sobrecarga do professor:** janela aberta demais vira plantão permanente. Limites por semana e janelas explícitas, controladas pelo próprio professor.
- **Motor de horários único:** esta onda, o [agendamento de visitas (Onda 86)](agendamento_online_visitas_roadmap.md) e a seleção de candidatos
  ([Onda 104](selecao_professores_roadmap.md)) precisam do mesmo "horário oferecido, travado e confirmado". Construir uma vez, na primeira das três.
- Falta (no-show) da família: registrar e, se frequente, avisar a coordenação.
- Não confundir com Preceptoria: aquela é rotina com ciclo; esta é demanda da família.

## Esforço

Médio (pequeno se o motor de horários já existir).

## Pacote

**P10 (Funil comercial)** pelo motor de horários e **P2 (Solicitações e casos)** pelo motivo e pelo retorno. Ver a [proposta de unificação](unificacao_de_ondas_proposta.md).
