# Excursões e Passeios Escolares (Onda 15) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Módulo comum em sistemas de gestão escolar (passeio/excursão com autorização assinada dos
responsáveis, cobrança do valor e lista de presença de segurança), não coberto pela lista
de marketing do Sponte nem pelas Ondas 1-8.

## O que já existe (ponto de partida)

- `EventoEscolar` + `EventoConfirmacao` (Onda 2) cobrem só confirmação simples de presença
  ("vou / não vou"), sem autorização assinada, sem cobrança e sem lista de segurança — um
  passeio precisa das três coisas.
- `AssinafyService` (contratos, Onda 7) já cobre assinatura eletrônica com validade
  jurídica — é só reaproveitar para a autorização do passeio, não reimplementar.
- `GatewayPagamento`/`Fatura` (Onda 6) já cobrem cobrança avulsa — é só gerar uma fatura
  ligada ao passeio em vez de ao contrato de mensalidade.

## Escopo

### Modelo novo
- `PasseioEscolar`: nome, turma(s) ou série alvo, data, local, valor, texto da autorização
  (riscos, horários, o que levar), data limite para confirmação.
- `InscricaoPasseio`: `passeio_escolar_id`, `matricula_id`, status (convidado/confirmado/
  pago/cancelado), `documento_assinatura_id` (autorização assinada via Assinafy),
  `fatura_id` (cobrança do passeio, quando houver valor).

### Telas
- Cadastro do passeio pela secretaria/coordenação.
- No Portal da Família: card do passeio, botão para assinar a autorização e pagar (reaproveita
  o mesmo fluxo de pagamento já usado no Financeiro do portal).
- Lista de presença/segurança do passeio (quem confirmou, quem assinou, quem pagou) para
  o responsável pela turma levar no dia.

## Dependências já satisfeitas

- `AssinafyService` (Onda 7) e `GatewayPagamento`/`Fatura` (Onda 6) já cobrem assinatura e
  cobrança — o trabalho real é só o model `PasseioEscolar`/`InscricaoPasseio` e as telas,
  orquestrando serviços que já existem e já são testados.
