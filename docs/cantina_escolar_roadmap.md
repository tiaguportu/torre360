# Cantina Escolar com Saldo Pré-pago (Onda 17) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Módulo comum em escolas particulares brasileiras: cartão ou pulseira recarregável pelos
responsáveis, com consumo descontado na hora da compra na cantina. Reduz dinheiro em
espécie circulando com criança pequena e dá aos pais visibilidade do que o filho consome.
Não coberto pela lista de marketing do Sponte nem pelas ondas já levantadas.

## O que já existe (ponto de partida)

- `GatewayPagamento`/`Fatura` (Onda 6) já cobrem recarga via PIX/boleto/cartão — é só
  gerar uma cobrança avulsa de recarga, não reimplementar meio de pagamento.
- Não existe hoje nenhum conceito de saldo/carteira por aluno, nem produto/venda de
  cantina.

## Escopo

### Modelos novos
- `SaldoCantina`: `matricula_id`, saldo atual (decimal). Único por matrícula.
- `RecargaSaldo`: `matricula_id`, valor, forma (PIX/cartão via `GatewayPagamento`, ou
  manual registrada pela secretaria), data, `fatura_id` (quando gerada via cobrança).
- `ProdutoCantina`: nome, preço, categoria (lanche/bebida/outros), ativo (boolean).
- `VendaCantina`: `matricula_id`, itens vendidos (produto + quantidade + preço no momento
  da venda), valor total, data/hora, operador (user que registrou).

### Telas
- Cadastro de produtos da cantina (secretaria/operador da cantina).
- Tela de "frente de caixa" simplificada: busca o aluno (por nome ou código/crachá),
  mostra o saldo atual, monta a venda, debita do saldo. Pensada para uso rápido no
  balcão, não um formulário Filament tradicional — pode exigir uma página dedicada mais
  enxuta que o CRUD padrão.
- No Portal da Família: saldo atual do aluno, histórico de consumo, botão de recarga.
- Bloqueio de venda quando saldo insuficiente (sem crédito automático).

## Dependências já satisfeitas

- `GatewayPagamento`/`Fatura` (Onda 6) já resolvem a recarga via pagamento online.
- Portal da Família (Onda 2) já é o lugar natural para a família ver saldo e recarregar.

## Risco e observação

- É o módulo mais distante do resto do sistema (fluxo de "frente de caixa" rápido, não
  cadastro tradicional) — maior candidato a precisar de UI própria fora do padrão
  Filament Resource usado no restante do projeto.
