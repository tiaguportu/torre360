# Contas Recorrentes e Fluxo de Caixa Projetado (Onda 78) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade.

## Contexto

O **Fluxo de Caixa** mostra o que já aconteceu nos últimos 12 meses. A direção precisa do **que vem pela frente**: com o que
já está faturado, o que já está a pagar e as despesas que se repetem todo mês, o caixa fecha os próximos 90 dias?
Hoje cada conta a pagar é lançada uma a uma, inclusive aluguel, internet e a folha, que não mudam de mês para mês.

## O que já existe

- `ContaPagar` (descrição, valor, vencimento, status, fornecedor, plano de contas, centro de custo) e o comando
  `financeiro:atualizar-contas-pagar-atrasadas`.
- `Fatura` com vencimento e saldo, e o **risco de inadimplência** proposto (Onda 53) para ponderar o que deve entrar.
- `RelatorioFluxoCaixa` (passado) e o **Orçamento Anual × Realizado** proposto (Onda 43).
- O agendador (`routes/console.php`) e a fila da Mesa (Onda 71).

## Escopo proposto

1. **Conta a pagar recorrente**: modelo com periodicidade (mensal, bimestral, anual), dia de vencimento, valor fixo ou
   estimado, vigência e conta/centro de custo. Uma rotina gera a próxima conta com antecedência de N dias; o valor
   estimado é revisado na hora de pagar.
2. **Reajuste do contrato**: data do reajuste do aluguel e dos contratos de serviço com aviso na Mesa.
3. **Fluxo projetado de 90 dias**: saldo inicial + faturas a receber (ponderadas pela taxa de recebimento do mesmo mês do
   ano anterior, ou pelo risco da Onda 53) − contas a pagar e recorrentes. Mostra a primeira data em que o saldo fica negativo.
4. **Cenários** (receber tudo, receber a média histórica, receber só o que está pago) e fonte na Onda 72.
5. **Fila da Mesa**: recorrente sem valor revisado para o mês; semana com saldo projetado negativo.

## Base legal

Sem obrigação legal: é gestão financeira.

## Dependências e riscos

- A projeção só vale se o banco estiver conciliado: saldo inicial errado estraga tudo. A conciliação bancária
  (`ConciliacaoBancariaService`) precisa estar em dia, e a tela mostra a data da última conciliação.
- Recorrente que vira conta duplicada (alguém lança à mão) é o risco prático: a rotina confere se já existe conta do mesmo
  modelo no mês.
- **Fusão sugerida com a Onda 43:** o orçamento é a meta, a projeção é o que vai acontecer e o realizado é o que aconteceu;
  uma tela com os três faz mais sentido do que duas.
- Ordem: 53 melhora a projeção; a Onda 68 (gateway real) melhora o saldo inicial por baixa automática.
