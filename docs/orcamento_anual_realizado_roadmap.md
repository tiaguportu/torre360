# Orçamento Anual × Realizado (Onda 43) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

A direção define no fim do ano quanto pretende receber e gastar em cada conta no ano seguinte e
precisa acompanhar o desvio mês a mês. Hoje o sistema mostra o que **aconteceu** (DRE, fluxo de
caixa), mas não o que **estava previsto**, então não alerta quando uma conta estoura.

## O que já existe (ponto de partida)

- `PlanoConta` e `CentroCusto`: classificação das receitas e despesas.
- `ContaPagar`, `Fatura` e `TransacaoBancaria`: o realizado.
- `DREService` e as páginas **Relatório DRE** e **Fluxo de Caixa**: agregações por período.
- `PlanilhaLeiMensalidade`: custos do ano usados para fixar a anuidade (fonte de premissas).
- Não existe orçamento (previsto) por conta, centro de custo e mês.

## Escopo

### Modelos novos
- `Orcamento`: ano, nome, versão, status (rascunho, aprovado, revisado), aprovado por.
- `OrcamentoLinha`: `plano_conta_id`, `centro_custo_id` (opcional), mês, valor previsto.

### Telas
- Planilha de orçamento (grade conta × 12 meses) com importação CSV e cópia do ano anterior com
  percentual de reajuste.
- **Orçado × Realizado**: por conta/centro e período, com variação em valor e %, e semáforo.
- Alertas ao gestor quando uma conta passa de X% do previsto no acumulado.
- Revisão (reprevisão) com histórico: o orçamento original é preservado.

### Regras
- Realizado vem do próprio sistema (contas a pagar pagas ou por competência, faturas baixadas),
  com a mesma regra do DRE para não haver duas verdades.
- Orçamento aprovado só muda por nova versão.

## Dependências já satisfeitas

- Plano de contas, centros de custo e DRE.

## Risco e observação

- Qualidade da classificação: se contas a pagar não têm conta contábil/centro de custo preenchidos,
  o realizado fica incompleto. Tornar esses campos obrigatórios antes de lançar o orçamento.
- Integra com Compras (Onda 38): saldo orçamentário na aprovação da requisição.
