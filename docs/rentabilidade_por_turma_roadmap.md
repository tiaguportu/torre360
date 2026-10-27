# Rentabilidade e Custo por Turma (Onda 77)

> **Status: implementada** (2026-10-27). As seções *Contexto*, *O que já existe* e *Escopo* abaixo são o planejamento original;
> o que foi entregue, com as decisões e o que ficou de fora, está em **Como foi implementado**, no fim do documento.

## Contexto

O DRE mostra receita e despesa da escola inteira. Quem decide abrir turma, ajustar preço ou negociar bolsa precisa saber
**quanto custa e quanto rende cada turma e cada curso**. Sem isso, a conversa sobre turma pequena é feita de impressão.

## O que já existe

- **Receita por turma**: `Fatura` → `Contrato` → `Matricula` → `Turma`, com baixa e saldo.
- **Custo docente**: apuração de hora-aula e DSR (Onda 50, `ApuracaoHoraAula`) e a grade da turma (aulas por semana).
- **Despesa por centro de custo**: `ContaPagar` e `TransacaoBancaria` carregam `centro_custo_id` e `plano_conta_id`.
- **Bolsas e descontos** (`BolsaConcedida`) e **encargos de atraso** (Onda 62), que mudam a receita realizada.
- **Relatórios configuráveis** (Onda 72) para entregar o resultado em planilha.

## Escopo proposto

1. **Receita da turma**: faturada, recebida e em atraso no período, já líquida de bolsa.
2. **Custo direto**: horas-aula dos professores da turma (Onda 50) e despesas lançadas no centro de custo da turma ou do curso.
3. **Rateio do custo indireto** (administração, aluguel, limpeza) por critério escolhido: por aluno, por turma ou por hora
   de aula. O critério é parâmetro da escola e fica registrado na própria tela.
4. **Margem e custo por aluno** por turma, série, curso e unidade, comparados ao mês anterior e ao ano anterior.
5. **Fonte nos relatórios** da Onda 72 e **linha na Mesa**: turma com margem negativa há N meses.

## Base legal

Sem obrigação legal: é contabilidade gerencial. A contabilidade oficial segue com o escritório (Onda 73).

## Dependências e riscos

- **O rateio é uma escolha**, não um fato. Dois critérios dão margens diferentes; a tela precisa mostrar o critério ao lado
  do número, para ninguém tratar o resultado como verdade contábil.
- Exige centro de custo e conta do plano preenchidos no lançamento. Hoje a baixa de fatura não classifica (ver a Onda 73);
  a conta de receita por item de fatura (ampliação da 73) melhora o resultado.
- Dado sensível para a gestão: restringir por permissão própria, como o Painel da Direção.
- Ordem: 50 (feita), ampliação da 73 (conta por item de fatura), depois 77. Fusão natural com a Onda 76 (ponto de equilíbrio).

---

## Como foi implementado (2026-10-27)

Menu **Financeiro → Resultado Realizado** (`/admin/financeiro/resultado-realizado-turmas`). Complementa a **Rentabilidade de Turmas** (Controladoria) que já existia: aquela é o **planejado** (mensalidade base e custos digitados na turma); esta é o **realizado**, com o que aconteceu no mês. A tela mostra o planejado ao lado, na coluna *Planejado*.

### O que entra na conta de cada turma (`RentabilidadeTurmaService`)

- **Receita:** as faturas com **vencimento no mês** (não canceladas), já líquidas de bolsa e desconto (o valor da fatura vem dos itens), com o quanto foi **pago** e o quanto está **em atraso** (vencida e ainda aberta). A margem é sobre o **faturado** (competência).
- **Custo docente:** a **apuração de hora-aula** do professor no mês (Onda 50, `valor_total_bruto`), dividida entre as turmas pela **proporção das aulas dele no cronograma** do mês. O professor com apuração e sem aula no cronograma (ou com aula em turma fora da lista) vai para o **pool indireto**, e a tela avisa o valor.
- **Custo direto:** saídas bancárias do mês **classificadas como despesa** em um centro de custo **vinculado à turma** (vai inteiro) ou **ao curso** (dividido entre as turmas do curso pelo número de alunos). O cadastro do centro de custo ganhou *Custo direto de uma turma*, *Custo direto de um curso* e **Fora do resultado das turmas** (folha dos professores, que já vem da apuração, investimentos, impostos).
- **Custo indireto** (administração, aluguel, limpeza): as demais saídas de despesa, rateadas pelo critério escolhido: **por aluno**, **igual por turma** ou **por hora-aula** (aulas no mês). O critério aparece ao lado do número e **muda a margem de cada turma, nunca o total da escola** (os testes conferem os três).
- **Alunos do mês:** matrículas que estiveram ativas em algum momento do mês (ativadas até o fim dele e não desativadas antes do início), sem as pendentes e as de reserva.
- **Turmas consideradas:** em andamento ou concluídas cujo período letivo toca o mês. Planejadas e canceladas ficam de fora.
- **Fora da conta, com aviso:** saída sem conta do plano (pode ser transferência) é contada e mostrada na tela como pendência de classificação.

Cada linha traz faturado, recebido, em atraso, custos (docente, direto, indireto, total), **resultado, margem, custo por aluno, resultado por aluno**, a margem **planejada** e o resultado do **mês anterior**; o cabeçalho traz o total do mês anterior e do mesmo mês do ano anterior. A visão pode ser **por turma, série, curso ou unidade**; o filtro de unidade mantém o rateio global (o pool é dividido entre todas as turmas e só então filtrado).

### Minha Mesa e relatórios

Fila **Turmas com resultado negativo** (`View:RentabilidadeRealizada`): turma com receita e resultado negativo em **todos** os últimos 3 meses completos; atrasada quando a margem do último mês é igual ou pior que -20%. O cálculo percorre vários meses e fica em cache por 6 horas. Fonte **Resultado realizado por turma** nos relatórios configuráveis (Onda 72).

### Parâmetros e permissão

`config/financeiro.php` → `rentabilidade`: `meses_alerta` (3), `margem_critica` (-20) e `criterio_padrao` (aluno, usado pela fila da Mesa e pelo indicador do Painel da Direção). Permissão **`View:RentabilidadeRealizada`** (administração e financeiro): é dado sensível para a gestão.

### Testes

`tests/Feature/RentabilidadeTurmaTest.php` (16, com o cenário fechado à mão: 3.800 faturados, 3.400 de custo em qualquer critério) e `tests/Feature/RentabilidadeTelasTest.php` (12).

### Limites conhecidos

Contabilidade gerencial: despesas por **caixa** (data da transação) e receita por **competência** (vencimento); a conta de receita por item de fatura (ampliação da Onda 73) ainda não existe, então a receita não se classifica por tipo (mensalidade, material, taxa); custo de turma sem aula no cronograma não recebe docente. O rateio é uma escolha da escola e a tela não o trata como verdade contábil.
