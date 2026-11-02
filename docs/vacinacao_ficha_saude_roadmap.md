# Carteira de Vacinação e Ficha de Saúde Atualizada (Onda 103) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

A ficha de saúde é preenchida na matrícula e raramente revisada: a criança ganha uma alergia, troca de plano de saúde ou muda de hospital de preferência, e a ficha segue com o dado
velho, justamente o que a enfermagem consulta na emergência. E a **carteira de vacinação** costuma ser exigida na matrícula, mas fica arquivada como foto, sem controle do que está
em dia nem do que vence.

## O que já existe (conferido no código)

- `FichaMedica` (por pessoa): tipo sanguíneo, alergias alimentares (lactose, glúten, amendoim e outras), plano de saúde, carteira do SUS, hospital de preferência e observações
  gerais, **com os campos de texto criptografados**. `AtendimentoEnfermagem`, `MedicamentoAluno` e `ContatoEmergencia`.
- **Documentos da matrícula:** `TipoDocumento` (por curso, com categoria de exigência e obrigatoriedade) e a análise de documento por IA (`DocumentoIaService`), que **já reconhece a
  `carteira_vacinacao`** no envio. O resultado é um arquivo classificado; não há registro por vacina.
- O cardápio cruza a ficha médica com os alérgenos (Onda 40). A matriz de acesso a dado de saúde está em `lgpd_saude_policies.md`.
- O padrão de **vencimento com painel e aviso** existe nas férias (Onda 10), nos documentos da escola (Onda 60) e na Mesa de Pendências (Onda 71).
- **Não existe** registro de vacina, data de revisão da ficha nem fila de ficha desatualizada.

## Escopo proposto

### Ficha com validade
- Campo **revisada em** (e por quem) na ficha. A ficha com revisão há mais de 12 meses, ou que nunca foi revisada, entra na fila *Fichas de saúde a atualizar* da Minha Mesa e gera o pedido de
  conferência à família (**Portal:** "revise os dados de saúde de [aluno]", com o que mudou destacado). Revisar não precisa reescrever tudo: "nada mudou" é uma ação.
- A atualização feita pela família passa por conferência da enfermagem antes de valer (dado de saúde).

### Vacinas
- `VacinaAluno`: aluno, vacina (lista oficial editável), dose, data, comprovante e conferido por. Esquema de referência (calendário nacional) cadastrado por faixa etária, para o sistema indicar
  **dose em atraso** e **próxima dose** com base na idade.
- Situação por aluno: em dia, pendente, dispensa por laudo médico. A escola **registra o que o comprovante mostra** e não atesta calendário vacinal.
- Fila *Vacinação pendente* e relatório por turma (sem nome ao público), útil em surto.

### Regras
- A decisão sobre a matrícula de quem está com vacina pendente é da escola e das normas da rede; o sistema **mostra a pendência e nunca bloqueia automaticamente** a matrícula
  nem a rematrícula.
- Acesso: enfermagem, secretaria e coordenação, na mesma matriz de `lgpd_saude_policies.md`. O Portal mostra à família só a situação, e não o detalhe de outras crianças.

## Base legal

- **ECA, art. 14, § 1º:** vacinação obrigatória nos casos recomendados pelas autoridades sanitárias. A exigência do comprovante **na matrícula** varia por rede e por município:
  confirmar com a secretaria de educação e o jurídico antes de transformar em regra do sistema.
- **LGPD, art. 11** (dado sensível de saúde) e **art. 14** (criança): finalidade de proteção da saúde, acesso mínimo e criptografia (já aplicada na ficha).

## Dependências e riscos

- Calendário nacional muda: a tabela de referência precisa de dono e de data de atualização, ou o sistema aponta atraso que não existe.
- Digitar vacina por vacina é trabalho para a secretaria: começar pelo comprovante anexado e pela conferência da IA que já existe, e deixar o registro por dose como fase 2.
- Risco de discriminação: o relatório de vacina não identifica criança fora da enfermagem e da coordenação.

## Esforço

Pequeno (ficha com validade) a médio (registro por vacina).

## Pacote

**P4 (Cuidado com o aluno)**, com o [registro de doses (Onda 55)](medicacao_registro_doses_roadmap.md) e o [aviso de enfermaria (Onda 93)](aviso_enfermaria_familia_roadmap.md), e o padrão de vencimento da
**P3**. Ver a [proposta de unificação](unificacao_de_ondas_proposta.md).
