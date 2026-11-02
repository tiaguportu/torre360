# Fechamento do Mês Financeiro (Onda 105) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).
> Registra a proposta que o [índice das ondas 80 a 91](ondas_80_a_88_indice.md) deixou "só em conversa", e é onde cai a **trava de período** que a Onda 73 listou como ampliação.

## Contexto

Todo mês o financeiro faz, de cabeça, a mesma conferência: os extratos foram conciliados? sobrou entrada ou saída sem conta do plano? as contas recorrentes saíram? há conta vencida sem baixa? a hora-aula foi
apurada? Depois exporta para o escritório. O sistema tem as pontas (conciliação, classificação, recorrentes, DRE, exportação), mas **não tem a noção de "mês fechado"**: nada impede que uma baixa de
fatura mude um número já entregue ao contador.

## O que já existe (conferido no código)

- `ConciliacaoBancariaService`, `TransacaoBancaria` e `BaixaFaturaService` (conciliação e baixa).
- **Exportação contábil** (Onda 73): `ExportacaoContabilService`, `LoteContabil`, `ParametroContabil`, a página *Contabilidade* e a fila **Classificação contábil pendente** da Minha Mesa (com o dia limite
  `MESA_CONTABILIDADE_DIA_LIMITE`, padrão 10).
- `ContaRecorrente` e a fila *Recorrentes para revisar* (Onda 78), `ContaPagar` com atualização de atrasadas, `DREService`, *Orçado × Realizado* (Onda 43), *Fluxo Projetado* e *Resultado Realizado*.
- `ApuracaoHoraAula` e o ponto (competência de RH, ver a [Onda 97](exportacao_folha_pagamento_roadmap.md)).
- **Não existe período fechado.** Busca por fechamento mensal, período travado e data de fechamento em `app` e `database/migrations` sem resultado. (`FechamentoCicloLetivo` é o fechamento do **ano letivo**, outra coisa.)

## Escopo proposto

### O fechamento
- `FechamentoMensal`: competência (por unidade), situação (aberto, em conferência, fechado, reaberto), quem fechou e quando, **foto dos totais** (receita, despesa, saldo e DRE do mês) e justificativa
  de reabertura.

### Lista de conferência calculada pelo sistema
Cada item com contagem, valor, link para resolver e responsável, e **bloqueante** ou **aviso**:

1. Extratos do mês conciliados (movimento sem conciliação).
2. Entradas e saídas **sem conta do plano** (a Onda 73 já conta).
3. Contas a pagar vencidas sem baixa e contas geradas de despesa estimada ainda **a revisar**.
4. Faturas pagas sem baixa e baixas sem fatura correspondente.
5. Contas recorrentes do mês geradas.
6. Apuração de hora-aula e ponto do mês aprovados.
7. DRE do mês gerado e sem variação inexplicada contra o orçamento acima do limite.
8. Exportação contábil gerada e **enviada ao escritório** (com registro de quem e quando).

### O efeito do fechamento
- **Trava:** com o mês fechado, baixa, edição de conta paga, ajuste de fatura quitada e alteração de lançamento contábil daquela competência são recusados, **exceto** para quem tem a permissão de
  reabertura (que registra o motivo). Uma baixa que chega por webhook depois do fechamento é lançada no **mês aberto** com marca de "pagamento de competência anterior", e não reabre o mês.
- Fila **Fechamento do mês** na Minha Mesa a partir do dia limite, para o financeiro e a direção, com o que falta.
- Histórico dos fechamentos e das reaberturas.

## Base legal

Controle interno e escrituração contábil (Código Civil, art. 1.179; confirmar o enquadramento da mantenedora com a contabilidade): o fechamento é a evidência de que o mês entregue ao contador não muda depois. A escrituração em si segue com o escritório.

## Dependências e riscos

- **Data de caixa × competência:** a trava precisa de uma regra clara de qual data conta (pagamento, vencimento ou competência), senão trava o que não devia. Definir com a contabilidade.
- O fluxo de pagamento por gateway (Onda 68) e a conciliação automática continuam chegando depois do fechamento: tratar como acima.
- Trava com exceção demais perde o valor; reabrir é permissão restrita e sempre justificada.
- Começar **só com a lista de conferência e o carimbo** (sem trava) é uma primeira entrega válida e sem risco para as baixas.

## Esforço

Médio (lista e carimbo: pequeno; trava: o grosso do risco).

## Pacote

**P9 (Fechamento fiscal e contador)**, junto com a [Onda 97](exportacao_folha_pagamento_roadmap.md), o [acesso do contador (Onda 75)](acesso_contador_roadmap.md) e a NFS-e da Onda 42. Ver a
[proposta de unificação](unificacao_de_ondas_proposta.md).
