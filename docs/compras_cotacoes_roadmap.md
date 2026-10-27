# Compras e Cotações (Onda 38)

> **Status: implementada** (2026-10-27). As seções *Contexto*, *O que já existe* e *Escopo* abaixo são o planejamento original;
> o que foi entregue, com as decisões e o que ficou de fora, está em **Como foi implementado**, no fim do documento.

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

---

## Como foi implementado (2026-10-27)

Menu **Suprimentos → Compras** (`/admin/suprimentos/compras`).

### O caminho de uma compra

`Rascunho → Em cotação → Aguardando aprovação → Aprovada → Recebida em parte → Recebida` (e `Recusada` ou `Cancelada`).

1. **Requisição** (qualquer pessoa da equipe com `Create:RequisicaoCompra`): título, setor (centro de custo), conta do plano de despesa, urgência, justificativa e itens (descrição, quantidade, unidade, estimativa por unidade e **destino**: consumo direto, entra no estoque, bem durável ou serviço). Nasce como rascunho e só o solicitante a edita; **Enviar para cotação** trava a requisição.
2. **Cotação** (`Cotar:RequisicaoCompra`): uma proposta por fornecedor, com preço de **todos** os itens (cotação incompleta não compara), frete, prazo de entrega, prazo de pagamento, validade e condições. O fornecedor que já tinha proposta é atualizado. O **mapa comparativo** põe os fornecedores lado a lado item a item, marca o menor preço de cada item e o menor total (com frete), mostra a validade e **sinaliza o fornecedor com recebimento divergente** nos últimos 12 meses.
3. **Escolha da cotação**: abre o pedido. Duas travas pedem justificativa escrita: escolher uma cotação que **não é a de menor valor total** e fechar com **menos cotações que o mínimo da faixa de valor**. Cotação vencida não pode ser escolhida.
4. **Aprovação por alçada** (`AprovarNivel1/2/3:PedidoCompra`): o valor do pedido define o nível exigido; quem tem a alçada de um nível aprova também os de baixo. A tela de aprovação mostra o **saldo do orçamento** da conta (Onda 43): previsto, realizado, comprometido por pedidos já aprovados e saldo. Compra acima do saldo exige justificativa de quem aprova (ou é bloqueada, se `COMPRAS_BLOQUEAR_ACIMA_DO_ORCAMENTO=true`). A recusa pede motivo e pode devolver a requisição para nova cotação.
5. **Recebimento** (`Receber:PedidoCompra`), total ou parcial: quantidade recebida por item, número da nota fiscal (obrigatório), arquivo da nota (PDF, JPG ou PNG até 10 MB, em disco privado) e marca de **divergência** (obriga a descrever). No mesmo ato: **item de estoque dá entrada** no saldo com o custo da compra (custo médio), **bem durável é cadastrado no patrimônio** (um bem por unidade, número `PC-AAAA-NNNN-<item>-NN`) e nasce a **conta a pagar** com o vencimento da data do recebimento mais o prazo de pagamento da cotação, o centro de custo e a conta do plano da requisição. O frete da cotação entra na primeira entrega.
6. **Cancelar**: o solicitante ou quem cota, até antes da primeira entrega; pede motivo.

### Regras de controle interno

- **Quem solicita não aprova** e **quem aprova não recebe** (`COMPRAS_SEGREGAR_FUNCOES`, ligado por padrão). A regra vale também para o super administrador: o `Gate::before` libera qualquer habilidade da policy, então as telas conferem o estado e o serviço recusa o que a regra não permite.
- O **saldo comprometido** (pedidos aprovados e ainda não recebidos por inteiro, na mesma conta do plano) entra no saldo do orçamento, para duas compras seguidas não gastarem o mesmo dinheiro.
- Toda mudança de situação grava o registro de atividade (`activity('compras')`).

### Quem vê o quê

Cada pessoa abre as **próprias** requisições e as que **precisam dela**: quem cota vê tudo que saiu do rascunho; quem aprova vê os pedidos da sua alçada; quem recebe vê as compras aprovadas. `VerTodas:RequisicaoCompra` abre as dos outros setores. A **nota fiscal** só abre para quem pode abrir a requisição (`/compras/recebimentos/{recebimento}/nota-fiscal`; o tipo é o detectado no arquivo e só PDF e imagem abrem no navegador).

### Parâmetros (`config/compras.php`)

| Parâmetro | Padrão | Variável de ambiente |
|---|---|---|
| Alçada nível 1 (Coordenação) até | R$ 1.000 | `COMPRAS_ALCADA_1_ATE` |
| Alçada nível 2 (Direção) até | R$ 10.000 | `COMPRAS_ALCADA_2_ATE` |
| Nível 3 (Mantenedora) | acima do nível 2 | — |
| Cotações mínimas: 1 até | R$ 500 | `COMPRAS_COTACAO_1_ATE` |
| Cotações mínimas: 2 até (acima, 3) | R$ 5.000 | `COMPRAS_COTACAO_2_ATE` |
| Bloquear compra acima do orçamento | não (pede justificativa) | `COMPRAS_BLOQUEAR_ACIMA_DO_ORCAMENTO` |
| Segregação de funções | sim | `COMPRAS_SEGREGAR_FUNCOES` |
| Histórico do fornecedor (meses) | 12 | `COMPRAS_MESES_HISTORICO_FORNECEDOR` |
| Fila *Compras para cotar*: atrasada após (dias) | 3 | `COMPRAS_MESA_COTACAO_DIAS` |
| Fila *Pedidos para aprovar*: atrasada após (dias) | 2 | `COMPRAS_MESA_APROVACAO_DIAS` |
| Fila *Compras para receber*: sem prazo na cotação, atrasada após (dias) | 15 | `COMPRAS_MESA_ENTREGA_DIAS` |

### Minha Mesa

`Compras para cotar` (permissão `Cotar:RequisicaoCompra`; a urgente já nasce atrasada), `Pedidos para aprovar` (só o que a pessoa pode aprovar: nível dentro da alçada dela e, com a segregação ligada, sem as que ela mesma solicitou) e `Compras para receber` (atrasada quando a entrega prometida na cotação passou; quem aprovou o pedido não o vê).

### Permissões (migration `create_suprimentos_permissions`)

`ViewAny`, `View`, `Create` e `Update:RequisicaoCompra` (administração, secretaria, financeiro, coordenação e professor), `VerTodas:RequisicaoCompra` (administração, financeiro), `Cotar:RequisicaoCompra` (administração, secretaria, financeiro), `Receber:PedidoCompra` (administração, secretaria), `AprovarNivel1:PedidoCompra` (administração, coordenação), `AprovarNivel2:PedidoCompra` (administração) e `AprovarNivel3:PedidoCompra` (super administrador). Papel criado à mão precisa recebê-las em **Perfis de acesso**.

### Testes

`tests/Feature/ComprasServiceTest.php` (26: requisição, cotação, mapa, escolha, alçada, orçamento, segregação, recebimento com estoque, patrimônio e conta a pagar) e `tests/Feature/ComprasTelasTest.php` (22: criar e editar, botões por passo e perfil, quem enxerga o quê, nota fiscal e as três filas).

### Fora desta entrega

Envio do pedido ao fornecedor por e-mail ou WhatsApp, leitura do XML da NF-e, portal do fornecedor, devolução ou estorno de um recebimento já registrado, requisição criada automaticamente a partir de uma ordem de serviço (Onda 39) ou do estoque abaixo do mínimo (a fila da Mesa só avisa; quem repõe abre a requisição).
