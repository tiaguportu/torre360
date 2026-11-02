# Pix Automático e Cobrança Recorrente na Mensalidade (Onda 98) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).
> **Depende da [Onda 68](gateway_pagamento_real_roadmap.md)** (driver de gateway real); sem ele só dá para testar com o driver fictício.

## Contexto

A mensalidade se repete todo mês, mas a família precisa **lembrar de pagar** todo mês. O Pix Automático (modalidade do Pix para cobranças recorrentes, operada
pelos bancos desde 2025) permite que o responsável **autorize uma vez** e a cobrança siga sozinha, no vencimento. Para a escola, o efeito esperado é a queda do atraso
na origem, o que a régua de cobrança só trata depois que ele acontece.

## O que já existe (conferido no código)

- **Interface de cobrança:** `GatewayPagamento` com `criarCobranca(Fatura)`, `consultar(gatewayId)` e `cancelar(gatewayId)`, o `GatewayPagamentoManager` e o driver `fake`.
  A interface trata de **uma cobrança por fatura**; não conhece autorização recorrente.
- **Fatura:** `gateway`, `gateway_id`, `status_gateway`, `pix_copia_e_cola`, `linha_digitavel`, `boleto_url`, `link_pagamento`.
- **Retorno:** webhook `POST /api/webhooks/pagamento` com assinatura HMAC, idempotência por `event_id` e o evento `pagamento_confirmado`, que aciona
  `PagamentoConfirmacaoService` e `BaixaFaturaService`. Conciliação bancária (`ConciliacaoBancariaService`) como rede de segurança.
- **Geração das faturas do contrato** (`GeracaoFaturasContratoService`), `ResponsavelFinanceiro` (contrato, pessoa e percentual), encargos de atraso (Onda 62),
  régua de cobrança e acordos (`AcordoInadimplencia`, com link público por token).
- **Não existe** autorização do pagador, mandato, agendamento da cobrança antes do vencimento, nem tratamento de falha de débito recorrente.

## Escopo proposto

### Autorização (mandato)
- `MandatoCobranca`: contrato, responsável financeiro pagador, provedor, identificador externo, situação (pendente de autorização, ativo, recusado, cancelado, expirado),
  valor máximo e periodicidade.
- **Portal e matrícula/rematrícula:** botão "Pagar por Pix Automático". A família recebe o QR/link, autoriza no aplicativo do próprio banco e o provedor avisa por
  webhook. A autorização é do **pagador**, que pode não ser o responsável legal; só o responsável financeiro do contrato pode iniciá-la.
- Cancelamento pelo banco ou pela família (webhook) volta o contrato para o boleto/Pix comum **sem perder a fatura**.

### Cobrança
- A cobrança de cada fatura é **enviada ao banco com a antecedência que o regulamento e o provedor exigem** (confirmar o prazo no contrato com o provedor) e marcada como
  "débito agendado". Mudança de valor (encargo, reajuste, bolsa) depois do envio exige cancelar e reenviar dentro do prazo.
- Falha (sem saldo, conta encerrada): a fatura volta à régua de cobrança normal, com o motivo no histórico, e, se o regulamento permitir, **nova tentativa**.

### Acompanhamento
- Relatório de adesão (contratos com mandato ativo, por turma e série), taxa de sucesso do débito e **atraso médio de quem adere × quem não adere**, para provar o ganho.
- Fila na Minha Mesa: *Mandatos recusados ou cancelados* e *Débitos que falharam*.

## Base legal

- Regulamento do Pix Automático do Banco Central e as regras do provedor; autorização expressa do pagador.
- **Lei nº 9.870/1999:** a falha do débito segue as regras de inadimplência já tratadas (sem penalidade pedagógica nem retenção de documento).
- LGPD: dado bancário do pagador fica com o provedor; o sistema guarda só o identificador do mandato.

## Dependências e riscos

- **Depende do provedor** suportar o produto, do contrato e das tarifas. Decidir na Onda 68.
- Mudar valor depois de a cobrança sair é o ponto de maior risco: o fluxo de reajuste (Onda 44) e de encargos precisa respeitar a janela de envio.
- Responsável financeiro com divisão de percentual (dois pagadores) pede um mandato por pagador.
- Pix Automático é só um dos meios recorrentes; **débito em conta** e **cartão recorrente tokenizado** são irmãos e ficam como evolução do mesmo `MandatoCobranca`.

## Esforço

Médio (depende do provedor).

## Pacote

**P8 (Cobrança real e risco)**, depois do driver da Onda 68 e junto com o [risco de inadimplência (Onda 53)](risco_inadimplencia_roadmap.md). Ver a
[proposta de unificação](unificacao_de_ondas_proposta.md).
