# Provas Online (Onda 8) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Última onda do roadmap original de funcionalidades do Sponte para o Torre360 (ondas 1-7 já
implementadas e integradas à `main`). Classificada como a de maior risco e custo do roadmap,
por isso ficou isolada e para o fim: depende de módulos que precisavam existir primeiro
(Portal da Família/Aluno e a estrutura pedagógica — matriz curricular, grade horária, salas),
ambos já disponíveis.

## Escopo

Permitir que o professor monte provas/atividades e o aluno as responda pelo Portal, com
correção automática de questões objetivas.

### Modelos novos
- `BancoQuestoes`: agrupador reutilizável de questões (por disciplina/série/professor).
- `Questao`: enunciado, tipo (objetiva/dissertativa), alternativas (quando objetiva),
  resposta correta, peso/valor, disciplina, habilidades BNCC relacionadas (opcional).
- `ProvaOnline`: título, turma, disciplina, questões selecionadas do banco (ou avulsas),
  data/hora de abertura e fechamento, duração máxima, embaralhamento (questões e/ou
  alternativas) ligado/desligado.
- `TentativaProva`: tentativa de um aluno (matrícula) em uma prova — início, fim, status
  (em andamento/enviada/corrigida).
- `RespostaAluno`: resposta do aluno a cada questão de uma tentativa.

### Correção
- **Objetivas**: automática ao enviar a tentativa, comparando com a resposta correta
  cadastrada na `Questao`; grava o resultado em `Nota` (reaproveitando
  `NotaLancamentoService`/`Avaliacao` existentes, para não duplicar o fluxo de notas).
- **Dissertativas**: ficam pendentes de correção manual pelo professor (tela própria,
  nota por questão, feedback opcional).

### Janela de tempo e antifraude básico
- A prova só fica visível/respondível entre abertura e fechamento (`ProvaOnline`).
- Duração máxima por tentativa, contada a partir do início (não do horário de abertura da
  prova).
- Embaralhamento de questões e alternativas por tentativa (reduz cola entre alunos vendo a
  tela um do outro; não é proteção contra cópia fora do sistema).
- Fora de escopo nesta proposta: proctoring (câmera/gravação), bloqueio de troca de aba,
  detecção de múltiplos dispositivos — exigiriam decisão própria sobre custo/privacidade
  (LGPD) antes de entrar no plano.

### Portal do Aluno
Nova página (`Filament\Portal\Pages`) listando provas disponíveis para o aluno, com o
formulário de resposta e cronômetro da duração restante.

## Divisão sugerida em sub-ondas

Dado o risco/custo elevado, a implementação pode ser quebrada em duas:

- **8a — Banco de questões e prova objetiva**: `BancoQuestoes`, `Questao` (objetivas),
  `ProvaOnline`, `TentativaProva`, `RespostaAluno`, correção automática, página no portal.
  Entrega valor sozinha (provas 100% objetivas já funcionam fim a fim).
- **8b — Dissertativas e antifraude básico**: questões dissertativas, tela de correção
  manual, embaralhamento.

Cada sub-onda segue o mesmo processo das ondas anteriores (branch própria, migration +
model + Resource/Policy + teste Feature, `docs/`, Pint, commit, push) e depende de
autorização explícita e separada antes de começar.

## Dependências já satisfeitas

- Portal da Família/Aluon (Onda 2): página e autenticação do aluno já existem.
- Estrutura pedagógica (Onda 4): `MatrizCurricular`, `GradeHorario`, `Sala` já existem,
  então `ProvaOnline` pode se apoiar em turma/disciplina sem modelagem adicional.
