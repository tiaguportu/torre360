# Canal WhatsApp Oficial (Onda 88) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade. Faz parte do [índice das ondas 80 a 88](ondas_80_a_88_indice.md).
> **Depende de uma decisão fora do código:** escolher o provedor e contratar (custo mensal e por mensagem).

## Contexto

A família responde por WhatsApp; e-mail é o canal que ela menos olha. O sistema já foi desenhado para isso (a régua e a comunicação em
massa têm "canal" como conceito), mas o WhatsApp de hoje é um **link que a equipe clica**: o consultor abre uma conversa pelo
próprio aparelho e o sistema não vê o que foi dito. Não há envio automático, confirmação de entrega, caixa de conversas
compartilhada nem registro do que a escola prometeu.

## O que já existe (conferido no código)

- **O contrato do canal:** `App\Contracts\CanalMensagem` (chave, rótulo, `disponivelPara(Pessoa)`, `enviar(Pessoa, assunto, corpo)`) e o
  `CanalMensagemManager`, com `email` e `fcm` (push). O comentário do contrato já diz "e futuramente WhatsApp/SMS". Quem usa: a
  comunicação em massa do CRM.
- **A régua de follow-up** tem seu próprio canal (`CanalReguaFollowUp`: e-mail e notificação do sistema) e gatilhos (lead criado, visita,
  lead estagnado, contato atrasado). O documento do funil lista "WhatsApp como canal da régua" como fora do escopo dos primeiros lotes.
  A **régua de cobrança** também tem `canal` (e-mail, notificação ou todos), mas dispara pelo `NotificationService`. Ou seja, o conceito
  de canal hoje existe **três vezes** (o `CanalMensagemManager`, o enum da régua de follow-up e a cobrança), sem que um use o outro.
- **WhatsApp manual:** links `wa.me` e `api.whatsapp.com/send` na lista de leads, na proposta comercial e no **repasse de lead ao
  consultor** (`ConsultorWhatsappService`); **mensagens rápidas** (`MensagemWhatsappTemplate`: nome, texto, instruções para a IA) e o
  **Copiloto IA** que redige a mensagem; **importação de conversa** exportada em ZIP com resumo por IA (`ConversaWhatsappZipService`).
- **Consentimento:** `Pessoa.aceita_comunicacao` (um só campo, sem distinguir canal), o registro de consentimento do CRM (`crm.lgpd`) e o
  descadastro de e-mail (`DescadastroController`).
- **Nenhuma integração com provedor:** a busca por Cloud API, Twilio, Z-API e similares no código e na configuração não acha nada.
  Não existe webhook de entrada de mensagem (`routes/api.php` só recebe Assinafy e pagamento).
- O que o sistema precisa **registrar** quando o consultor conversa: o histórico de contato (`HistoricoContato`), a **próxima ação
  obrigatória** (Onda 84) e a **data do primeiro contato** (`data_primeiro_contato`; o SLA de primeira resposta que a usava foi
  retirado em 2026-10-28). Uma mensagem enviada pelo canal tem de contar para os três.

## Escopo proposto

### Provedor e número
- Decidir entre a **API oficial da Meta (Cloud API)** direto e um **provedor intermediário**; o código fica atrás de uma interface, então
  trocar de provedor é trocar uma classe. O número da escola precisa ser **migrado para a API**: a partir daí o aplicativo comum
  naquele número deixa de funcionar do jeito atual (decisão que a secretaria precisa tomar antes).

### Canal e modelos
- `WhatsappCanal` implementa `CanalMensagem` (envio de **modelo aprovado** com variáveis) e entra no `CanalMensagemManager`; a régua
  de follow-up e a comunicação em massa ganham a opção.
- `ModeloWhatsapp`: nome, categoria (utilidade, marketing, autenticação), idioma, corpo com variáveis e **situação da aprovação** (que o
  provedor devolve). Os modelos de mensagem rápida de hoje continuam para a conversa dentro da janela de atendimento.
- **Regra das 24 horas:** fora da janela aberta pela resposta do contato, só modelo aprovado; dentro dela, texto livre. A caixa mostra
  quanto resta.

### Entrada e caixa de conversas
- **Webhook** de mensagens e de situação (enviada, entregue, lida, falhou), com assinatura conferida, idempotência e fila. Grava
  `ConversaWhatsapp` e `MensagemWhatsapp`, ligadas por **telefone normalizado** à `Pessoa`, ao lead e ao responsável.
- **Caixa de conversas** (CRM e Secretaria): lista por contato, atribuição (quem atende), situação, respostas rápidas, anexos e o
  histórico do lead ao lado. Mensagem de número desconhecido vai para a triagem, **sem criar lead sozinha**.
- Cada mensagem humana alimenta o **histórico de contato**, a **próxima ação** e a **data do primeiro contato** (a primeira resposta por WhatsApp conta).

### Consentimento, limites e custo
- **Opt-in por canal** (novo campo, separado do e-mail) com prova (data, origem, texto); **opt-out** por palavra ("SAIR") ou pela
  ficha, respeitado antes de qualquer envio.
- Limite de mensagens por contato por dia e **horário de silêncio**; modelo de marketing nunca para quem não consentiu.
- **Teto de custo** mensal por categoria e relatório de consumo (fonte da Onda 72). Estourou o teto, o envio automático para e a gestão
  é avisada.

### Saída para as outras ondas
Canal de entrega da [Central de Avisos (70)](central_avisos_familia_roadmap.md), da [ciência de comunicados (33)](comunicados_ciencia_enquetes_roadmap.md)
e da [cobrança (68)](gateway_pagamento_real_roadmap.md), sem que cada uma monte o seu.

## Base legal

- **LGPD, art. 7º, I e art. 8º** (consentimento específico), **art. 18, IX** (revogação, que o "SAIR" precisa cumprir) e **art. 33**
  (**transferência internacional**: o provedor e a Meta tratam o dado fora do Brasil; é preciso base legal, contrato de operador e
  informação ao titular).
- **Política de uso comercial do WhatsApp** (exige consentimento prévio e conta verificada): descumprir derruba a qualidade do número e
  pode suspendê-lo.
- **CDC, art. 42**: cobrança não pode expor a família a constrangimento; modelo de cobrança no WhatsApp passa pelo mesmo cuidado da
  [régua de cobrança](ondas_61_a_67_indice.md).

## Dependências e riscos

- **Contratar o provedor é pré-requisito**, assim como a verificação do negócio e a aprovação dos modelos (leva de horas a dias).
- **Não gravar dado de criança em mensagem de modelo** além do estritamente necessário; o conteúdo trafega por terceiros.
- **Mídia** (foto, PDF) é baixada do provedor dentro do prazo dele e guardada em armazenamento privado, com a mesma lista de tipos de
  arquivo do resto do sistema.
- **Mensagem duplicada ou fora de ordem:** o webhook repete e atrasa; a gravação precisa ser idempotente.
- **Adoção:** a equipe está habituada ao aplicativo no celular; a caixa precisa ser mais rápida que o `wa.me` para pegar.
- Entregar em fatias: primeiro **envio de modelo** (lembrete de visita e de vencimento), depois a **caixa**, por último a régua inteira.

## Fusão sugerida

**Comunicação e canal (88 + 70 + 33 + 47).** O WhatsApp não é uma onda de CRM: é um canal da escola inteira. Unificar os três "canais" atuais
numa peça só é o primeiro passo. Destinatário, consentimento,
entrega e ciência são os mesmos para o aviso, o comunicado, a cobrança e a régua; implementar o canal como peça comum e deixar cada onda
só escolher mensagens e momentos. Ver o [índice](ondas_80_a_88_indice.md#fusões-sugeridas).
