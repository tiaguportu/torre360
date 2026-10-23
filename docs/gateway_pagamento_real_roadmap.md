# Gateway de Pagamento Real: Asaas e Efí (Onda 68) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for
> autorizada — não há código, migration ou teste desta funcionalidade no sistema ainda.

## Contexto

A camada de gateway existe desde a Onda 6 e funciona de ponta a ponta com um driver **fictício**: o botão
*Gerar Cobrança* grava um PIX e uma linha digitável sintaticamente válidos mas falsos, e o webhook baixa a fatura.
Enquanto não há driver real, a cobrança (boleto ou PIX) sai de fora do sistema e a baixa vem da **conciliação por
extrato** (OFX ou CSV importado). Isso tem três custos: o responsável não paga pelo Portal, a baixa depende de alguém importar o extrato, e o valor
cobrado no boleto não acompanha a multa e os juros que o sistema calcula (Onda 62).

Esta onda troca o driver fictício por um **real**, sem mudar quem consome a interface (fatura, Portal, webhook,
régua de cobrança).

## O que já existe (ponto de partida)

- `App\Contracts\GatewayPagamento` (`chave`, `rotulo`, `criarCobranca`, `consultar`, `cancelar`),
  `GatewayPagamentoManager` e o driver `FakeGatewayPagamento`. A troca é `PAGAMENTOS_GATEWAY_DRIVER`.
- `Fatura` com `gateway`, `gateway_id` (único), `status_gateway`, `pix_copia_e_cola`, `linha_digitavel`, `boleto_url` e
  `link_pagamento`; ações *Gerar Cobrança* e *Dados de Pagamento*; botões de pagamento no Portal da Família.
- `POST /api/webhooks/pagamento` (`PagamentoWebhookController`): contrato genérico com assinatura HMAC
  (`X-Pagamento-Signature`), **falha fechado** sem `PAGAMENTOS_WEBHOOK_SECRET`, idempotência por `event_id`.
- `PagamentoConfirmacaoService::confirmar()` e `BaixaFaturaService::darBaixa()`: baixa a fatura, sem reabrir fatura
  cancelada. `ConciliacaoBancariaService` (OFX/CSV) concilia créditos com faturas.
- Onda 61: concluir um cancelamento já chama `GatewayPagamento::cancelar()` nas faturas canceladas.
- Onda 62: `EncargosAtrasoService` calcula multa (até 2%), juros e valor atualizado.
- `ReguaCobrancaService` (lembretes) e o risco de inadimplência (Onda 53).

## Escopo

### Driver `asaas` (primeiro)
- Classe `AsaasGatewayPagamento` implementando `GatewayPagamento`, registrada no manager. Chamadas HTTP com timeout,
  nova tentativa só para falha de rede ou 5xx, e erro claro (a fatura **continua sem cobrança**, nunca com cobrança
  pela metade).
- `criarCobranca(Fatura)`: cria ou reaproveita o **cliente** do pagador (responsável financeiro: nome, CPF ou CNPJ,
  e-mail), cria a cobrança com o saldo devedor, o vencimento, `externalReference = id da fatura` e a descrição, e busca
  o QR Code PIX e o código de barras. Grava os campos que a fatura já tem.
- `consultar(gatewayId)` e `cancelar(gatewayId)` com o contrato de hoje.
- Ambientes **sandbox** e **produção** na mesma configuração, com botão *Testar conexão*.

### Webhook do provedor
- Endpoint próprio por driver (`/api/webhooks/pagamento/asaas`), que valida o **token do provedor** com
  `hash_equals`, traduz o evento para o contrato genérico e chama `PagamentoConfirmacaoService`. Pagamento confirmado
  ou recebido dá baixa; vencida, estornada e removida só atualizam `status_gateway` e avisam a equipe.
- Idempotência pelo id do evento do provedor. Evento de cobrança desconhecida responde 200 (para o provedor não
  reenviar para sempre) e vai para o log.
- **Os nomes dos eventos e dos campos devem ser conferidos na documentação do provedor na hora de implementar.**

### Encargos alinhados (fusão com a Onda 62)
- A cobrança nasce com **multa e juros configurados** no gateway a partir de `contrato.multa_percentual` e
  `contrato.juros_mensal_percentual`, para o boleto vencido cobrar o mesmo que o demonstrativo do sistema. Sem isso, o
  responsável paga um valor e o sistema espera outro.
- *Reemitir cobrança* quando o valor muda (desconto, acordo de inadimplência, encargos): cancela a cobrança antiga e
  cria a nova na mesma fatura, mantendo o histórico.

### Conciliação e tarifas
- A baixa pelo webhook guarda o **valor líquido** e a **tarifa** do gateway. Um relatório mensal de tarifas alimenta as
  despesas financeiras do fluxo de caixa.
- Pagamento que chega pelo gateway e também por transferência (duplicidade) é marcado para conferência, não baixado
  duas vezes.

### Telas
- **Configuração do gateway** (driver, ambiente, chave e token, testar conexão), com a chave **criptografada** e nunca
  exibida depois de salva.
- Na fatura: situação no gateway, reemitir, cancelar cobrança e link de pagamento.
- Portal: PIX copia e cola e boleto reais, com a situação atualizada.

### Efí (segundo driver)
- Mesma interface; autenticação por OAuth2 e certificado do cliente (mTLS) para o PIX. Só entra se a escola não
  escolher o Asaas: não vale manter dois drivers reais sem necessidade.

## Regras

- O driver real **nunca** é usado em ambiente de teste: a suíte continua com `fake` e `Http::fake`.
- A chave do provedor fica em `.env` ou criptografada no banco; não vai para log nem para o histórico de alterações.
- Só os dados do **pagador** vão ao provedor (nome, documento, e-mail, telefone); o nome e os dados do aluno não.
- Baixa só por webhook validado ou por consulta à API; payload sem assinatura não dá baixa.

## Base legal e regulatória

- **LGPD:** o provedor é **operador**; entra no registro de operações e no aviso de privacidade, e o contrato com ele
  prevê o tratamento dos dados do pagador.
- **PIX e boleto** seguem as regras do Banco Central e da rede bancária (regulamento do PIX, Resolução BCB nº 1/2020).
  A conta de recebimento e as tarifas são contratadas pela escola; a contabilidade valida o tratamento fiscal.

## Dependências já satisfeitas

- Interface e manager, campos na fatura, webhook genérico e idempotência, baixa automática, conciliação por extrato,
  encargos (Onda 62) e cancelamento de cobrança (Onda 61).

## Risco e observação

- **Custo por cobrança** (boleto, PIX e cartão) muda a conta da escola: simular antes de ligar para todos.
- **Duplicidade de cobrança** se a mesma fatura for emitida por fora e pelo gateway. Começar por um período letivo ou
  por uma turma, com a cobrança antiga desligada.
- **Webhook atrasado ou perdido:** manter a rotina de consulta como rede de segurança (como no Assinafy).
- Dependência de terceiro: o sistema precisa continuar funcionando (e a fatura continuar válida) com o gateway fora do
  ar.
- Cartão de crédito recorrente exige tratamento à parte (tokenização no provedor, nunca no sistema). Fica fora desta
  onda.

## Ampliações

- **Estorno e devolução pelo gateway:** a devolução da Onda 61 deixa de ser manual quando a cobrança foi paga por ele.
- **Acordo de inadimplência** (módulo que já existe): cada parcela do acordo passa a gerar a sua cobrança no gateway.
- **Débito automático** e **PIX automático**, quando o provedor oferecer.
