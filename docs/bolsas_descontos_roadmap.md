# Bolsas e Descontos Educacionais (Onda 14)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam nos textos de Ajuda das telas (**Tipos de Bolsa** e **Bolsas
> Concedidas**, grupo **Financeiro** do menu).

## Contexto

Módulo financeiro comum em escolas particulares brasileiras (bolsa de estudo, desconto por
convênio com empresa, desconto família/irmãos), não coberto pela lista de marketing do
Sponte nem pelas Ondas 1-8. Tem peso financeiro real — antes desta onda o desconto só
existia lançado manualmente, fatura a fatura, sem critério, aprovação ou rastreabilidade.

## O que já existia (ponto de partida)

- `ItemFatura` já tinha `tipo_desconto` (`absoluto` ou `percentual`) e `desconto`, usados
  no cálculo de `Fatura::valor()` — mas era um desconto solto por item/fatura, lançado à
  mão, sem vínculo com um motivo, um percentual recorrente ou uma aprovação.
- Não existia nenhuma entidade "Bolsa" ou "Convênio" — nada que relacionasse um
  aluno/contrato a um desconto recorrente com critério de concessão e renovação.

## Como foi implementado

- `TipoBolsa` (`app/Models/TipoBolsa.php`): nome, percentual máximo, `exige_aprovacao`
  (boolean), critério de renovação em texto livre. Resource em `/admin/tipo-bolsas`.
- `BolsaConcedida` (`app/Models/BolsaConcedida.php`): `matricula_id`, `tipo_bolsa_id`,
  percentual, vigência (`data_inicio`/`data_fim`, nullable = enquanto durar a matrícula),
  status (`StatusBolsa`: Solicitada/Aprovada/Recusada/Encerrada), aprovador, observação.
  Resource em `/admin/bolsa-concedidas`.
- **Fluxo de aprovação:** ao criar uma concessão, se o `TipoBolsa` **não** exige aprovação
  (`exige_aprovacao = false`), ela já nasce com status Aprovada
  (`CreateBolsaConcedida::mutateFormDataBeforeCreate()`). Caso contrário, nasce Solicitada
  e aparecem as ações **Aprovar** (registra `aprovado_por_user_id`) e **Recusar** (com
  motivo) na listagem. Uma bolsa aprovada tem a ação **Encerrar**, que fecha a vigência.
- `BolsaConcedida::isAtiva()`: aprovada, já começou e ainda não terminou (ou sem fim
  definido). `BolsaConcedida::percentualAtivoPara(Matricula $matricula)`: soma o
  percentual de todas as bolsas ativas da matrícula, limitado a 100.
- **Integração automática com o financeiro:** `GeracaoFaturasContratoService::gerar()`
  (Onda 7) consulta `BolsaConcedida::percentualAtivoPara()` do contrato e aplica o
  percentual em `tipo_desconto`/`desconto` de **cada** item de fatura gerado (entrada e
  parcelas) — o financeiro não precisa lançar o desconto à mão fatura a fatura. Sem bolsa
  ativa, o comportamento é idêntico ao anterior (desconto 0, absoluto).
- Permissões (`TipoBolsa`/`BolsaConcedida`) restritas a `secretaria`/`admin`/`super_admin`
  — é dado financeiro.
- Testes em `tests/Feature/BolsaConcedidaTest.php` (model + fluxo de aprovação) e
  `tests/Feature/GeracaoFaturasComBolsaTest.php` (integração com a geração de faturas).

## Não incluído nesta entrega

- Relatório dedicado de bolsas concedidas (quantidade, percentual médio, impacto no valor
  total faturado) — a listagem já permite filtrar por status, mas um relatório/dashboard
  próprio fica para um incremento futuro, se a direção sentir falta.
- Validação automática do critério de renovação (frequência mínima, nota mínima etc.) — o
  campo existe como texto livre informativo; não há checagem automática que suspenda uma
  bolsa quando o critério deixa de ser cumprido.
