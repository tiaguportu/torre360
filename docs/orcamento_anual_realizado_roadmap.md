# Orçamento Anual × Realizado (Onda 43)

> **Status: implementada** (2026-10-27). As seções *Contexto*, *O que já existe* e *Escopo* abaixo são o planejamento original;
> o que foi entregue, com as decisões e o que ficou de fora, está em **Como foi implementado**, no fim do documento.

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

---

## Como foi implementado (2026-10-27)

Menu **Financeiro → Orçamento Anual** (`/admin/financeiro/orcamentos`) e **Financeiro → Orçado × Realizado**.

- **Tabelas:** `orcamentos` (ano, nome, versão, status, orçamento de origem) e `orcamento_linhas` (conta do plano, centro de custo opcional, mês, valor previsto).
- **Situações:** `Rascunho → Aprovado → Substituído por revisão`. O orçamento aprovado **não muda**: o ajuste abre uma **revisão** (cópia em rascunho) e, quando a revisão é aprovada, a anterior fica como substituída. Um ano pode ter várias versões; a vigente é a última aprovada.
- **Planilha** (conta × 12 meses): preenchida célula a célula ou **importada por CSV** (código da conta do plano, centro de custo opcional, janeiro a dezembro; aceita vírgula ou ponto e milhar com ponto). Linha com problema volta no relatório da importação sem derrubar o resto. **Criar a partir de outro orçamento** copia as linhas com um percentual de reajuste.
- **Orçado × Realizado:** o previsto **acumulado até o mês escolhido** contra o realizado, por conta do plano, com variação em valor e %, e semáforo. O realizado é o do **DRE**: soma do valor das transações bancárias classificadas na conta e nas subcontas, a mesma regra, para não haver duas verdades. A tela avisa quantas saídas do período ainda estão **sem conta do plano** (ficaram fora).
- **Semáforo** (`ORCAMENTO_ALERTA_PERCENTUAL`, padrão 90): despesa em 90% do previsto acumulado fica em atenção; acima de 100% está estourada. Na receita é o inverso: abaixo de 100% é atenção e abaixo do percentual de alerta é estouro.
- **Saldo disponível para outras telas** (`OrcamentoService::saldoDisponivel`): é o que a aprovação de uma compra (Onda 38) consulta, já descontado o comprometido por pedidos aprovados.
- **Minha Mesa:** fila **Orçamento estourado** (permissão `View:OrcadoRealizado`), com as despesas que chegaram perto do previsto (atenção) ou passaram dele (estouro) no acumulado do ano até o mês corrente; só o estouro é atrasado. O resultado fica em cache por alguns minutos.
- **Permissões:** `ViewAny`, `View`, `Create`, `Update`, `Delete` e `Aprovar:Orcamento` e `View:OrcadoRealizado` (administração e financeiro; a aprovação, só a administração; o Orçado × Realizado também para a coordenação).
- **Testes:** `tests/Feature/OrcamentoTest.php` (20).

### Fora desta entrega

Orçamento por centro de custo com limites que bloqueiem o lançamento (o bloqueio é só na aprovação de compras e é opcional), orçamento de receita por turma, e realizado por **competência** das contas a pagar (hoje é por caixa, como o DRE).
