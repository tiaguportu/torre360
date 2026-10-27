# Patrimônio e Estoque Escolar (Onda 16)

> **Status: implementado** (patrimônio). Este documento descreve o escopo e as decisões de
> modelagem; detalhes de uso ficam no texto de Ajuda da tela **Bens Patrimoniais**, grupo
> **Patrimônio** do menu. O estoque de consumíveis (fase 2 do escopo
> original) foi implementado depois, em 2026-10-27: ver **Fase 2: estoque de consumíveis**, no fim do documento.

## Contexto

Módulo clássico de sistemas de gestão escolar completos (controle de bens — computadores,
mobiliário, material de laboratório — e estoque de consumíveis), não coberto pela lista de
marketing do Sponte nem pelas Ondas 1-8.

## Como foi implementado

- `BemPatrimonial` (`app/Models/BemPatrimonial.php`): descrição, número de patrimônio
  (único), categoria, data e valor de aquisição, unidade/sala de alocação, fornecedor,
  status (`StatusBemPatrimonial`: Em Uso/Em Manutenção/Baixado). Resource em
  `/admin/bem-patrimonials` (grupo **Patrimônio**).
- `MovimentacaoPatrimonio` (`app/Models/MovimentacaoPatrimonio.php`): histórico de
  transferências (unidade/sala anterior → nova) e mudanças de status (status anterior →
  novo), com quem registrou e quando. Exibido como relation manager somente leitura
  ("Movimentações") dentro do cadastro do bem — os registros só são criados pelas ações
  abaixo, não por um formulário próprio.
- **Ação "Transferir"** (`BemPatrimonial::transferir()`): move o bem para outra
  unidade/sala e grava a movimentação com o histórico de onde ele estava.
- **Ação "Mudar Status"** (`BemPatrimonial::mudarStatus()`): altera o status (ex.: marcar
  em manutenção ou dar baixa) e grava a movimentação com o status anterior/novo.
- Reaproveita `Unidade`/`Sala` (Onda 4) e `Fornecedor` (já existente) como referências —
  nenhum cadastro novo para essas entidades.
- Não é dado sensível: permissões restritas a `secretaria`/`admin`/`super_admin` (quem
  cuida do inventário físico), mesmo nível de acesso do restante da secretaria.
- Testes em `tests/Feature/BemPatrimonialTest.php` (model, as duas ações e o relation
  manager de histórico).

## Não incluído na primeira entrega (patrimônio)

- **Estoque de consumíveis** (`ItemEstoque`/`MovimentacaoEstoque` do escopo original): já
  estava marcado como "fase 2 opcional" na proposta original, por ter lógica de
  entrada/saída por quantidade, diferente da lógica de item único do patrimônio. Fica para
  quando houver demanda real por controlar consumíveis (material de limpeza, papelaria).
- Relatório de inventário por unidade/sala: a listagem já filtra por unidade e por status,
  mas uma tela de "inventário físico periódico" dedicada não foi incluída.

---

## Fase 2: estoque de consumíveis (implementada em 2026-10-27)

Menu **Suprimentos → Estoque** (`/admin/suprimentos/estoque`).

- **Tabelas:** `itens_estoque` (código, nome, categoria, unidade de medida — unidade, caixa, pacote, resma, litro, quilo, metro —, estoque mínimo, saldo atual, custo médio, conta do plano, ativo) e `movimentacoes_estoque` (tipo, quantidade, **saldo depois do movimento**, custo unitário, centro de custo, motivo, quem fez e a origem, que pode ser um recebimento de compra).
- **Movimentos** (`EstoqueService`): **entrada**, **saída** (para um setor/centro de custo) e **ajuste de inventário** (informa-se o que foi contado e o motivo; o sistema grava a diferença). Cada movimento trava a linha do item, ajusta o saldo e grava a movimentação com o saldo depois. **Saldo negativo não existe**: a saída recusa o que falta.
- **Custo médio móvel:** cada entrada com custo recalcula a média ponderada com o saldo que havia; saída e ajuste não mudam o custo unitário. O valor do estoque é saldo × custo médio.
- **Trilha:** a aba *Movimentações* do item mostra todos os movimentos, só para leitura. Item com movimentação **não se exclui** (desligue "Ativo").
- **Integração com Compras (Onda 38):** o recebimento de um item com destino "estoque" dá a entrada sozinho, com o custo da compra e a nota fiscal no motivo.
- **Minha Mesa:** fila **Estoque abaixo do mínimo** (permissão `ViewAny:ItemEstoque`): item no mínimo ou abaixo; zerado é atrasado. Mínimo zero desliga o aviso do item.
- **Permissões:** `ViewAny`, `View`, `Create`, `Update`, `Delete` e **`Movimentar:ItemEstoque`** (mexer no saldo é uma permissão à parte). Administração e secretaria cuidam; financeiro e coordenação consultam; a coordenação também pode movimentar.
- **Testes:** `tests/Feature/EstoqueTest.php` (12).

Continua fora: inventário físico periódico dedicado, lote e validade, reserva de material para um evento e múltiplos almoxarifados.
