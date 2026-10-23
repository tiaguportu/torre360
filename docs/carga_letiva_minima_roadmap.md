# Carga Letiva Mínima e Reposição de Aulas (Onda 49)
 
> **Status: Implementada em 2026-10-23.**
> Coberta por migrations (`reposicao_aulas`), `CargaLetivaService`, `ReposicaoAulaResource` e suíte de testes `CargaLetivaEReposicaoTest`.

## Contexto

A LDB exige carga horária mínima anual de **800 horas** no ensino fundamental e **1.000 horas** no
ensino médio (a ser ampliada progressivamente para 1.400), distribuída por no mínimo **200 dias de
efetivo trabalho escolar**, sem contar o tempo dos exames finais (art. 24, I, na redação da Lei nº
14.945/2024). A educação infantil também tem 800 horas e 200 dias (art. 31, II). O sistema controla a
frequência mínima de 75% do **aluno**, mas não confere se a **escola** está cumprindo os 200 dias e as
horas do calendário.

## O que já existe

- `PeriodoLetivo` (início e fim) e `DiaNaoLetivo` (data, descrição, curso opcional, ativo).
- `CronogramaAula` (turma, disciplina, professor, data, hora de início e fim): base para somar horas.
- `Turma::carga_horaria_total`, informada à mão, e `HistoricoEscolarAno` com `dias_letivos` e
  `carga_horaria_total` digitados na emissão do histórico.
- `FrequenciaAlunoService` (75% do aluno) e a chamada por aula (`FrequenciaEscolar`).
- **Falta:** contar dias letivos e horas previstos e realizados por turma, comparar com o mínimo e
  registrar a reposição de aulas.

## Escopo

### Apuração (`CargaLetivaService`)
- **Dias letivos previstos:** dias com aula no cronograma (ou dias úteis do período menos os
  `DiaNaoLetivo` ativos do curso), conforme configuração da escola. Sábado letivo só conta se houver aula.
- **Horas previstas:** soma da duração das aulas convertida em **hora-relógio de 60 minutos**. A
  hora-aula pode durar 45 ou 50 minutos, mas o mínimo anual se mede em hora-relógio (Parecer
  CNE/CEB nº 8/2004).
- **Realizado:** aulas com chamada lançada; projeção do saldo até o fim do período.
- **Mínimos por etapa** em configuração (800 h e 1.000 h; 200 dias).
- Painel **Carga letiva** (grupo Acadêmico), com semáforo por turma: em dia, em risco (a projeção fica
  abaixo do mínimo) e insuficiente.

### Reposição de aulas
- `ReposicaoAula`: aula não dada (falta do professor, evento, clima) vira uma reposição agendada
  (data, horário, professor, motivo). A aula de reposição entra no cronograma marcada como tal, conta no
  realizado e aparece no calendário do Portal.

### Relatório
- Resumo do calendário letivo (dias, horas e reposições) em PDF e CSV para a secretaria de educação ou
  o conselho de educação.

## Regras e limites

- O que vale como "dia de efetivo trabalho escolar" e a duração mínima do dia variam por sistema de ensino
  (conselhos estadual e municipal): tudo em configuração, nada fixo no código.
- Exames finais ficam fora da conta, como no texto da LDB.
- Educação infantil e EJA têm regras próprias; começar por ensino fundamental e médio.

## Dependências

- Calendário e cronograma já existem. A Onda 50 (hora-aula docente) e a Onda 57 (faltas e Conselho
  Tutelar) reaproveitam o total de aulas do ano como denominador.

## Riscos e observações

- Escola que só lança as aulas quando elas acontecem teria previsto igual a realizado: a apuração exige o
  cronograma do ano inteiro.
- A conta é um apoio ao planejamento; a validação do calendário é do órgão do sistema de ensino.

## Esforço

Médio.

## Fusão sugerida

Faz parte do conjunto **"Frequência e carga letiva"**: a [Onda 64](grade_horaria_assistida_roadmap.md) (grade, implementada) dá as aulas que cada turma deveria ter, esta onda confere o total do ano contra o mínimo legal e a [Onda 57](faltas_conselho_tutelar_roadmap.md) usa esse total para calcular o limite de faltas. A [Onda 50](hora_aula_docente_roadmap.md) usa as mesmas aulas para a hora-aula. Ordem: 64, 49, 57 e 50.

Veja o [índice das ondas 68 a 71](ondas_68_a_71_indice.md#fusões-sugeridas-para-dar-completude).
