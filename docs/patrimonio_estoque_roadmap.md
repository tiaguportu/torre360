# Patrimônio e Estoque Escolar (Onda 16) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Módulo clássico de sistemas de gestão escolar completos (controle de bens — computadores,
mobiliário, material de laboratório — e estoque de consumíveis), não coberto pela lista de
marketing do Sponte nem pelas Ondas 1-8. Confirmado: não existe hoje nenhum model,
migration ou tela relacionada a patrimônio/estoque no Torre360.

## Escopo

### Modelos novos
- `BemPatrimonial`: descrição, número de patrimônio, categoria (equipamento de
  informática, mobiliário, material pedagógico etc.), data de aquisição, valor de
  aquisição, unidade/sala onde está alocado, status (em uso/em manutenção/baixado).
- `MovimentacaoPatrimonio`: histórico de transferência entre unidades/salas ou mudança de
  status, para saber "onde está" e "o que já aconteceu" com o bem.
- Opcional, fase 2: `ItemEstoque`/`MovimentacaoEstoque` para consumíveis (material de
  limpeza, papelaria), separado do patrimônio (bens duráveis) por ter lógica de
  entrada/saída por quantidade, não por item único.

### Telas
- Cadastro de bens patrimoniais, com histórico de movimentação.
- Relatório por unidade/sala (útil para inventário físico periódico).
- Ação "Dar baixa" (bem descartado/vendido/perdido), com motivo.

## Dependências já satisfeitas

- `Unidade`/`Sala` (Onda 4) já existem como referência de onde um bem está alocado.
- `Fornecedor` (já existente) pode ser reaproveitado para registrar de quem o bem foi
  comprado, sem precisar de um cadastro de fornecedor próprio para este módulo.
