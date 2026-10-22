# Apuração de Hora-Aula Docente (Onda 50) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda.

## Contexto

O professor é remunerado por aulas, não por jornada. A CLT (arts. 317 a 324) fixa a remuneração pelo
**número de aulas semanais**, na conformidade dos horários (art. 320), com o mês de quatro semanas e meia
(§ 1º), desconto das aulas faltadas (§ 2º) e pagamento mantido no período de exames e de férias escolares
(art. 322). O valor da hora-aula, a hora-atividade e o descanso semanal dependem da **convenção coletiva
da categoria**. Hoje essa conta é feita fora do sistema.

## O que já existe

- `Funcionario` (regime, carga horária semanal) e `ContratoTrabalho` (salário vigente, histórico de
  vigências).
- `CronogramaAula` (professor, data, horário) e a chamada por aula (`FrequenciaEscolar`): uma aula com
  chamada lançada foi dada.
- `SubstituicaoProfessor` (titular × substituto por turma, disciplina e período) e a atribuição de
  professor por disciplina na turma (`turma_disciplina`).
- Ponto eletrônico (Onda 41) e RH (Onda 10).
- **Falta:** apurar por mês quantas aulas cada professor deu, quantas faltou e quanto isso significa em
  valor, e entregar o resultado à contabilidade.

## Escopo

### Apuração mensal (`ApuracaoHoraAula`)
- Aulas dadas por professor, turma e disciplina; aulas com falta do professor (desconto) e aulas
  substituídas (quem deu a aula é quem conta).
- Valor da hora-aula por professor (ou por nível/titulação), em tabela editável com vigência; DSR e
  mês de 4,5 semanas conforme a regra da convenção, em configuração.
- Hora-atividade e reuniões pedagógicas como lançamentos manuais (a convenção define).
- Reposições (Onda 49) contam para quem repôs.

### Fechamento e entrega
- Fechamento mensal com **conferência do professor** (no próprio painel, com o papel de professor, que
  só vê a sua apuração) e prazo para contestar.
- Exportação CSV/PDF para a contabilidade. O sistema **não** calcula encargos nem emite folha.

## Regras e limites

- Nada de valores ou percentuais da convenção no código: tabelas editáveis e com histórico.
- A apuração é conferida e travada por mês; ajustes depois do fechamento são lançamentos novos.
- Dados salariais seguem a restrição de acesso do RH (`ContratoTrabalho`).

## Dependências

- Onda 10 (RH) e Onda 41 (ponto) implementadas. A Onda 49 melhora a conta ao tratar reposição.

## Riscos e observações

- Aula "dada" por chamada lançada depende de o professor lançar a frequência; aula sem chamada vira
  pendência, não desconto automático.
- Cada sindicato tem regras próprias; começar com uma convenção parametrizada e validar com a contabilidade.

## Esforço

Médio.
