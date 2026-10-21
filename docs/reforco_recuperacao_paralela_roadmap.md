# Reforço e Recuperação Paralela (Onda 28) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

A recuperação **paralela** acontece durante o ano, assim que a dificuldade aparece, e não só no
fim da etapa. A escola identifica alunos com habilidades não consolidadas, forma pequenos grupos,
dá aulas de reforço e verifica se houve avanço. O sistema já mede a dificuldade; falta organizar a
resposta.

## O que já existe (ponto de partida)

- `NotaHabilidade` com conceito `nao_realiza`/`em_desenvolvimento` por habilidade e aluno: dá o
  **público-alvo** do reforço de forma objetiva.
- `docs/recuperacao_etapa_exame_final.md`: recuperação de **fim de etapa** e exame final (decisão
  sobre a nota), que não substitui o reforço contínuo.
- `RiscoEvasaoService`, `FrequenciaEscolar`: sinais de que o aluno precisa de apoio.
- `Turma`, `GradeHorario`, `Sala`, `ReservaEspaco` (Onda 21): onde e quando o reforço acontece.
- `Preceptoria`: acompanhamento individual.
- Não existe turma/grupo de reforço, inscrição, frequência própria nem medição de evolução.

## Escopo

### Modelos novos
- `GrupoReforco`: nome, professor, habilidades-alvo (N:N com `Habilidade`), dias e horários,
  sala (reserva), vagas, período.
- `GrupoReforcoAluno`: `matricula_id`, origem (indicação automática ou do professor), situação
  (ativo, concluído, desligado), data de entrada e saída.
- `EncontroReforco`: data, presença por aluno, conteúdo trabalhado.

### Telas
- **Sugestão de alunos**: lista de quem está com a habilidade `nao_realiza` há mais de uma
  avaliação, para o professor montar o grupo.
- Registro de encontros e presença.
- **Evolução**: comparação do conceito da habilidade antes e depois do reforço.
- Portal da Família: o aluno foi indicado e quando é o reforço (com aviso).

### Regras
- Participar é decisão da escola com ciência da família (aviso no portal; aceite opcional).
- O aluno sai do grupo quando a habilidade chega a "realiza bem" em nova avaliação.

## Dependências já satisfeitas

- Avaliação por habilidades, grade horária e reserva de espaços.

## Risco e observação

- Cuidado de linguagem no portal: "reforço" não pode soar como rótulo ao aluno.
- Se houver cobrança do reforço, entra na lógica de contraturno (Onda 31).
