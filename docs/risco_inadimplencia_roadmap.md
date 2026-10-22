# Risco de Inadimplência (Onda 53) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda.

## Contexto

O sistema calcula o **risco de evasão** do aluno (Onda 13) e tem a **régua de cobrança**, que age depois do
atraso. Falta o meio do caminho: saber **quais contratos têm maior chance de atrasar** para agir antes
(lembrete, contato, acordo) e priorizar a equipe financeira.

## O que já existe

- `Matricula::hasDebitosVencidos()`, `Fatura` (status e vencimento), `TransacaoBancaria` (pagamentos).
- `ReguaCobranca` e seus registros, `AcordoInadimplencia` (acordos e parcelas) e a página de relatório de
  inadimplência.
- O molde completo do score: `RiscoEvasaoService`, `RiscoEvasaoConfiguracao`, `config/risco_evasao.php`,
  página de pesos e comando de recálculo (documento da Onda 13).
- **Falta:** o score financeiro e a ação preventiva.

## Escopo

### Fatores (pesos configuráveis, somando 100)
- Atrasos nos últimos 6 meses (quantidade e dias médios).
- Pagamento sempre perto ou depois do vencimento.
- Valor em aberto em relação à mensalidade.
- Acordo anterior quebrado.
- Mudança de comportamento: antes pagava em dia e passou a atrasar.

### Telas e ações
- Score por contrato/responsável, faixas (baixo, médio, alto) e painel para o financeiro.
- **Lembrete preventivo** antes do vencimento só para a faixa alta, pelos canais da régua.
- Relatório à direção: carteira por faixa e evolução.
- Recálculo por evento (pagamento, atraso) e comando diário.

## Regras e limites

- **O score só prioriza contato.** Ele não pode ser usado para negar matrícula, rematrícula nem atendimento.
  A Lei nº 9.870/1999 permite recusar a renovação da matrícula de quem está inadimplente (art. 5º), mas
  proíbe suspender provas, reter documentos escolares ou aplicar penalidade pedagógica por inadimplemento
  (art. 6º); e documentos de transferência saem a qualquer tempo.
- LGPD: é perfilamento do titular. Explicar os critérios na política de privacidade e garantir a revisão
  humana de qualquer decisão que o afete (art. 20); manter o score fora do Portal da família.
- Acesso só ao financeiro e à direção.

## Dependências

- Financeiro e régua de cobrança implementados. Opcionalmente a Onda 44 (reajuste) usa a carteira por faixa
  na projeção de receita.

## Riscos e observações

- Poucos meses de histórico dão score fraco: mostrar "dados insuficientes" em vez de uma faixa.

## Esforço

Médio.
