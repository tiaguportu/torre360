# Agendamento Online de Visitas e Eventos (Onda 86) — Implementada (parcial, sem a Onda 85)

> **Status: implementada em 2026-11-02**, no pacote P10, **sem a Onda 85** (distribuição de leads): o consultor da visita sai de uma regra mínima, descrita abaixo.
> A visita individual está pronta; os eventos *portas abertas* ficaram para depois (ver *Implementação*, no fim). O texto abaixo é o escopo original.
> Veja o [índice dos pacotes P2 e P10](pacotes_p2_p10_indice.md).

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

## Implementação (2026-11-02)

**Entregue** (`AgendaVisitaService`, `VisitaPublicaController`, `DisponibilidadeVisitaResource`, migration `2026_11_02_140000`):

- **Agenda do consultor** (*CRM / Comercial → Agenda de Visitas*): dia da semana, faixa, duração de cada visita, antecedência mínima (padrão 24 h) e máxima (padrão 30 dias) e, opcionalmente, a unidade.
- **Página pública `/visita`** (sem login, não indexável): a família escolhe unidade, dia e horário entre os **livres** (sem os dias não letivos, sem o que o consultor já tem marcado e dentro da
  antecedência), informa responsável, estudante, série e turno e dá o consentimento. Campo-armadilha, reCAPTCHA (quando configurado, como nos formulários atuais) e limite por origem.
- **Reserva.** Uma trava por horário e a conferência dentro dela evitam que duas famílias peguem o mesmo horário. O lead entra pelas regras do formulário público (reconhece quem já existe, sem
  duplicar), a visita nasce com token e a família recebe a confirmação por e-mail, com o que levar e os links para **trocar o horário** ou **cancelar**; o consultor é avisado no sino. Trocar mantém o
  consultor quando ele está livre e libera o horário antigo na hora; cancelar libera o horário e avisa o consultor (em destaque se faltam menos de 4 horas).
- **Consultor sem a Onda 85.** Com mais de um consultor no horário, vale quem tem menos visitas no dia (empate: o de menor id). Lead novo e sem dono passa a ser do consultor da visita.
- **Lembrete à família.** Vale a régua de follow-up (a migration da régua cria uma regra ativa de `visita_lembrete`); se a escola a desligar, `crm:notificar-pendentes` envia um lembrete direto às
  visitas marcadas online.

**Fora desta entrega:** eventos *portas abertas* e *aula experimental* com inscrição pública, lista de presença no dia, lembrete 2 h antes e a regra de distribuição da Onda 85.

**Testes:** `AgendaVisitasOnlineTest`.
