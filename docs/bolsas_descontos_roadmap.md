# Bolsas e Descontos Educacionais (Onda 14) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Módulo financeiro comum em escolas particulares brasileiras (bolsa de estudo, desconto por
convênio com empresa, desconto família/irmãos), não coberto pela lista de marketing do
Sponte nem pelas Ondas 1-8. Tem peso financeiro real — hoje o desconto só existe lançado
manualmente, fatura a fatura, sem critério, aprovação ou rastreabilidade.

## O que já existe (ponto de partida)

- `ItemFatura` já tem `tipo_desconto` (`absoluto` ou `percentual`) e `desconto`, usados no
  cálculo de `Fatura::valor()` — mas é um desconto solto por item/fatura, lançado à mão,
  sem vínculo com um motivo, um percentual recorrente ou uma aprovação.
- Não existe nenhuma entidade "Bolsa" ou "Convênio" — nada que relacione um aluno/contrato
  a um desconto recorrente com critério de concessão e renovação.

## Escopo

### Modelos novos
- `TipoBolsa` (ou `TipoConvenio`): nome (ex.: "Bolsa Filantrópica", "Convênio Empresa X",
  "Desconto Irmãos"), percentual máximo, exige aprovação (boolean), critério de renovação
  em texto livre (frequência mínima, nota mínima etc. — texto descritivo, não uma regra
  automática nesta primeira versão).
- `BolsaConcedida`: `matricula_id` (ou `contrato_id`), `tipo_bolsa_id`, percentual
  concedido, data de início, data de fim (nullable — null = enquanto durar a matrícula),
  status (solicitada/aprovada/recusada/encerrada), aprovado_por_user_id, observação.

### Integração com o financeiro já existente
- Ao gerar as faturas do contrato (`GeracaoFaturasContratoService`, Onda 7), aplicar
  automaticamente o percentual de qualquer `BolsaConcedida` ativa no período, em vez de o
  financeiro lançar o desconto à mão em cada fatura gerada.
- Relatório de bolsas concedidas (quantidade, percentual médio, impacto no valor total
  faturado) — útil para a direção acompanhar o custo da política de bolsas.

### Telas
- Cadastro de `TipoBolsa` (tabela auxiliar simples).
- Resource de `BolsaConcedida` com fluxo de aprovação (solicitar → aprovar/recusar).

## Dependências já satisfeitas

- `ItemFatura.tipo_desconto`/`desconto` já resolvem o cálculo do valor com desconto — a
  bolsa só precisa gerar esses itens automaticamente, não reimplementar o cálculo.
- `GeracaoFaturasContratoService` (Onda 7) já é o ponto certo para plugar a aplicação
  automática do desconto na geração de cada fatura.
