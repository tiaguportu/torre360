# Encargos por Atraso: Multa e Juros (Onda 62)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda das telas e na seção 75 do
> `MANUAL_USUARIO.md`. Aparece em **Financeiro → Faturas** (coluna "Multa e Juros"), no **Relatório de
> Inadimplência**, no formulário do **Acordo de Inadimplência** e no Portal da Família (**Financeiro**).

## Contexto

O sistema listava as faturas, mas **nunca calculou o que a família deve depois do vencimento**: o formulário
do acordo de inadimplência vinha com 2% e 1% fixos como sugestão, e a secretaria fazia a conta fora. Sem a
conta no sistema, cada atendimento dá um número diferente, a família não sabe quanto vai pagar, e a escola
corre o risco de cobrar multa acima do limite legal.

Base legal:

- **CDC, art. 52, § 1º** (redação da Lei nº 9.298/1996): a multa de mora nos contratos de consumo **não pode
  passar de 2%** do valor da prestação. Contrato de mensalidade escolar é contrato de consumo.
- **Juros de mora** precisam estar previstos no contrato; **1% ao mês** é o usual. O limite de 12% ao mês no
  sistema é só uma trava contra erro de digitação, não uma orientação jurídica.
- **Lei nº 9.870/1999, art. 6º**: inadimplência não autoriza suspender prova, reter documento ou aplicar
  penalidade pedagógica. Os encargos são só financeiros.

## O que já existia

- `Fatura`, `ItemFatura` e `TransacaoBancaria` (valor da fatura, valor pago e saldo), `Contrato` e o
  `RelatorioInadimplencia`.
- **Defeito encontrado:** o relatório só listava fatura com status `Atrasado`, e **nenhum processo grava esse
  status** (o status só vai para `Atrasado` à mão). Na prática o relatório de inadimplência ficava vazio.
- O acordo de inadimplência trazia multa de R$ 1.500,00 e juros de 2% e 1% como valores de exemplo.

## O que foi feito

### Cálculo (`EncargosAtrasoService`)
- **Multa:** uma vez só, sobre o saldo em aberto (valor da fatura menos o que já foi pago). Padrão de 2%.
- **Juros:** simples e proporcionais ao dia (`saldo × % ao mês × dias de atraso ÷ 30`). Padrão de 1% ao mês.
- **Vencimento em sábado, domingo ou feriado vale no próximo dia útil.** Os dias de atraso contam a partir
  desse dia. Os feriados ficam em `config/financeiro.php` (`encargos.feriados`, formato `AAAA-MM-DD`).
- **Carência** opcional em dias (`encargos.carencia_dias`, padrão 0): dentro dela não se cobra nada; passada a
  carência, a multa entra e os juros contam desde o vencimento.
- **Pagamento parcial** reduz a base de cálculo. Fatura paga ou cancelada tem encargo zero.
- **Percentuais por contrato:** `contrato.multa_percentual` e `contrato.juros_mensal_percentual` (opcionais)
  valem no lugar do padrão da escola e ficam **congelados no contrato**; mudar o padrão depois não mexe nos
  contratos já assinados. A multa do contrato acima de 2% é **limitada a 2%** (no formulário e no cálculo); os
  juros, a 12% ao mês.
- Nada é gravado: o valor é calculado na hora, a cada tela, e muda a cada dia de atraso.

### O que é "fatura em atraso"
`Fatura::emAtraso()`: status `Atrasado` **ou** `Pendente`/`Parcial` com o vencimento já passado. Mesma regra de
`Matricula::hasDebitosVencidos()`. Isso corrigiu o relatório de inadimplência, que passou a listar as faturas
vencidas de verdade.

### Telas
- **Faturas:** coluna "Multa e Juros" (opcional) com os dias de atraso.
- **Relatório de Inadimplência:** cinco cartões (faturas em atraso, total devido, multa e juros, total
  atualizado, responsáveis) e a coluna "Multa e Juros".
- **Acordo de Inadimplência:** com faturas vencidas, a multa e os juros do formulário vêm do cálculo real.
- **Contrato:** campos de multa e juros (opcionais), com a base legal no texto de ajuda.
- **Portal da Família → Financeiro:** colunas "Multa e juros" e "Valor hoje", vencimento em vermelho e um botão
  **Demonstrativo de débito** por aluno com fatura vencida.

### Demonstrativo de débito (PDF)
`/demonstrativo-debito/{matrícula}`: aluno, turma, responsáveis, cada fatura vencida com dias de atraso, valor
em aberto, multa, juros e total, e a nota de cálculo. Abre para a família do aluno (responsável, responsável
financeiro ou o próprio aluno) e para quem tem `View:Fatura`; o professor não abre. A emissão por quem é da
equipe fica no log (só a quantidade de faturas, sem valores).

## Decisões e limites

- **O boleto ou o PIX emitido no gateway não é refeito** com os encargos: o demonstrativo é informativo e diz
  que o valor do banco pode ser diferente. Quando existir um gateway real, reemitir a cobrança atualizada é a
  ampliação natural (hoje só há o driver de teste).
- Nenhuma rotina troca a fatura para `Atrasado`: a definição de atraso fica por data.
- Sem desconto de pontualidade, sem correção monetária (IPCA/IGP-M) e sem juros compostos.
- O cálculo não substitui o jurídico: o contrato é quem manda. Se o contrato da escola prevê encargos
  diferentes, preencha os campos do contrato e confira com o advogado.

## Testes

`tests/Feature/EncargosAtrasoTest.php` (22 testes: multa e juros, fim de semana e feriado, carência, contrato
com percentuais próprios e teto, pagamento parcial, escopo `emAtraso`, relatório, formulário do contrato,
lista de faturas, Portal e PDF com autorização) e os testes já existentes de inadimplência, acordo e Portal.
