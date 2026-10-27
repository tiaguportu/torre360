# Simulador do Próximo Ano Letivo (Onda 76)

> **Status: implementada** (2026-10-27). As seções *Contexto*, *O que já existe* e *Escopo* abaixo são o planejamento original;
> o que foi entregue, com as decisões e o que ficou de fora, está em **Como foi implementado**, no fim do documento.

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

---

## Como foi implementado (2026-10-27)

Menu **Relatórios → Simulador do Próximo Ano** (`/admin/simulador-proximo-ano`). É **projeção, não compromisso**, e a tela diz isso.

### O que entra na conta (`SimuladorProximoAnoService`)

A projeção é por **série de destino** (a que a família escolheu na rematrícula; o sistema não tem "próxima série" automática) para a campanha de rematrícula escolhida:

- **Rematrículas confirmadas** contam inteiras.
- **Rematrículas em andamento** (iniciada, dados confirmados, aguardando assinatura) pesam pela **taxa de andamento** (padrão 90%), depois de tirar quem tem **risco de evasão** igual ou acima do corte (padrão 60; 0 desliga).
- **Lista de espera** do período de destino (aguardando e notificados) soma **até as vagas que sobram** depois do que já está confirmado.
- **Quem ainda nem iniciou** a rematrícula (matrícula ativa na origem, sem rematrícula na campanha) não tem série de destino: entra à parte, em *Sem série de destino definida*, pela **taxa de renovação** (padrão 60%, sem quem tem risco alto), **só no total**.
- **Recusas** (rematrícula cancelada) saem da conta e aparecem no resumo.

Por série: confirmadas, em andamento (e quantas saíram por risco), espera (e quanto entrou na conta), **projeção**, turmas e vagas do período de destino, **vagas livres ou excesso**, alunos por turma, **preço anual** (tabela de preços da campanha; na falta dela, a média dos contratos atuais, com o rótulo da origem), **receita mensal prevista** e o **ponto de equilíbrio** (custo planejado médio da turma da série sobre o preço mensal). A situação de cada série é *dentro do esperado*, *acima da capacidade*, *demanda sem turma* ou *abaixo do equilíbrio*; a espera maior que as vagas que sobram também é sinalizada. No total: alunos projetados, turmas e vagas, receita mensal prevista e a diferença contra a receita do ano em curso.

### O "e se"

Na própria tela: taxa de andamento, taxa de renovação, corte de risco, **reajuste extra** sobre o preço, soma da espera, e por série o **número de turmas** (zero fecha a série) e a **capacidade por turma**; também dá para **abrir turma em uma série que ainda não tem**. A tela sugere as taxas da **campanha anterior encerrada** (das rematrículas iniciadas, quantas terminaram; dos alunos elegíveis, quantos renovaram).

### Cenários salvos

*Salvar cenário* guarda os parâmetros e a **fotografia do resultado do dia** (`cenarios_proximo_ano`). Os cenários da campanha ficam lado a lado (taxas, alunos, turmas e vagas, receita, diferença contra hoje, alertas). *Carregar* devolve os parâmetros à tela para recalcular com os dados de hoje; *Excluir* é de quem criou ou de quem tem `Gerenciar:SimuladorProximoAno`.

### Minha Mesa e relatórios

Fila **Projeção do próximo ano** (`View:SimuladorProximoAno`, área Secretaria) com os alertas da projeção padrão; fica **atrasada quando a campanha de rematrícula termina em até 15 dias** (`simulador.alerta_prazo_dias`): a decisão de abrir ou fechar turma tem data. Fonte **Projeção do próximo ano letivo** nos relatórios configuráveis, com a projeção de hoje ou a fotografia de um cenário salvo.

### Parâmetros e permissões

`config/simulador.php`: `padroes` (taxas, corte, espera, reajuste), `capacidade_padrao` (30, para série sem turma e sem capacidade informada) e `alerta_prazo_dias` (15). Permissões `View:SimuladorProximoAno` (administração, secretaria, coordenação) e `Gerenciar:SimuladorProximoAno` (administração).

### Testes

`tests/Feature/SimuladorProximoAnoTest.php` (20, cenário fechado à mão) e `tests/Feature/SimuladorProximoAnoTelasTest.php` (15).

### Fora desta entrega

Conta de **custo docente por turma** a partir da grade assistida (Onda 64) e da hora-aula (o equilíbrio usa o custo planejado digitado na turma), risco de inadimplência na projeção (Onda 53, continua proposta), exportação do cenário em PDF (a planilha sai pela fonte de relatórios) e a ligação "o orçamento do próximo ano nasce do cenário escolhido" (hoje o orçamento é criado à parte, copiando o ano anterior).
