# Reajuste de Mensalidades (Onda 44)

> **Status: implementado**, em entrega unificada com o informe anual da Onda 42. O escopo, as regras
> e as telas estão em [reajuste_e_informe_anual_roadmap.md](reajuste_e_informe_anual_roadmap.md).

## Resumo

Simulação do novo valor de cada série a partir da média dos contratos do período de origem da
campanha de rematrícula, com percentual padrão ou próprio por série, arredondamento, projeção de
receita e pontos de atenção (planilha da Lei nº 9.870/1999, prazo de 45 dias, rematrículas já
efetivadas). Aprovado e aplicado, o preço de cada série passa a valer no novo contrato das
rematrículas efetivadas depois. Contratos existentes não mudam.

## O que ficou diferente da proposta

- Em vez de alterar contratos um a um (`ReajusteAplicado`), o reajuste define o **preço da série na
  campanha** (`periodo_rematricula_precos`), que a efetivação da rematrícula usa.
- A projeção de receita não considera inadimplência histórica.
- O comunicado às famílias não é disparado pelo sistema: há a lista em CSV e o registro da data.
