# Loja Escolar Opcional: Uniformes e Livros (Onda 67) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda.

## Contexto

Muitas escolas vendem uniforme, agenda, apostila e livro. Hoje isso acontece fora do sistema: caderno de
anotações, planilha, recibo à mão, e a venda não aparece na ficha financeira da família. Dois cuidados
legais mudam o desenho:

- **Venda casada (CDC, art. 39, I):** a escola pode vender, mas **não pode obrigar** a família a comprar dela
  nem de fornecedor indicado. Pode definir o padrão do uniforme; a compra tem que poder ser feita em outro
  lugar. A lista de material (Onda 59) já nasceu sem campo de marca ou loja por esse motivo.
- **Compra pelo Portal é compra à distância:** vale o **arrependimento em 7 dias** (CDC, art. 49) e as regras
  de comércio eletrônico do **Decreto nº 7.962/2013** (informações claras, resumo do pedido, canal de
  atendimento).
- **Fiscal:** vender mercadoria não é o mesmo que prestar serviço (ICMS e nota de mercadoria, não ISS). Confirme
  com a contabilidade antes de vender.

## O que já existe

- `Fatura` e `ItemFatura` (cobrança avulsa) e o `GatewayPagamento` (PIX, boleto e link): dá para cobrar um
  pedido sem inventar meio de pagamento.
- O Portal da Família com seleção de aluno, a Lista de Material (Onda 59) e, desde a Onda 62, o cálculo de
  multa e juros de qualquer fatura em atraso.
- A **Onda 17 (cantina com saldo pré-pago)**, ainda proposta, também prevê produtos e vendas por matrícula. A
  base é a mesma e **deve ser feita uma vez só**: cadastro de produto, venda por matrícula e baixa de estoque.
  A cantina acrescenta o saldo e o balcão rápido; a loja, a variação de tamanho, o pedido pelo Portal e a
  fatura.
- **Falta:** produto, variação (tamanho), estoque, pedido, entrega e devolução.

## Escopo

### Modelos novos
- `produtos_loja`: nome, categoria (uniforme, livro e apostila, outros), descrição, foto, preço, ativo.
- `variacoes_produto`: tamanho ou código, estoque e estoque mínimo.
- `pedidos_loja`: matrícula, solicitante, situação (aberto, pago, separado, entregue, cancelado, devolvido),
  `fatura_id`, forma de entrega (retirada na secretaria) e datas.
- `itens_pedido_loja`: produto, variação, quantidade e **preço no momento da compra**.

### Telas
- **Secretaria:** cadastro de produtos e estoque; fila de pedidos para separar e entregar (com assinatura ou
  confirmação de quem retirou); devolução e troca dentro do prazo; relatório de vendas e de estoque abaixo do
  mínimo.
- **Portal da Família:** catálogo com tabela de medidas; carrinho; resumo do pedido antes de confirmar;
  pagamento por PIX ou boleto; histórico e o botão de **arrependimento** dentro de 7 dias.
- Em todas as telas da loja, o aviso fixo **"compra opcional: você pode adquirir em qualquer outro lugar"**.

### Regras
- O pedido gera uma **fatura própria** (nunca soma na mensalidade): a família que atrasa a mensalidade não
  fica com o uniforme preso, e o contrário também vale. A fatura do pedido segue as mesmas regras de encargos
  por atraso (Onda 62).
- **Nenhum ato escolar depende da compra:** matrícula, rematrícula, nota e documento não consultam a loja.
- Estoque reservado no pedido, baixado na entrega e devolvido no cancelamento e na devolução.
- Preço do item não muda em pedido já feito.

## Dependências

- Onda 17 (base de produtos e vendas) e Onda 62 (encargos). Sem a 17, esta onda cria a base e a cantina a
  reaproveita depois.

## Riscos e observações

- **Fiscal:** nota fiscal de mercadoria, tributação e o enquadramento da escola. Sem a contabilidade validar,
  a loja não deve ir para produção.
- Estoque físico não bate com o do sistema sem contagem periódica: prever o ajuste de inventário com motivo.
- Uniforme personalizado (nome do aluno) tem política de devolução própria: o catálogo marca o produto como
  "sob encomenda" e o Portal mostra a regra antes do pagamento.

## Esforço

Médio a grande (o pedido pelo Portal com pagamento, estoque por tamanho e devolução são o grosso).

## Fusão sugerida

Veja a fusão com a [Onda 17](cantina_escolar_roadmap.md): cantina e loja compartilham produto, estoque e caixa. A cobrança em fatura do contrato é o que a loja acrescenta.

Veja o [índice das ondas 68 a 71](ondas_68_a_71_indice.md#fusões-sugeridas-para-dar-completude).
