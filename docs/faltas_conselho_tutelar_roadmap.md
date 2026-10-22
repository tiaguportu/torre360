# Faltas Excessivas e Comunicação ao Conselho Tutelar (Onda 57) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda.

## Contexto

A LDB manda o estabelecimento de ensino **notificar o Conselho Tutelar do município** com a relação dos
alunos que apresentem faltas **acima de 30% do percentual permitido em lei** (art. 12, VIII, na redação da
Lei nº 13.803/2019). Com a frequência mínima de 75% (art. 24, VI), o percentual permitido de faltas é 25%;
30% disso são **7,5% das aulas do ano**, ou 15 faltas num ano de 200 dias. O ECA (art. 56, II) também manda
comunicar a reiteração de faltas injustificadas e a evasão, **esgotados os recursos escolares**. O sistema
calcula a frequência do aluno e alerta a falta, mas não gera essa relação nem guarda a prova de que a escola
tentou resolver antes.

## O que já existe

- `FrequenciaEscolar` (presente ou ausente por aula, **sem** justificativa) e `FrequenciaAlunoService`
  (percentual e limite de 75%).
- Alerta de falta ao responsável (Onda 3) e o risco de evasão (Onda 13).
- `OcorrenciaEscolar` e os tipos de ocorrência.
- **Falta:** a relação de alunos acima do limite, o ofício, o registro das tentativas de contato com a
  família e o controle do que foi enviado.

## Escopo

### Apuração
- Por aluno: aulas previstas no ano, faltas e percentual; limite de notificação em configuração (padrão
  7,5% das aulas previstas no ano; a Onda 49 fornece o total de aulas).
- Falta justificada (atestado, por exemplo) como marcação nova em `FrequenciaEscolar`. A lei fala em
  faltas; o ECA, em faltas injustificadas: a tela mostra os dois números e a decisão de notificar é da
  direção.

### Fluxo
1. A lista **Alunos acima do limite** aparece para a coordenação com o motivo e o histórico.
2. **Tentativas de contato com a família** registradas (data, meio, resultado): é a prova de que os
   recursos escolares foram esgotados.
3. **Ofício ao Conselho Tutelar** em PDF com a relação mínima (nome, turma, faltas, responsáveis para
   contato), a partir de modelo editável; cadastro do Conselho (município, endereço, e-mail).
4. Controle: enviado em (data), protocolo, resposta do Conselho e encerramento.

## Regras e limites

- Dados mínimos no ofício; base legal de obrigação legal (LGPD, art. 7º, II) e proteção da criança; envio
  e acesso ficam no log.
- O sistema monta a relação e guarda o histórico; o envio é feito pela direção.
- Procedimentos locais (por exemplo, a ficha de comunicação do aluno infrequente adotada por alguns estados
  e pelo Ministério Público) variam: o modelo do ofício é editável.

## Dependências

- Onda 49 (total de aulas do ano) melhora o denominador; sem ela, usa o cronograma lançado.

## Riscos e observações

- Notificar sem tentar contato antes cria conflito com a família; por isso o registro de tentativas faz
  parte do fluxo.
- Educação infantil tem frequência e regras próprias: começar por fundamental e médio.

## Esforço

Pequeno.
