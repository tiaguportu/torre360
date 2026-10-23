# Ondas 68 a 71 — Índice

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

## Fusões e ordem sugerida das propostas

1. **70 (central de avisos), idealmente fundida à 33 (comunicados com ciência e enquetes):** as duas precisam do mesmo
   destinatário, do mesmo registro de entrega e do mesmo sino no Portal. Implementar como uma onda só, "Comunicação com
   a família", evita dois mecanismos de entrega. Também fecha a pendência mais visível das ondas 61 e 69: a família
   hoje só descobre o que mudou se abrir a tela certa.
2. **68 (gateway real):** só depois de a escola escolher o provedor e simular as tarifas. Alinha multa e juros do boleto
   aos da Onda 62; o `fake` continua nos testes.
3. **47 (digest diário) vira consumidor da Mesa:** o catálogo de filas que ela pedia já existe
   (`FontePendencia`). Falta só a preferência por usuário, o agendamento e o envio. Veja a nota no documento da Onda 47.

## Ampliações propostas em ondas já existentes

Não são ondas novas: são melhorias de funcionalidades já implementadas ou documentadas.

| Onda | Ampliação | Observação |
|---|---|---|
| 69 (assinatura) | **Lembrete automático** ao assinante que não assinou (reenvio pelo Assinafy após N dias) e **ordem de assinatura** (a escola assina depois da família). | Hoje todos assinam ao mesmo tempo e a cobrança é manual (fila *Assinaturas atrasadas* da Mesa). |
| 69 (assinatura) | Novos tipos de modelo: **autorização de uso de imagem** para quem não usa o Portal (a Onda 25 colhe o aceite lá), **termo de ciência do regimento**, **aditivo de contrato** e **termo de rescisão** de funcionário. | O catálogo de variáveis é um arquivo só: cada contexto novo é uma entrada. |
| 69 (assinatura) | Unificar o envio do **contrato de matrícula** com o novo serviço. | Só vale a refatoração se o fluxo do contrato precisar de algo que o novo já tem (ordem, lembrete). |
| 69 e 66 | A **conferência de cláusulas** (Onda 66) pode ler o texto dos modelos de contrato e de distrato com o mesmo catálogo. | |
| 71 (mesa) | Mais filas: contratos a assinar, **frequência pendente do professor**, ajustes de ponto (Onda 41), pareceres para revisar (Onda 30), reservas (Onda 21), manutenção atrasada (Onda 39), férias vencendo, follow-ups e SLA do CRM, contas a pagar. | Cada fila é uma classe pequena e um teste. |
| 71 (mesa) | **Assumir** um item (quem está cuidando) e adiar com data. | Hoje a Mesa é só leitura e por papel. |
| 61 (cancelamento) | Aviso à família ao aprovar ou recusar. | Entra pela Onda 70. |
| 65 (admissão) | Lembrete semanal das providências vencidas. | A fila da Mesa mostra; o e-mail semanal entra com o digest (Onda 47). |
