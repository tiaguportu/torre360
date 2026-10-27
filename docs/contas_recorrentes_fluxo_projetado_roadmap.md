# Contas Recorrentes e Fluxo de Caixa Projetado (Onda 78)

> **Status: implementada** (2026-10-27). As seções *Contexto*, *O que já existe* e *Escopo* abaixo são o planejamento original;
> o que foi entregue, com as decisões e o que ficou de fora, está em **Como foi implementado**, no fim do documento.

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

---

## Como foi implementado (2026-10-27)

Menu **Financeiro → Contas Recorrentes** (`/admin/financeiro/contas-recorrentes`) e **Financeiro → Fluxo Projetado** (`/admin/financeiro/fluxo-projetado`).

### Contas recorrentes

- **Cadastro** (`contas_recorrentes`): descrição, fornecedor, conta do plano, centro de custo, periodicidade (mensal, bimestral, trimestral, semestral ou anual), dia do vencimento (dia 31 em mês curto vence no último dia), valor, **valor estimado** (varia a cada mês), vigência, antecedência da geração (dias) e **data do próximo reajuste**.
- **Rotina diária** `financeiro:gerar-contas-recorrentes` (06:45) e botão **Gerar contas agora**: cria a conta a pagar de cada vencimento que entrou na janela de antecedência. Também **recupera os vencimentos dos últimos 31 dias** que uma parada do agendador deixou para trás (já vencidos, nascem atrasados); um cadastro novo com vigência antiga não gera meses passados.
- **Nunca duplica:** a *competência* (mês do vencimento) é única por despesa, **no banco** (índice único) e na rotina. Rodar de novo não repete a conta.
- **Valor estimado:** a conta nasce **a revisar** (selo e filtro na lista de Contas a Pagar). A ação **Revisar valor** confirma o valor da fatura (e pode levá-lo ao cadastro como novo valor estimado). **A baixa fica travada até a revisão.**
- **Reajuste do contrato:** a ação **Registrar reajuste** muda o valor do cadastro, marca o próximo reajuste (em um ano, se não informado; a data precisa ser futura) e atualiza as contas **abertas, de hoje em diante, que ainda estão no valor antigo**. A conta que alguém editou à mão fica como está.
- **Contas a Pagar** ganhou `conta_recorrente_id`, `competencia` e `valor_revisado`.

### Fluxo projetado

Projeção de 30, 60, 90 ou 180 dias, **dia a dia**, agrupada por semana:

- **Saldo de hoje** = soma das transações bancárias lançadas (entradas − saídas) + `FINANCEIRO_SALDO_BASE` (para quando o primeiro extrato importado não começa do zero).
- **Recebimentos:** o saldo restante de cada fatura aberta, no dia do vencimento; as já vencidas entram `dias_recebimento_atrasadas` (7) dias depois de hoje.
- **Pagamentos:** contas a pagar abertas (as vencidas saem no primeiro dia) **mais os vencimentos de despesas recorrentes que ainda não viraram conta**.
- **Três cenários:** *Média histórica* (o que vence é multiplicado pela taxa de recebimento dos últimos 6 meses completos; sem histórico, a taxa padrão de 85%; as vencidas entram com metade da taxa), *Receber tudo* e *Não receber nenhuma fatura aberta*.
- A tela mostra a **primeira data em que o saldo fica negativo**, o menor saldo e **a situação da conciliação bancária** (data do último lançamento e transações sem conciliar): o saldo de hoje só é confiável com o banco em dia.
- É **estimativa** e a tela diz isso, junto com a taxa usada e sua origem (histórico ou padrão).

### Minha Mesa e relatórios

Filas **Contas recorrentes a revisar** (`Update:ContaPagar`; vencida e sem revisão é atrasada), **Reajustes de contrato** (`Update:ContaRecorrente`; 30 dias de aviso, atrasado depois da data) e **Caixa projetado negativo** (`View:FluxoProjetado`; uma linha só, atrasada quando a data cai em até 14 dias). Fonte **Fluxo de caixa projetado** nos relatórios configuráveis (Onda 72), por cenário e horizonte.

### Parâmetros (`config/financeiro.php`)

`recorrentes.dias_retroativos` (31), `aviso_reajuste_dias` (30), `revisao_valor_dias` (7); `projecao.saldo_base` (`FINANCEIRO_SALDO_BASE`, 0), `taxa_padrao` (0,85), `meses_historico` (6), `fator_atrasadas` (0,5), `dias_recebimento_atrasadas` (7), `dias` (90), `alerta_dias` (30) e `urgente_dias` (14).

### Permissões

`ViewAny`, `View`, `Create`, `Update` e `Delete:ContaRecorrente` e `View:FluxoProjetado` (administração e financeiro).

### Testes

`tests/Feature/ContasRecorrentesFluxoTest.php` (24: vencimentos, geração, idempotência, estimado, reajuste, os três cenários, saldo base e conciliação) e `tests/Feature/ContasRecorrentesTelasTest.php` (16: cadastro, geração, reajuste, revisão e baixa travada, página do fluxo, filas e fonte de relatório).

### Fora desta entrega

Risco de inadimplência por aluno ponderando as faturas (Onda 53, continua proposta: a taxa é do mês e não do aluno), integração com o saldo real do gateway (Onda 68) e a visão única orçado × projetado × realizado (hoje são três telas ligadas por links).
