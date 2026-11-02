# Mapa de Habilidades da Turma (Onda 99) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

O sistema já guarda **o domínio de cada aluno em cada habilidade**: por conceito, lançado pelo professor, e por acerto, vindo da correção das provas por foto
(OMR). Mas ninguém consegue responder "**quais habilidades a turma 5º B ainda não domina**" nem "**quais alunos precisam retomar a habilidade EF05MA03**".
O dado existe e não vira decisão: a coordenação monta a lista à mão, e o reforço ([Onda 28](reforco_recuperacao_paralela_roadmap.md)) não tem de onde partir.

## O que já existe (conferido no código)

- `Habilidade` (código, nome, descrição; campo de experiência para a educação infantil).
- **Conceito por habilidade:** `AvaliacaoHabilidade` (turma, etapa avaliativa, professor) e `NotaHabilidade` (matrícula, habilidade, `conceito` do enum `ConceitoHabilidade`),
  lançados em `LancarNotaHabilidade`. **A única leitura é o Portal** (*Notas*, por aluno).
- **Acerto por habilidade na prova:** `OmrCorrecaoHabilidade` (correção, habilidade, `acertos`, `total`), gravada pelo `OmrService`. **Nenhuma tela lê essa tabela** (a busca por uso mostra
  só as duas gravações no `OmrService`).
- `Avaliacao` com gabarito por questão (`AvaliacaoGabaritoQuestao`), que é a origem das provas corrigidas pelo OMR.
- Escopo do professor (`EscopoProfessor`): o professor vê só as próprias turmas.
- **Não existe** nenhum cruzamento por turma nem por habilidade (busca por `MapaHabilidade` e `mapa_habilidades` sem resultado; as únicas leituras de `NotaHabilidade` são o Portal e as telas de lançamento).

## Escopo proposto

### O mapa
- **Matriz turma × habilidade:** linhas são alunos, colunas são habilidades, e a célula mostra o domínio (cor e valor) com a **fonte** (conceito do professor ou acerto da prova).
  Filtros: turma, disciplina, etapa, fonte. Ordenação pelas habilidades **menos dominadas**, que é o que a coordenação quer ver primeiro.
- **Visão por habilidade:** percentual da turma que domina, lista de quem precisa retomar e as provas e itens em que a habilidade apareceu.
- **Visão por aluno:** habilidades por disciplina, com a evolução entre etapas.
- **Visão da série (coordenação):** a mesma habilidade em todas as turmas, para separar "problema da turma" de "problema da matriz".

### Do mapa à ação
- Botão **Agrupar para reforço**: gera a lista de alunos da turma que estão abaixo do corte numa habilidade e a leva à intervenção da
  [P4 (Cuidado com o aluno)](unificacao_de_ondas_proposta.md) ou, antes dela, a um relatório CSV.
- Fonte para a **cobertura do plano de ensino** ([Onda 29](plano_ensino_anual_cobertura_roadmap.md)): habilidade planejada × habilidade com baixo domínio.
- Fonte de relatório (Onda 72) e indicador opcional do Painel da Direção.

### Regras
- **Corte configurável** (por exemplo, abaixo de 60% de acerto ou conceito inferior ao esperado) e **mínimo de itens** para a célula valer (uma questão só não diz nada).
  Célula sem dados aparece como "sem avaliação", nunca como zero.
- Conceito e acerto não se misturam num número só: são exibidos lado a lado, com a fonte. A conversão do conceito para faixa é uma tabela do cadastro.
- **Rótulo é do desempenho, não do aluno:** "habilidade a retomar", nunca "aluno fraco". O mapa não vai ao Portal da família por padrão.

## Base legal

LGPD, art. 14 e art. 6º, VI (transparência) e IX (não discriminação): o mapa é dado pedagógico de criança, de uso interno, e não pode virar ranking nem critério de
decisão sobre rematrícula ou bolsa. BNCC como referência das habilidades.

## Dependências e riscos

- **Qualidade do dado:** se poucas provas têm habilidade vinculada às questões, o mapa fica vazio. Medir a cobertura (percentual de itens com habilidade) na primeira versão.
- Coerência entre as duas fontes: nem todo ano usa conceito e nem toda prova passa pelo OMR.
- Desempenho: agregação por turma e habilidade pede consulta própria e cache (como o Painel), não cálculo na abertura da tela.
- Professor vê só as turmas dele; coordenação vê a série.

## Esforço

Médio.

## Pacote

**P4 (Cuidado com o aluno)** e **P5 (Planejamento do ensino)**. Ver a [proposta de unificação](unificacao_de_ondas_proposta.md).
