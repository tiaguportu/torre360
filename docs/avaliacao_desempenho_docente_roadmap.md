# Avaliação de Desempenho Docente (Onda 19) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Ciclo de observação/avaliação do professor pela coordenação — distinto de dois módulos já
existentes com os quais poderia ser confundido:

- **RH e Gestão de Funcionários** (Onda 10) é administrativo/contratual (cargo, salário,
  férias) — não avalia desempenho pedagógico.
- **Avaliação do aluno** (notas, BNCC) mede o aluno, não o professor.
- **Questionários e Avaliação Institucional** (já existente) é genérico e poderia, em
  tese, ser reaproveitado para uma pesquisa de satisfação sobre o professor — mas não
  cobre um **ciclo de observação em sala** com critérios pedagógicos específicos e
  histórico por professor, que é o que este módulo propõe.

## O que já existe (ponto de partida)

- `Funcionario` (Onda 10) já existe como cadastro do professor enquanto funcionário —
  este módulo se vincula a ele, não duplica o cadastro.
- `Questionarios` (sistema genérico de formulários) já existe e pode ser reaproveitado
  como motor de formulário, em vez de construir um novo sistema de perguntas do zero.

## Escopo

### Modelo novo
- `CicloAvaliacaoDocente`: nome/período (ex.: "1º Semestre 2026"), data de início/fim.
- `AvaliacaoDocente`: `funcionario_id` (professor avaliado), `ciclo_avaliacao_docente_id`,
  avaliador (coordenador/user), data da observação, critérios avaliados (reaproveitando a
  estrutura de `Questionarios` como motor de formulário, em vez de um schema fixo),
  pontuação consolidada, parecer qualitativo em texto livre, status
  (rascunho/finalizada).

### Telas
- Cadastro de ciclos de avaliação pela coordenação/RH.
- Lançamento da avaliação pelo coordenador (reaproveitando o motor de `Questionarios`
  para montar os critérios).
- Histórico de avaliações por professor, visível para o próprio professor e para RH.

## Dependências já satisfeitas

- `Funcionario` (Onda 10) já existe como alvo da avaliação.
- `Questionarios` já existe como motor de formulário reaproveitável, evitando construir
  um sistema de perguntas/respostas do zero.

## Risco e observação

- Dado sensível (avaliação de desempenho de funcionário) — merece a mesma restrição de
  acesso que RH (Onda 10): visível para o próprio professor avaliado, coordenação e
  admin/super_admin, não para outros professores.
