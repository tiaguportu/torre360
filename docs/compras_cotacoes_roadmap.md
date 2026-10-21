# Compras e Cotações (Onda 38) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Material de escritório, limpeza, manutenção e equipamentos são comprados por solicitação das
áreas, cotação com fornecedores, aprovação do gestor e pagamento. Hoje isso corre por e-mail e
planilha; a conta a pagar nasce só no fim, sem ligação com o pedido que a originou.

## O que já existe (ponto de partida)

- `Fornecedor`, `ContaPagar`, `CentroCusto` e `PlanoConta`: o fim do fluxo (pagamento e classificação).
- `BemPatrimonial` e `MovimentacaoPatrimonio` (Onda 16): destino de compras de ativo.
- `OrdemServico` e `PlanoManutencao` (Onda 39): origem natural de compras de peças e serviços.
- `docs/patrimonio_estoque_roadmap.md`: o estoque de consumíveis não foi feito e é a origem de
  reposição.
- Não existem requisição, cotação, aprovação por alçada, pedido ou recebimento.

## Escopo

### Modelos novos
- `RequisicaoCompra`: solicitante, setor/centro de custo, itens (descrição, quantidade, estimativa),
  justificativa, urgência, status.
- `Cotacao` e `CotacaoItem`: fornecedor, preços por item, prazo e condições; mínimo de N cotações
  configurável por faixa de valor.
- `PedidoCompra`: cotação vencedora, valor, aprovações (alçadas), status (aprovado, enviado,
  recebido parcial, recebido, cancelado).
- `Recebimento`: itens recebidos, nota fiscal (número, anexo), divergências.

### Telas
- Requisição pelo setor; mapa comparativo de cotações (menor preço, prazo, histórico do fornecedor).
- Aprovação por alçada (valor × papel) com trilha.
- Recebimento e conferência com a nota; geração da `ContaPagar` com vencimento e centro de custo.

### Regras
- Quem solicita não aprova a própria compra; quem aprova não recebe.
- Compra de bem durável gera cadastro no patrimônio no recebimento.
- Fornecedor com pendência (nota divergente) fica sinalizado na próxima cotação.

## Dependências já satisfeitas

- Cadastro de fornecedor, contas a pagar e patrimônio.

## Risco e observação

- Controle interno e segregação de funções são o valor da onda: sem alçadas configuráveis ela vira
  só mais um formulário.
- O orçamento anual (Onda 43) deve alimentar o saldo disponível durante a aprovação.
