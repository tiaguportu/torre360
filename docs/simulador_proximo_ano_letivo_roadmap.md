# Simulador do Próximo Ano Letivo (Onda 76) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade.

## Contexto

Entre agosto e dezembro a direção decide **quantas turmas abrir, com quantas vagas e a que preço**, com pouca informação
junta: a rematrícula está em andamento, a lista de espera tem gente, parte dos alunos tem risco de evasão, e o reajuste
depende do custo. Cada número está numa tela. O resultado é uma planilha feita à mão, refeita a cada rodada.

## O que já existe

- **Rematrícula** (`PeriodoRematricula`, `Rematricula`): quem confirmou, quem recusou, quem ainda não respondeu.
- **Lista de espera** (`ListaEsperaMatricula`, Onda 20): interessados por série e período.
- **Capacidade** em `Turma.vagas_maximas` e a matriz de séries.
- **Risco de evasão** (`Matricula.risco_evasao_score`, Onda 13) e, proposto, o risco de inadimplência (Onda 53).
- **Reajuste** de mensalidades (Onda 44) com a regra de cálculo.
- **Carga horária docente** (Onda 50) e a grade assistida (Onda 64), que dizem quantos professores cada turma pede.

## Escopo proposto

1. **Projeção de matrículas por série**: rematriculados confirmados + pendentes ponderados pela taxa histórica de confirmação
   do ano anterior, menos o risco de evasão acima de um corte, mais a lista de espera até a vaga.
2. **"E se"**: abrir ou fechar uma turma, mudar a capacidade, mudar o reajuste. A tela mostra vagas livres, turmas
   abaixo do ponto de equilíbrio, receita prevista e a diferença para o ano em curso.
3. **Ponto de equilíbrio por turma**: receita da turma menos o custo da hora-aula (Onda 50) e do centro de custo.
4. **Cenários salvos** (otimista, esperado, conservador) para levar à reunião, com exportação em PDF e planilha pelos
   relatórios da Onda 72.
5. **Fila da Mesa**: turmas com projeção abaixo do mínimo e séries com lista de espera maior que as vagas.

## Base legal

Lei nº 9.870/1999 (anuidade escolar): o valor e o reajuste precisam ser divulgados com antecedência à matrícula, o que dá
data-limite para a decisão que o simulador apoia. Não há obrigação de usar o simulador; é gestão.

## Dependências e riscos

- Qualidade dos dados: sem taxa histórica de confirmação, a primeira edição usa um parâmetro digitado.
- Projeção não é compromisso: a tela deve dizer o que entra na conta e rotular o resultado como estimativa.
- **Fusão sugerida com a Onda 45 (Painel da Direção) e a 43 (Orçamento × Realizado):** o orçamento do próximo ano nasce do
  cenário escolhido aqui.
- Ordem: 44 (feita), 50 (feita), depois 76; 53 melhora a projeção, mas não bloqueia.
