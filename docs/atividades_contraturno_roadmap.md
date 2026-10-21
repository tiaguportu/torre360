# Atividades do Contraturno (Onda 31) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Escolas particulares oferecem oficinas eletivas no contraturno (judô, robótica, xadrez, teatro,
reforço), com vagas limitadas, professor próprio e cobrança à parte. Hoje a inscrição é por
formulário ou WhatsApp e a cobrança é lançada à mão.

## O que já existe (ponto de partida)

- `Turma`, `Sala`, `GradeHorario` e `ReservaEspaco` (Onda 21): espaço e horário sem conflito.
- `Contrato`, `Fatura`, `ItemFatura`, `GeracaoFaturasContratoService`, `TipoBolsa`/`BolsaConcedida`:
  cobrança recorrente e descontos.
- `FrequenciaEscolar` (presença por aula) e Portal da Família (inscrição e acompanhamento).
- `Funcionario` (professor/instrutor) e `SubstituicaoProfessor`.
- Não há **oferta eletiva**, inscrição com vagas, lista de espera por atividade nem cobrança ligada.

## Escopo

### Modelos novos
- `AtividadeContraturno`: nome, descrição, instrutor, dias/horários, sala, faixa etária ou séries,
  vagas, preço mensal, período.
- `InscricaoAtividade`: `matricula_id`, atividade, status (inscrito, lista de espera, cancelado),
  datas, vínculo com a cobrança (item de fatura recorrente).
- `PresencaAtividade`: aula, aluno, presença.

### Telas
- Cadastro da atividade (secretaria/coordenação) e reserva automática da sala.
- Portal da Família: catálogo, inscrição e cancelamento (com regra de antecedência).
- Lista de espera com promoção automática quando abre vaga.
- Chamada do instrutor e relatório de ocupação e de receita.

### Regras
- Inscrição gera item de cobrança a partir do mês de início; cancelamento interrompe no mês seguinte.
- Conflito de horário do aluno com a grade regular ou com outra atividade é recusado.
- Aluno com bolsa pode ter desconto configurável por atividade.

## Dependências já satisfeitas

- Faturamento recorrente, reserva de espaço e portal.

## Risco e observação

- Saída do aluno no contraturno: integrar com o controle de saída/portaria (Ondas 9 e 37).
- Seguro e atestado médico para atividades físicas: campo de exigência por atividade.
