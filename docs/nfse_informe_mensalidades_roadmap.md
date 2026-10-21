# NFS-e e Informe Anual de Mensalidades (Onda 42)

> **Status: parcialmente implementado.** O **informe anual** foi entregue em 2026-10-21, junto com
> o reajuste da Onda 44: veja [reajuste_e_informe_anual_roadmap.md](reajuste_e_informe_anual_roadmap.md).
> A **NFS-e** continua **proposta**: não há código, migration ou teste dela ainda.

## Contexto

Duas obrigações recorrentes da secretaria financeira que hoje são feitas fora do sistema:

1. **Nota fiscal de serviço (NFS-e)** das mensalidades, quando exigida pelo município e pela
   tributação da escola.
2. **Informe anual de pagamentos** que as famílias usam na declaração de imposto de renda
   (dedução de despesas com instrução, Lei nº 9.250/1995, art. 8º, II, b — conferir limites e
   exigências do ano com a contabilidade).

## O que já existe (ponto de partida)

- `Fatura`, `ItemFatura`, `BaixaFaturaService` e `PagamentoConfirmacaoService`: o que foi cobrado e
  pago, por competência.
- `ResponsavelFinanceiro`: quem paga e seu CPF/CNPJ.
- `TributacaoCurso`: regime e alíquotas por curso (base para a tributação da nota).
- `Contrato` e `GatewayPagamentoManager`: vínculo com o contrato e conciliação.
- `DREService`: visão contábil de receita.
- Não existe emissão de NFS-e. O informe anual já existe (`InformeAnualMensalidadesService`).

## Escopo

### Informe anual — implementado
- Entregue: pagamentos recebidos no ano por aluno, mês a mês, parte estimada de instrução,
  responsáveis com participação, PDF individual e em lote e página **Informe Anual** no Portal.
- Ficou para depois: envio do informe por e-mail em lote e categoria explícita (dedutível ou não) no
  item da fatura.

### NFS-e (depende de integração)
- `NotaFiscalServico`: fatura de origem, número, série, chave/protocolo, status (pendente,
  emitida, cancelada, rejeitada), XML/PDF.
- Emissão por provedor configurável (`NfseProviderInterface`): municípios usam provedores próprios
  ou o padrão nacional da NFS-e; a escolha e as credenciais dependem do município e da contabilidade.
- Emissão na baixa da fatura (ou em lote por competência), reprocessamento de rejeições e envio da
  nota ao responsável.

### Regras
- Nota só para fatura **paga** (ou conforme o regime da escola: competência × caixa).
- Cancelamento exige justificativa e respeita o prazo do município.

## Dependências já satisfeitas

- Faturamento, baixa e portal financeiro.

## Risco e observação

- **Alto:** regras fiscais mudam por município e ao longo do tempo (inclusive a transição para o
  padrão nacional). Antes de codificar, fechar com a contabilidade o regime, o provedor e o que é
  dedutível; não fixar essas regras no código.
- O informe pode ser entregue antes da NFS-e: não depende dela.
