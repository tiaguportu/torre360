# Ondas 61 a 67 — Índice

Continuação do [índice das ondas 49 a 60](ondas_49_a_60_indice.md). Cada onda tem um documento próprio com
contexto, o que já existe no sistema (conferido no código), escopo, base legal, dependências e riscos.

- **Implementadas (4):** 61, 62, 64 e 65.
- **Propostas (2):** 66 e 67. Não há código, migration nem teste delas.
- **Mesclada (1):** a 63 (convivência escolar e bullying) virou uma seção da Onda 35 (ouvidoria e canal de
  denúncias), porque usa o mesmo cadastro de casos e o mesmo comitê. Continua proposta.

## Visão geral

| Onda | Tema | Área | Status | Documento |
|---|---|---|---|---|
| 61 | Cancelamento de Matrícula e Distrato | Secretaria / Financeiro | **Implementada** | [cancelamento_matricula_distrato_roadmap.md](cancelamento_matricula_distrato_roadmap.md) |
| 62 | Encargos por Atraso: Multa e Juros | Financeiro | **Implementada** | [encargos_atraso_roadmap.md](encargos_atraso_roadmap.md) |
| 63 | Convivência Escolar e Prevenção ao Bullying | Conformidade | Mesclada à Onda 35 (proposta) | [ouvidoria_canal_denuncias_roadmap.md](ouvidoria_canal_denuncias_roadmap.md#ampliação-programa-de-convivência-escolar-e-prevenção-ao-bullying) |
| 64 | Grade Horária Assistida | Calendário / Pedagógico | **Implementada** | [grade_horaria_assistida_roadmap.md](grade_horaria_assistida_roadmap.md) |
| 65 | Admissão e Desligamento de Funcionários | RH | **Implementada** | [admissao_desligamento_funcionarios_roadmap.md](admissao_desligamento_funcionarios_roadmap.md) |
| 66 | Conferência de Cláusulas dos Modelos de Contrato | Jurídico / Financeiro | Proposta | [conferencia_clausulas_contrato_roadmap.md](conferencia_clausulas_contrato_roadmap.md) |
| 67 | Loja Escolar Opcional (uniformes e livros) | Operação / Financeiro | Proposta | [loja_escolar_roadmap.md](loja_escolar_roadmap.md) |

## O que mudou no que já existia

Estas ondas corrigiram dois comportamentos antigos. Vale avisar quem usa o sistema:

- **Relatório de Inadimplência:** só listava fatura com status `Atrasado`, que nenhum processo grava. Agora lista
  também `Pendente` e `Parcial` com o vencimento passado, e mostra multa, juros e valor atualizado. Quem estava
  acostumado a ver o relatório vazio vai encontrar a inadimplência real.
- **Conflito na grade horária:** o professor e a sala só conflitam com turmas de períodos letivos que se
  cruzam, e turma cancelada não ocupa ninguém. Antes, a grade do ano passado travava a deste ano.

As permissões novas (`Aprovar` e `Concluir:CancelamentoMatricula`, `ProcessoFuncionario`, `GradeAssistida:Turma`)
são criadas pelas migrations e dadas aos papéis que já existem; um papel criado à mão precisa recebê-las em
**Perfis de acesso**.

## Ordem sugerida das propostas

1. **66 (conferência de cláusulas):** pequena, sem dependência e com efeito imediato na segurança jurídica do
   contrato. Copia o padrão de regras da trava do material (Onda 59).
2. **Programa de convivência (seção da Onda 35):** depende do canal da Onda 35; obrigação legal da escola.
3. **67 (loja escolar):** só depois de decidir a base de produtos com a Onda 17 (cantina) e de a contabilidade
   validar o lado fiscal.

## Ampliações propostas em ondas já existentes

Não são ondas novas: são melhorias de funcionalidades já implementadas ou documentadas.

| Onda | Ampliação | Observação |
|---|---|---|
| 61 (cancelamento) | **Aviso à família** ao aprovar ou recusar o pedido (e-mail ou WhatsApp, pela Onda 33) e **sino de avisos no Portal**. | Hoje a família acompanha só abrindo o Portal. |
| 61 (cancelamento) | **Proporcional do mês da saída** como opção da escola, e cálculo de multa rescisória a partir de uma regra do contrato. | Hoje o mês da saída é cobrado inteiro e a multa é informada à mão. |
| 61 (cancelamento) | **Devolução com saída bancária lançada** em Movimentação Bancária, em vez de só a data. | |
| 62 (encargos) | Reemitir a cobrança no gateway com o valor atualizado (quando houver driver real) e mostrar o valor atualizado na **régua de cobrança**. | Hoje o PIX ou boleto emitido não muda. |
| 62 (encargos) | **Desconto de pontualidade** e correção monetária por índice, como opção do contrato. | |
| 62 e 53 | A **pontuação de risco de inadimplência** (Onda 53) pode usar os dias de atraso e o valor atualizado que a 62 calcula. | |
| 64 (grade) | Sugestão com **sala** (por tipo e capacidade), limite de aulas seguidas e carga semanal por professor, e **trocas** (reposicionar horário existente para encaixar o que sobrou). | Hoje a sugestão é gulosa, sem sala e não mexe no que existe. |
| 64 (grade) | **PDF da grade** por turma e por professor, e disponibilidade por período letivo. | |
| 64 e 50 | A carga de aulas colocada na grade é a base da **apuração de hora-aula** (Onda 50). | |
| 65 (admissão e desligamento) | **Lembrete semanal** das providências vencidas (como o aviso de férias), modelos de lista **por cargo** (professor e administrativo) e termo de confidencialidade assinado pelo Assinafy. | Hoje há só o número no menu. |
| 65 e 10 | O **desligamento** puxar o saldo de férias e do banco de horas para a conferência da rescisão. | |
| 63 (na Onda 35) | Veja a seção "Ampliação: programa de convivência escolar" no documento da Onda 35. | |
| 58 (censo) | Tabela de regra de admissão e etapas equivalentes; turmas com mais de uma etapa no registro 91. | Veja o documento da Onda 58. |
| 59 (material) | Aviso às famílias quando a lista é publicada (Onda 33); lista por turma ou turno. | |
| 60 (documentos da escola) | Incluir **contratos de seguro e de manutenção com vencimento e data de aviso prévio** no painel de validade. | Veja a ampliação no documento da Onda 60. Hoje a onda só trata de documentos regulatórios. |
