# Financeiro Automatizado: Gateway, Webhook, Conciliação e Relatórios

Onda 6 do roadmap de funcionalidades.

## 0. Reescopo desta onda

O plano original desta onda tinha 7 itens; o item "status Atrasado automático" e o item
"régua de cobrança automática" **já existiam** antes desta onda, implementados pela
**Régua de Cobrança Inteligente** (`App\Services\ReguaCobrancaService`, ver seção 35 do
`MANUAL_USUARIO.md`). Esta onda cobre o que faltava:

1. Relatórios financeiros: inadimplência, fluxo de caixa, contas a pagar.
2. Extração do `BaixaFaturaService`.
3. Camada de gateway de pagamento (`GatewayPagamento`, driver `Fake`).
4. Webhook de pagamento + baixa automática (e correção de validação de assinatura no
   webhook do Assinafy, que não validava).
5. Conciliação automática crédito ↔ fatura.
6. Botões de pagamento no Portal da Família.

## 1. `BaixaFaturaService`

`App\Services\BaixaFaturaService::darBaixa(Fatura $fatura, array $dados)` registra uma
`TransacaoBancaria` de entrada e recalcula o status da fatura (Pago, Parcial, ou mantém —
nunca reabre uma fatura Cancelada). Extraído de `FaturasTable::darBaixaAction()`, que
agora só monta o formulário e delega ao serviço. Reutilizado por:

- O webhook de pagamento (`PagamentoConfirmacaoService`).
- A conciliação bancária automática (`ConciliacaoBancariaService`).

**Banco padrão:** quando a baixa não vem de um operador escolhendo o banco na tela
(webhook, conciliação), usa `PAGAMENTOS_BANCO_ID_PADRAO` do `.env`, com fallback para o
primeiro banco ativo cadastrado. Lança `RuntimeException` se não houver nenhum.

## 2. Camada de Gateway de Pagamento

### Interface e driver

`App\Contracts\GatewayPagamento` define o contrato: `criarCobranca()`, `consultar()`,
`cancelar()`. `App\Services\GatewayPagamentoManager::resolver()` resolve o driver
configurado em `config('pagamentos.driver')` (`.env`: `PAGAMENTOS_GATEWAY_DRIVER`, padrão
`fake`) — mesmo padrão de `CanalMensagemManager`.

**Driver `Fake`** (`App\Services\Gateways\FakeGatewayPagamento`): não chama nenhuma API
externa. Gera um PIX copia-e-cola e uma linha digitável sintaticamente válidos mas
fictícios, registra tudo em log. Serve para desenvolvimento e demonstração; um driver real
(Asaas, Efí etc.) entra implementando a mesma interface e trocando só
`PAGAMENTOS_GATEWAY_DRIVER` — nada mais no sistema precisa mudar.

### Campos novos em `Fatura`

`gateway`, `gateway_id` (único), `status_gateway`, `linha_digitavel`, `boleto_url`,
`link_pagamento`. `pix_copia_e_cola` **já existia** na tabela (herdado da antiga
`titulos`, renomeada para `faturas`) — só passou a ser preenchido de verdade agora.

### Ações no admin (`Faturas`)

- **Gerar Cobrança:** cria a cobrança no gateway configurado e grava os dados na fatura.
  Só aparece se ainda não houver `gateway_id`.
- **Dados de Pagamento:** mostra o PIX copia-e-cola, linha digitável e links já gerados.
- **Simular Pagamento (Dev):** só aparece com o driver `fake` ativo — confirma o
  pagamento como se o webhook tivesse chegado, usando a mesma lógica idempotente do
  webhook real (`PagamentoConfirmacaoService`). Útil para testar o fluxo completo sem um
  gateway de verdade.

A régua de cobrança (`{{PIX_COPIA_COLA}}`) agora usa o dado real da fatura quando
existente, com uma mensagem de fallback ("ainda não gerado") em vez do texto fixo fake
anterior.

## 3. Webhook de Pagamento

`POST /api/webhooks/pagamento` (`App\Http\Controllers\Webhooks\PagamentoWebhookController`).

Payload esperado (contrato genérico — um driver real mapeia o formato do gateway para
este formato, ou expõe seu próprio endpoint tradutor):

```json
{
  "gateway_id": "FAKE-123-ABCD1234",
  "event_id": "evt_unico_do_gateway",
  "evento": "pagamento_confirmado",
  "valor": 500.00,
  "data_pagamento": "2026-03-05"
}
```

- `evento: "pagamento_confirmado"` dispara a baixa (`PagamentoConfirmacaoService`); outros
  valores de evento só atualizam `status_gateway`, sem baixa.
- **Idempotência:** por `event_id` (cai para `gateway_id` se ausente) — verifica se já
  existe uma `TransacaoBancaria` com esse `external_id` antes de processar. Reenvios do
  mesmo evento (comportamento normal de webhook, que reenvia até receber 200) não
  duplicam a baixa.
- `gateway_id` desconhecido retorna 200 (não 404) para não gerar reenvios infinitos de um
  identificador que nunca vai existir no nosso lado.
- Fatura cancelada: ignora a confirmação (não reabre).

### Validação de assinatura

`App\Services\WebhookSignatureValidator`: HMAC-SHA256 do corpo bruto da requisição com um
segredo compartilhado (`PAGAMENTOS_WEBHOOK_SECRET`), enviado pelo gateway no header
`X-Pagamento-Signature`. **Se o segredo não estiver configurado, a validação é pulada**
(com aviso no log) — não quebra antes de você configurar o segredo, mas também não
protege o endpoint até configurar.

**Correção no webhook do Assinafy:** o mesmo validador foi aplicado em
`AssinafyWebhookController` (`ASSINAFY_WEBHOOK_SECRET`, header `X-Assinafy-Signature`),
que antes não validava assinatura nenhuma. Pings de validação (GET ou corpo vazio)
continuam sem exigir assinatura, já que não há payload assinável nesse caso.

## 4. Conciliação Automática Crédito ↔ Fatura

`ConciliacaoBancariaService::conciliarCreditosComFaturas(?int $bancoId = null)`: tenta
casar `TransacaoBancaria` de entrada ainda não vinculadas a nenhuma fatura.

1. **Por identificador explícito** na descrição do extrato (ex.: "Pagamento Fatura #123").
2. **Por valor + janela de data** em torno do vencimento (-45/+10 dias) quando não há
   identificador, ou ele não bate — só concilia automaticamente quando há **exatamente
   uma** fatura em aberto candidata; valores ambíguos (duas faturas do mesmo valor no
   período) ficam para conciliação manual.

Roda automaticamente ao final de `processarOfx()`/`processarCsv()`, e pode ser
reexecutada manualmente pelo botão **"Conciliar Créditos Pendentes"** na listagem de
Transações Bancárias — útil quando o crédito foi importado antes da fatura existir.

## 5. Relatórios Financeiros

### Relatório de Inadimplência (`/admin/financeiro/relatorio-inadimplencia`)

Faturas em atraso com aluno, turma, responsável(is), dias em atraso (com selo colorido:
amarelo ≤7 dias, vermelho ≤30, cinza acima) e saldo devedor. Resumo no topo (total de
faturas, valor total devido, responsáveis únicos). Filtros por turma e por faixa de
atraso (1–7, 8–15, 16–30, 31+ dias).

### Fluxo de Caixa (`/admin/financeiro/fluxo-de-caixa`)

Consolidado mensal (últimos 12 meses) de entradas, saídas e saldo, a partir de
`TransacaoBancaria`. Resumo do período no topo.

### Contas a Pagar (`/admin/conta-pagars`)

Novo CRUD: `App\Models\ContaPagar` (descrição, valor, vencimento, status, fornecedor,
plano de contas, centro de custo). Ação **"Dar Baixa"** gera uma `TransacaoBancaria` de
saída vinculada e marca a conta como Paga. Comando agendado
`financeiro:atualizar-contas-pagar-atrasadas` (diário, 07:00) marca como Atrasado as
contas Pendentes vencidas — mesmo princípio de `atualizarStatusFaturasAtrasadas()` da
Régua de Cobrança.

## 6. Portal da Família: Pagamento

`Portal/Pages/Financeiro.php` ganhou a ação **"Pagar"** por fatura: gera a cobrança no
gateway automaticamente na primeira vez (se ainda não existir) e mostra PIX copia-e-cola,
boleto (quando o driver fornecer) e link de pagamento/2ª via — sem a família precisar
falar com a secretaria.

## 7. Variáveis de `.env`

| Variável | Padrão | Descrição |
|---|---|---|
| `PAGAMENTOS_GATEWAY_DRIVER` | `fake` | Driver de gateway ativo. |
| `PAGAMENTOS_BANCO_ID_PADRAO` | _(vazio)_ | Banco usado em baixas automáticas sem operador. |
| `PAGAMENTOS_WEBHOOK_SECRET` | _(vazio)_ | Segredo HMAC do webhook de pagamento. |
| `ASSINAFY_WEBHOOK_SECRET` | _(vazio)_ | Segredo HMAC do webhook do Assinafy (novo). |

## 8. Migrations desta onda

| Migration | O que faz |
|---|---|
| `add_gateway_fields_to_faturas_table` | Colunas de gateway em `faturas` (não inclui `pix_copia_e_cola`, que já existia). |
| `create_conta_pagars_table` | Tabela `conta_pagars`. |
| `create_conta_pagar_permissions` | Permissões CRUD de `ContaPagar` para super_admin/admin/financeiro. |

## 9. Testes

`BaixaFaturaServiceTest`, `GatewayPagamentoTest`, `PagamentoWebhookTest`,
`ConciliacaoCreditoFaturaTest`, `ContaPagarTest`, `RelatorioInadimplenciaTest`,
`RelatorioFluxoCaixaTest`, `PortalFinanceiroPagamentoTest`.
