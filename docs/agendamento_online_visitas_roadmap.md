# Agendamento Online de Visitas e Eventos (Onda 86) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade. Faz parte do [índice das ondas 80 a 88](ondas_80_a_88_indice.md) e continua a
> [Onda 85](distribuicao_leads_carteira_roadmap.md) (distribuição de leads).

## Contexto

A visita à escola é o salto do funil: a previsão de receita do CRM já trata *Visita Agendada* como a passagem de 25% (em atendimento)
para 50% de chance (`config/crm.php`, `previsao_receita`).
Mas só a secretaria ou o consultor consegue marcá-la: a família escreve ou liga, espera uma resposta e negocia um horário por
mensagem. Fora do expediente, o interesse esfria. A visita também não tem horário "oferecido": cada marcação é um horário digitado
à mão, sem checar se o consultor está livre.

## O que já existe (conferido no código)

- **Visita** (`VisitaInteressado`): data e hora, consultor, dependente, situação (agendada, realizada, faltou, cancelada) e
  observações. `VisitaInteressadoService::agendar` grava a visita, **antecipa o próximo contato** do lead e recalcula o lead score.
  **Não há disponibilidade, duração nem conferência de conflito do consultor.**
- **Lembrete:** `VisitaInteressadoService::enviarLembretes` avisa o **consultor**, 24 horas antes, pelo sino. A **família** só recebe
  lembrete se a régua de follow-up tiver regra para o gatilho `visita_lembrete` (a régua tem `visita_lembrete`, `visita_realizada` e
  `visita_faltou`, em e-mail e notificação do sistema).
- **Pós-visita:** pesquisa de satisfação por link (`/pesquisa-visita/{token}`, `PesquisaSatisfacaoVisita`) e, na Minha Mesa (Onda 84),
  *Visitas de hoje* e *Visita sem resultado*.
- **O padrão de horário oferecido já existe do outro lado do balcão:** na **Preceptoria**, o professor libera horários
  (`Preceptoria` com data, hora e professor) e a família do Portal escolhe um. É o mesmo desenho, para quem já é aluno.
- **Eventos** (`EventoEscolar`): título, tipo, local, datas, **limite de vagas**, prazo de confirmação, público-alvo, autorização e
  valor, com `EventoConfirmacao` (matrícula, responsável, acompanhantes). Hoje serve à **família matriculada**; a confirmação exige
  matrícula, então não atende um visitante.
- **Entradas públicas** que já criam o lead: `/quero-matricular` e a matrícula online. Nenhuma marca visita.

## Escopo proposto

### Agenda do consultor e horários oferecidos
- `DisponibilidadeVisita`: por consultor (ou por unidade), dias da semana, faixa de horário, **duração da visita** e antecedência
  mínima e máxima. Excepcional: bloqueio de data (feriado, o calendário de dias não letivos).
- Horário ocupado some da oferta; o consultor vê a agenda na Minha Mesa e no calendário do CRM (que já existe, com janela de
  `config/crm.php`).

### Página pública de agendamento
- Link `/visita` (e por campanha, com UTM): a família escolhe **unidade, dia e horário**, informa responsável, aluno e série de interesse
  e dá o **consentimento**. Cria o lead (ou reconhece a família já cadastrada, pelas regras do `CaptacaoInteressadoService`) e a visita.
- O consultor da visita sai da **mesma regra de distribuição** da [Onda 85](distribuicao_leads_carteira_roadmap.md).
- **Confirmação** por e-mail com o que levar, o endereço e **links para reagendar e cancelar** (token, como o da pesquisa de visita).
  O lembrete à família passa a existir mesmo sem regra configurada.
- Antirrobô: o mesmo reCAPTCHA já usado nos formulários públicos e limite por IP.

### Eventos para quem ainda não é aluno
- Tipo *Portas abertas* / *Aula experimental* em `EventoEscolar` com **inscrição pública** e vagas. A inscrição de visitante é um
  registro próprio (não a `EventoConfirmacao`, que exige matrícula) ligado ao lead; o evento aparece na página pública de agendamento.
- **Lista de presença** no dia, que marca quem veio e dispara a régua (`visita_realizada` ou `visita_faltou`).

### Regras
- Reagendar libera o horário antigo; cancelar com menos de N horas avisa o consultor.
- Nunca duas visitas no mesmo horário do mesmo consultor (travar a linha, como a reserva de espaços da Onda 21 faz com a sala).
- A visita marcada **antecipa o próximo contato** do lead, como já faz hoje.

## Base legal

LGPD, art. 7º, I e art. 14: a visita pode envolver **dado de criança** (nome, série, idade) coletado do responsável; o formulário
deve dizer a finalidade e colher o consentimento do responsável, e coletar só o necessário. A prova do consentimento segue o padrão do
CRM (`crm.lgpd`).

## Dependências e riscos

- **Reservas simultâneas:** duas famílias no mesmo horário. Resolver com bloqueio pessimista na criação e mensagem "horário acabou de
  ser ocupado".
- **Falta (no-show)** é o custo do agendamento fácil: lembrete 24 h e 2 h antes e confirmação por um clique reduzem; a régua já tem o
  gatilho `visita_faltou` para recuperar.
- **Fusos e horário de verão:** guardar a hora no fuso da escola.
- A página pública é superfície nova: limites de requisição, validação e o mesmo cuidado dos formulários atuais.
- Depende de a [Onda 85](distribuicao_leads_carteira_roadmap.md) escolher o consultor; sem ela, a visita pode nascer com o consultor
  da agenda escolhida pela família.

## Fusão sugerida

**Sequência do CRM (84 → 85 → 86 → 87).** O horário que a família escolhe é um lead novo com visita marcada: entra pela distribuição
(85), aparece na Mesa do consultor (84) e chega à [proposta](proposta_comercial_aceite_roadmap.md) (87) depois da visita. O
**acolhimento** de quem acabou de se matricular (checklist e pesquisa 30 dias depois) cabe como gatilho novo da régua, sem onda própria.
