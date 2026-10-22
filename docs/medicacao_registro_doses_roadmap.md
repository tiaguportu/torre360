# Medicação com Registro de Doses (Onda 55) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda.

## Contexto

A ficha médica do aluno guarda os medicamentos autorizados, com dosagem, horário e instruções. A escola
precisa saber **o que dar, a quem e a que horas** e provar **quem deu e quando**. Hoje o horário é um texto
e a dose dada não fica registrada: se a família perguntar às 15h se o remédio das 12h foi dado, ninguém
tem como responder pelo sistema.

## O que já existe

- `FichaMedica` e `MedicamentoAluno` (nome, dosagem, `horario_administracao` em texto, instruções,
  `autorizado_responsaveis`, receita em arquivo privado).
- `AtendimentoEnfermagem` (data e hora, queixa, procedimento, `medicamento_ministrado` em texto livre,
  `notificado_responsaveis`).
- As regras de acesso a dado de saúde (`docs/lgpd_saude_policies.md`).
- **Falta:** horários estruturados, agenda do dia e o registro de cada dose.

## Escopo

### Dados
- Horários estruturados no medicamento (lista de horas), período de uso (início e fim), validade da
  receita e quantidade trazida pela família.
- `DoseAdministrada`: medicamento, data e hora prevista, estado (dada, recusada, não dada), quem
  administrou, hora real, observação e reação.

### Telas
- Painel da enfermagem **Doses de hoje**: lista por horário, com atraso em destaque e ação "Registrar dose".
- Aviso de dose próxima e de dose atrasada; aviso ao responsável quando a dose é dada ou não pôde ser dada
  (configurável por medicamento).
- Aviso de receita vencendo e de medicamento acabando.
- Ficha do aluno: histórico das doses.

## Regras e limites

- É dado de saúde de criança (LGPD, arts. 11 e 14): campos cifrados, log com o fluxo e sem conteúdo,
  acesso só à enfermagem e à coordenação, como já é na ficha médica.
- O sistema **registra e lembra**; não decide dose nem substitui o protocolo da escola. Quem pode
  administrar o quê depende do protocolo definido com o responsável técnico e da orientação do conselho
  profissional: a escola define e o sistema só aplica a autorização cadastrada.
- Medicamento sem autorização dos responsáveis ou com receita vencida não gera dose.

## Dependências

- Ficha médica e enfermagem implementadas. Combina com a Onda 46 (criptografia dos dados de saúde).

## Riscos e observações

- Erro de horário ou de dose tem consequência: confirmar sempre pelo nome do aluno e pelo medicamento
  antes de registrar, e travar o registro de dose duplicada no mesmo horário.

## Esforço

Médio.
