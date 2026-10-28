# Ondas 68 a 71 — Índice

> Continua no [índice das ondas 72 a 79](ondas_72_a_79_indice.md).

Continuação do [índice das ondas 61 a 67](ondas_61_a_67_indice.md). Cada onda tem um documento próprio com
contexto, o que já existe no sistema (conferido no código), escopo, base legal, dependências e riscos.

- **Implementadas (2):** 69 e 71.
- **Propostas (2):** 68 e 70. Não há código, migration nem teste delas.

## Visão geral

| Onda | Tema | Área | Status | Documento |
|---|---|---|---|---|
| 68 | Gateway de Pagamento Real (Asaas e Efí) | Financeiro | Proposta | [gateway_pagamento_real_roadmap.md](gateway_pagamento_real_roadmap.md) |
| 69 | Assinatura Eletrônica de Documentos e Editor de Modelos | Secretaria / RH / Jurídico | **Implementada** | [assinatura_eletronica_documentos_roadmap.md](assinatura_eletronica_documentos_roadmap.md) |
| 70 | Central de Avisos à Família | Comunicação / Portal | Proposta | [central_avisos_familia_roadmap.md](central_avisos_familia_roadmap.md) |
| 71 | Mesa de Pendências por Papel | Transversal | **Implementada** | [mesa_pendencias_roadmap.md](mesa_pendencias_roadmap.md) |

## O que mudou no que já existia

Estas ondas alteraram alguns comportamentos. Vale avisar quem usa o sistema:

- **Modelos de Documentos:** cada tipo agora tem o seu contexto de dados (aluno, cancelamento ou funcionário) e o editor
  **recusa ao salvar** uma variável que não existe para o tipo, ou escrita com espaço dentro das chaves
  (`{{ ALUNO_NOME }}`). Um modelo antigo com variável errada passa a falhar na **próxima vez que for salvo**; o erro
  lista quais são. Antes, a variável errada saía impressa no documento.
- **Emissão de declarações** (secretaria e Portal): lista só os modelos de aluno. O distrato e os documentos de
  funcionário não saem por ali.
- **Distrato (PDF):** quando existe um modelo ativo de "Distrato de Matrícula", é o texto dele que sai; sem modelo, o
  texto fixo de antes.
- **Webhook do Assinafy:** o mesmo endereço atende contratos e documentos avulsos. Nada muda no fluxo do contrato.
- **Menu:** *Minha Mesa* no topo, e *Documentos para Assinatura* no grupo Secretaria. A Mesa só aparece para quem tem
  permissão para ao menos uma fila.

As permissões novas (`ViewAny`, `View`, `Create`, `Enviar` e `Cancelar:DocumentoAssinatura`) são criadas pela migration e
dadas a administração, secretaria (todas) e coordenação (consulta); um papel criado à mão precisa recebê-las em
**Perfis de acesso**. A Mesa **não** tem permissão própria: cada fila usa a permissão do módulo que a alimenta.

## Fusões sugeridas para dar completude

Algumas propostas se sobrepõem em modelo de dados, tela ou público. Implementá-las separadas cria dois mecanismos para
a mesma coisa; juntas, sai um só. Cada documento afetado tem uma seção "Fusão sugerida" com o detalhe.

| Conjunto | Ondas | O que compartilham | Ordem dentro do conjunto |
|---|---|---|---|
| **Comunicação com a família** | 33 + 70 | Destinatário, registro de entrega e sino no Portal. O aviso transacional (cancelamento aprovado, documento para assinar) e o comunicado com ciência rastreada viram dois tipos do mesmo modelo. | Modelo de destinatário e entrega, aviso transacional, comunicado com ciência, enquete |
| **Frequência e carga letiva** | 64 (feita) + 49 + 57 (+ 50) | A grade dá as aulas de cada turma; a 49 confere o total do ano contra o mínimo legal; a 57 usa esse total para o limite de faltas; a 50 usa as mesmas aulas para a hora-aula. | 64, 49, 57, 50 (a 57 pode ir antes com o total do calendário) |
| **Vencimentos** | 56 + 60 + painel de férias (Onda 10, feito) | Cadastro com data de vencimento, painel e aviso semanal. Um cadastro genérico com tipo, responsável e antecedência atende as três; cada tipo vira uma fila da Mesa. | Cadastro genérico, 60, 56, migrar as férias |
| **Casos com prazo legal** | 34 + 35 (+ 63) | Caso com protocolo, responsável, prazo legal, sigilo, histórico e resposta. Pedido do titular (LGPD, art. 19, II), denúncia e convivência escolar (Lei nº 13.185/2015) são três tipos de caso. | Motor de casos, 34, 35, 63 |
| **Ciclo pedagógico** | 27 + 28 + 13 (feita) | O conselho decide encaminhamentos por aluno, o reforço os executa e o score de evasão deixa de ser número e vira ação com medida de efetividade. | 27, 28, ação sobre o risco de evasão |
| **Vendas da escola** | 17 + 67 | Produto, estoque, caixa e forma de cobrança. A cantina e a loja são dois pontos de venda; o cruzamento de alérgenos (Onda 40) vale para os dois. | Cadastro único, 17, 67 |
| **Portaria e saída** | 9 + 37 | Mesmo fluxo visto de dois lados: autorizados, registro de saída e "quem está na escola agora". | Juntas |
| **Cobrança real, risco e encargos** | 68 + 53 (+ 62, feita) | O gateway dá o pagamento sem depender de extrato; a 62 dá dias de atraso e valor atualizado; a 53 usa os dois. Multa e juros do boleto saem das regras do contrato. | 68, 53 |
| **Pendências e resumo diário** | 71 (feita) + 47 | A Mesa já tem o catálogo de filas que a 47 pedia. O digest é outro consumidor das mesmas filas. | 47 depois das preferências por usuário |

### Ordem sugerida de entrega

1. **Comunicação com a família (33 + 70).** Fecha a pendência mais visível das ondas 61 e 69: hoje a família só descobre o
   que mudou se abrir a tela certa. É a base do digest e dos avisos de falta excessiva.
2. **Frequência e carga letiva (57, com a 49).** A 57 é obrigação legal e usa dados que já existem.
3. **Conformidade:** 34 (com o motor de casos) e 46 (MFA e retenção de logs), que já têm pendências abertas na revisão de
   segurança.
4. **Vencimentos (60 e 56).** Copiam o painel de férias e entregam fila na Mesa quase sem código novo.
5. **68 (gateway real),** só depois de a escola escolher o provedor e simular as tarifas.
6. **Pedagógico (27 e 28)** e, em seguida, o resto da lista das ondas 27 a 67.
7. **66 (conferência de cláusulas dos contratos)**, pequena e sem dependência, pode entrar a qualquer momento; lê o texto
   dos modelos pelo mesmo catálogo de variáveis da Onda 69.

## Melhorias em ondas implementadas (achados da verificação de 2026-10-23)

| Onda | Achado | Correção sugerida |
|---|---|---|
| 61 e 65 | O `Gate::before` do aplicativo dá `true` ao super admin para **qualquer** habilidade, inclusive métodos de policy. Por isso `can('aprovar', $pedido)` era verdadeiro mesmo com o pedido já aprovado. | **Corrigido em 2026-10-23:** As telas conferem explicitamente o estado do registro (`$record->estaSolicitado()`, `$record->estaAprovado()`, `$record->devolucaoPendente()` e `$this->getRecord()->estaEmAndamento()`) em conjunto com a autorização. Coberto com testes de `super_admin` em `CancelamentoMatriculaTest` e `ProcessoFuncionarioTest`. |
| Transversal | `VisualizarDocumentoController` abria para a equipe administrativa qualquer arquivo de pasta sem regra própria (M10 de `seguranca_pendencias.md`). | **Corrigido em 2026-10-23:** Aplicada lista branca estrita de pastas administrativas autorizadas com padrão fail-closed (pasta sem regra catalogada retorna 403 Forbidden). Coberto com teste em `AcessoDocumentosEquipeTest`. |
| 62 | `Matricula::hasDebitosVencidos()` repete a regra de `Fatura::emAtraso()`. | Passar a usar o escopo. |
| 71 | Faltam filas de outros módulos. | Contratos a assinar, frequência pendente do professor, ajustes de ponto (Onda 41), pareceres (Onda 30), reservas (Onda 21), manutenção (Onda 39), férias vencendo, follow-ups do CRM, contas a pagar. |
| Engenharia | O SQLite dos testes esconde erros que só aparecem no MySQL (agregado com `orderBy`, coluna inexistente). Seis classes rodam o `RolesSeeder` em todo teste (40 a 75 s cada). | Rodar a suíte também em MariaDB no CI; semear papéis uma vez por processo. |

## Ampliações propostas em ondas já existentes

Não são ondas novas: são melhorias de funcionalidades já implementadas ou documentadas.

| Onda | Ampliação | Observação |
|---|---|---|
| 69 (assinatura) | **Lembrete automático** ao assinante que não assinou (reenvio pelo Assinafy após N dias) e **ordem de assinatura** (a escola assina depois da família). | Hoje todos assinam ao mesmo tempo e a cobrança é manual (fila *Assinaturas atrasadas* da Mesa). |
| 69 (assinatura) | Novos tipos de modelo: **autorização de uso de imagem** para quem não usa o Portal (a Onda 25 colhe o aceite lá), **termo de ciência do regimento**, **aditivo de contrato** e **termo de rescisão** de funcionário. | O catálogo de variáveis é um arquivo só: cada contexto novo é uma entrada. |
| 69 (assinatura) | Unificar o envio do **contrato de matrícula** com o novo serviço. | Só vale a refatoração se o fluxo do contrato precisar de algo que o novo já tem (ordem, lembrete). |
| 69 e 66 | A **conferência de cláusulas** (Onda 66) pode ler o texto dos modelos de contrato e de distrato com o mesmo catálogo. | |
| 71 (mesa) | Mais filas: contratos a assinar, **frequência pendente do professor**, ajustes de ponto (Onda 41), pareceres para revisar (Onda 30), reservas (Onda 21), manutenção atrasada (Onda 39), férias vencendo, follow-ups do CRM (o SLA do CRM, que chegou a virar fila, foi retirado em 2026-10-28), contas a pagar. | Cada fila é uma classe pequena e um teste. |
| 71 (mesa) | **Assumir** um item (quem está cuidando) e adiar com data. | Hoje a Mesa é só leitura e por papel. |
| 61 (cancelamento) | Aviso à família ao aprovar ou recusar. | Entra pela Onda 70. |
| 65 (admissão) | Lembrete semanal das providências vencidas. | A fila da Mesa mostra; o e-mail semanal entra com o digest (Onda 47). |
