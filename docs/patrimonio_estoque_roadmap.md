# Patrimônio e Estoque Escolar (Onda 16)

> **Status: implementado** (patrimônio). Este documento descreve o escopo e as decisões de
> modelagem; detalhes de uso ficam no texto de Ajuda da tela **Bens Patrimoniais**, grupo
> **Patrimônio** do menu. O estoque de consumíveis (fase 2 do escopo original) **não foi
> implementado** nesta entrega.

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

## Não incluído nesta entrega

- **Estoque de consumíveis** (`ItemEstoque`/`MovimentacaoEstoque` do escopo original): já
  estava marcado como "fase 2 opcional" na proposta original, por ter lógica de
  entrada/saída por quantidade, diferente da lógica de item único do patrimônio. Fica para
  quando houver demanda real por controlar consumíveis (material de limpeza, papelaria).
- Relatório de inventário por unidade/sala: a listagem já filtra por unidade e por status,
  mas uma tela de "inventário físico periódico" dedicada não foi incluída.
