# Reajuste de Mensalidades (Onda 44) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Todo fim de ano a escola define o reajuste das mensalidades do ano seguinte: simula cenários por
curso e série, considera bolsas e descontos, comunica as famílias dentro do prazo legal e atualiza
os contratos. Hoje isso é planilha, e a atualização dos valores nos contratos é manual.

## O que já existe (ponto de partida)

- `PlanilhaLeiMensalidade` e `PlanilhaLeiMensalidadeService`: planilha de custos que fundamenta o
  valor da anuidade (relacionada à Lei nº 9.870/1999).
- `Contrato`, `TemplateContrato` e `GeracaoFaturasContratoService`: valor e geração das parcelas.
- `PeriodoRematricula`/`Rematricula` e `RematriculaService`: o momento em que o novo valor é
  apresentado e aceito.
- `TipoBolsa` e `BolsaConcedida`: descontos que precisam ser preservados no reajuste.
- `Customer360TimelineService` e `ComunicacaoEmMassa`: histórico e comunicação.
- Não existe simulação de reajuste, aplicação em lote nem trilha do que foi comunicado.

## Escopo

### Modelos novos
- `CenarioReajuste`: ano de referência, nome, regra (percentual único, por série, valor fixo),
  arredondamento, status (simulação, aprovado, aplicado).
- `CenarioReajusteItem`: série/curso, valor atual, valor novo, impacto mensal e anual, número de
  alunos afetados.
- `ReajusteAplicado`: contrato, valor anterior, novo valor, vigência, comunicação enviada em.

### Telas
- Simulador com comparação de cenários e **projeção de receita** (considerando bolsas e inadimplência
  histórica).
- Aplicação em lote nos contratos da rematrícula, com prévia e reversão antes da vigência.
- Comunicado aos responsáveis com o novo valor, gerado do cenário aprovado, e registro de envio.

### Regras
- O reajuste vale a partir da vigência do novo período; parcelas já faturadas não mudam.
- Bolsa percentual acompanha o novo valor; bolsa em valor fixo exige decisão explícita.
- A divulgação precisa respeitar o prazo mínimo antes da data final de matrícula previsto na Lei
  nº 9.870/1999 (art. 1º, § 5º, 45 dias — conferir com o jurídico).

## Dependências já satisfeitas

- Contratos, rematrícula, bolsas e planilha de custos.

## Risco e observação

- Erro de aplicação em lote afeta toda a receita: exigir prévia, aprovação e reversão.
- Famílias com acordo de inadimplência ou condições especiais precisam de tratamento à parte.
