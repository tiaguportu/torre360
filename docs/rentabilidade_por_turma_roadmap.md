# Rentabilidade e Custo por Turma (Onda 77) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade.

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
