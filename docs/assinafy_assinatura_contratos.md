# Assinatura digital de contratos (Assinafy): status, webhook e reconciliação

## Resumo
O status de assinatura fica em `contrato.assinafy_status` e é atualizado por dois caminhos:
1. **Webhook** do Assinafy (`POST /api/webhooks/assinafy`), tratado por `AssinafyService::handleWebhook()`.
2. **Consulta ativa** à API (`AssinafyService::consultarEAtualizarStatusSignatarios()`), usada pelo botão
   **Sincronizar Assinaturas** e pelo comando agendado `assinafy:reconciliar`.

Referência oficial: [SDK PHP do Assinafy](https://github.com/assinafy/php-sdk) (catálogo de eventos e status).

## Sintoma que originou esta mudança
O contrato foi assinado no Assinafy, mas o Torre360 continuava mostrando "não assinado" (e a pendência
**Contrato não assinado** na lista de matrículas). Causas:
- O evento que o Assinafy envia quando **todos assinam** é `document_ready` (status do documento `ready`), mas o código
  o gravava como `ready` e só considerava assinado `signed`/`completed` (nomes que o Assinafy não usa).
- A consulta ativa só gravava o status se a API devolvesse `signed`/`completed`, o que nunca acontece (os estados finais
  são `ready`, `certificating` e `certificated`).
- Não havia rotina para recuperar webhooks perdidos, e os testes usavam um evento inexistente (`document_signed`).

## Status no Torre360 (`StatusAssinaturaContrato`)
| Valor | Rótulo | Significado | Conta como assinado? |
|---|---|---|---|
| `pendente` | Não enviado | Contrato criado, ainda não enviado ao Assinafy (padrão da coluna) | não |
| `pending`, `enviado` | Pendente | Enviado, aguardando assinaturas | não |
| `ready` | Todos assinaram | Todas as assinaturas coletadas (`document_ready`) | **sim** |
| `certificating` | Certificando | Certificado digital em geração | **sim** |
| `certificated` | Certificado | Certificado gerado (estado final) | **sim** |
| `signed`, `completed` | Assinado | Valores legados | **sim** |
| `rejected` / `refused` | Recusado | Um signatário recusou | não |
| `canceled` | Cancelado | Dono do documento cancelou | não |
| `expired` | Expirado | Prazo de assinatura vencido | não |
| `erro_envio` | Erro no envio | Falha ao enviar/processar | não |

`ready`, `certificating` e `certificated` são **status distintos** (exibidos com rótulos próprios) e todos significam
"todas as assinaturas foram coletadas". A lista única de status assinados é `Contrato::STATUS_ASSINADO`; use
`Contrato::estaAssinado()` e os scopes `Contrato::assinado()` / `Contrato::naoAssinado()` em vez de repetir listas.

## Eventos do webhook
| Evento | Efeito |
|---|---|
| `document_ready` | Todos assinaram → `ready` (depois a API confirma a etapa real) |
| `signer_signed_document` | Assinatura individual: grava o signatário como assinado; o contrato segue `enviado` |
| `signature_requested`, `document_uploaded` | `enviado` |
| `signer_rejected_document` | `rejected` |
| `user_rejected_document` | `canceled` |
| `document_processing_failed` | `erro_envio` |
| demais (visualização, e-mail verificado, metadados...) | **informativos**: só atualizam `assinafy_request_log` |

Regras:
- **Nunca regride:** um contrato já assinado só avança (`ready` → `certificating` → `certificated`); eventos atrasados ou
  repetidos não o fazem voltar nem trocam a `data_aceite`.
- **`data_aceite` = quando a última assinatura foi coletada.** No webhook usa o `created_at` do envelope do evento
  `document_ready`; na consulta ativa usa a data da última assinatura informada pelo Assinafy (se não houver, a hora da
  conclusão detectada). É gravada ao passar a "assinado" e, na consulta ativa, corrigida pela data real das assinaturas.
  Obs.: contratos criados pelo Assistente de Matrícula ou pela ação **Gerar contrato** recebem `data_aceite = agora` na
  criação; ao serem assinados esse valor é substituído pela data da conclusão das assinaturas. Os contratos da
  **Rematrícula Online** já nascem **sem** `data_aceite` (ela só passa a existir na assinatura); as faturas deles partem
  do dia da rematrícula, então a assinatura não altera os vencimentos.
- **O webhook do Assinafy não é assinado** ("The envelopes do not have cryptographic signature"). Por isso, quando há
  credenciais da API, a conclusão só é aceita **depois de consultar o documento na API**; se a API não confirmar (ou
  estiver fora do ar), o status é mantido e a reconciliação resolve depois. O HMAC (`ASSINAFY_WEBHOOK_SECRET`) só é
  verificado quando o remetente envia o header `X-Assinafy-Signature`; exigi-lo sempre rejeitaria todos os retornos.
- A rematrícula online é confirmada quando o contrato atinge qualquer status "assinado".

## Reconciliação (rede de segurança)
`php artisan assinafy:reconciliar` consulta o Assinafy e atualiza os contratos ainda em andamento
(`enviado`/`pending`/`ready`/`certificating`, com `assinafy_id`). Roda **de hora em hora** (`routes/console.php`).
Opções:
- `--contrato=ID` consulta um contrato específico.
- `--limite=N` máximo de contratos por execução (padrão 100).
- `--incluir-assinados` reprocessa também os já assinados/certificados para **corrigir a `data_aceite`** pela data da
  última assinatura. Use uma vez para reparar dados antigos.

### Reparar contratos antigos já assinados (executar no servidor de produção)
Contratos que o tratamento antigo deixou em `ready` já contam como assinados, mas podem ter `data_aceite` incorreta e
ainda não estar `certificated`. Depois do deploy:
```
php artisan assinafy:reconciliar --incluir-assinados
```
O comando mostra uma tabela com o status antes/depois de cada contrato. Para um único contrato:
`php artisan assinafy:reconciliar --contrato=166 --incluir-assinados`.

## Diagnóstico se um contrato assinado não atualizar
1. Veja o badge **Assinatura** em `/admin/contratos`: **Pendente** indica que o retorno não chegou/não foi confirmado.
2. Procure no `storage/logs` por `Webhook Assinafy recebido`, `Processando webhook Assinafy` e
   `conclusão não confirmada pela API`.
3. Confirme no painel do Assinafy que o webhook aponta para `https://<dominio>/api/webhooks/assinafy`.
4. Use **Sincronizar Assinaturas** (ou `assinafy:reconciliar --contrato=ID`).

## Cuidado nos testes
O `.env` do ambiente local pode ter credenciais reais do Assinafy. Testes que passam por `AssinafyService` devem
neutralizar a chave (`config(['services.assinafy.key' => ''])`) e usar `Http::preventStrayRequests()`; veja
`AssinafyAssinaturaTest`. Em `Http::fake()` a primeira resposta que casa vale (as definições se acumulam), então
redefina com um `Http` novo ao trocar a resposta no meio do teste.
