# Carteira Digital do Aluno (Saldo Pré-pago Único) (Onda 102) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).
> Generaliza o **saldo da [Onda 17 (cantina)](cantina_escolar_roadmap.md)**: em vez de um saldo da cantina, **um saldo do aluno** que a cantina, a loja, o passeio e a
> biblioteca possam usar.

## Contexto

A cantina previa um saldo pré-pago só dela. Mas há outros pontos de venda pequenos (loja de uniforme, passeio, material, multa de biblioteca), e cada um com saldo
próprio seria um jeito diferente de a família recarregar. O que a escola precisa é de **uma carteira por aluno**, com extrato, que a família recarrega uma vez e que cada ponto
de venda debita. A criança leva a credencial ([Onda 101](credencial_digital_qr_roadmap.md)) e não leva dinheiro.

## O que já existe (conferido no código)

- **Não há conceito de saldo ou carteira por aluno** (busca por saldo de aluno, crédito ou carteira em `app` e `database` sem resultado útil).
- Cobrança: `Fatura`/`ItemFatura`, `GatewayPagamento` (PIX, boleto, link), `BaixaFaturaService` (que cria a `TransacaoBancaria`) e conciliação bancária. A fatura nasce do **contrato**
  (`Fatura.contrato_id`): **confirmar se aceita nulo**; sem isso, a recarga precisa de uma forma de cobrança avulsa que a loja da [Onda 67](loja_escolar_roadmap.md) também pede.
- `PlanoConta` e a classificação contábil das entradas (Onda 73), `BolsaConcedida`, estoque (`ItemEstoque`, `MovimentacaoEstoque`) e cardápio com alérgenos (Onda 40).
- Cancelamento de matrícula com **devolução** (Onda 61), que precisa tratar o saldo que sobra.

## Escopo proposto

### O livro da carteira
- `Carteira` (uma por matrícula) e `LancamentoCarteira`, **somente inserção**: tipo (recarga, débito, estorno, ajuste), valor, **saldo depois do lançamento**, origem (fatura de recarga, venda da
  cantina, pedido da loja, passeio, multa), operador, chave de idempotência e observação. O saldo é o último lançamento; nenhum lançamento é editado nem apagado, só estornado.
- Sem crédito automático e sem saldo negativo: compra maior que o saldo é recusada.

### Recarga
- Fatura avulsa com PIX (pelo gateway) ou recarga manual no balcão (com recibo e operador). A baixa da fatura lança o crédito, uma vez só, mesmo que o webhook repita.

### Uso e controle da família
- **Extrato no Portal** (data, local, valor e saldo), sem o detalhe de cada item se a escola preferir.
- O responsável define **limite diário**, saldo mínimo para aviso, e **restrições por categoria** (por exemplo, sem doces); o cruzamento com a alergia da ficha médica avisa o caixa.
- Aviso quando o saldo baixa de um valor (pelos canais da [Onda 70](central_avisos_familia_roadmap.md)).

### Encerramento
- Na saída do aluno, o saldo é **devolvido** ou **transferido para um irmão** (a escolha é do responsável), com prazo, e integra a devolução da Onda 61. Saldo parado de aluno que saiu tem
  política definida (aviso, prazo e destino).

### Para a gestão
- Relatório do **passivo da carteira** (soma dos saldos = o que a escola deve às famílias), conciliado todo dia com os lançamentos; vendas por ponto, por categoria e por dia.

## Base legal

- **CDC, arts. 6º e 31:** informação clara sobre o saldo, as regras de uso e a devolução. Saldo pré-pago é uma obrigação da escola com a família.
- **Fiscal e financeiro:** o enquadramento do saldo (a escola recebe antes de entregar o bem ou serviço) e a nota fiscal na venda precisam de **validação da contabilidade e do jurídico**
  antes de produção, como a Onda 67.
- LGPD: o histórico de consumo de uma criança é perfil de hábitos e dado de saúde indireto (alergia). Acesso restrito, sem uso comercial.

## Dependências e riscos

- Depende de um meio de recarga online (a [Onda 68](gateway_pagamento_real_roadmap.md)); o driver fictício serve para desenvolver.
- **Fraude e erro de caixa:** lançamento só pelo operador autenticado, com a credencial da Onda 101; estorno com motivo e perfil de permissão próprio.
- Mais um sistema de dinheiro dentro do sistema: a conciliação diária entre o livro, as faturas pagas e os extratos é obrigatória, não opcional.
- Pode ser desnecessária se a escola usar só fatura (a loja da Onda 67 já funciona assim). **Decidir antes de construir** se a cantina e a carteira têm demanda real.

## Esforço

Médio a grande.

## Pacote

**P6 (Serviços opcionais e vendas)**, com recarga pelo **P8**. Ver a [proposta de unificação](unificacao_de_ondas_proposta.md).
