# Hardening de segurança

Resumo de alto nível das melhorias de segurança aplicadas ao sistema, a partir de uma revisão geral. Serve como referência rápida para quem for mexer nessas áreas depois — não é um relatório de vulnerabilidades.

## Controle de acesso

- Painel administrativo: removido o autorregistro público; acesso ao painel passou por revisão de papéis.
- Edição de template de crachá (`TemplateCrachaV3Controller`): restrita a usuários da equipe (`isStaff()`), não apenas a qualquer conta autenticada.
- Download/visualização de documentos (`VisualizarDocumentoController`, `ValidarDocumentoController`): reforçada a checagem de posse/propriedade antes de servir qualquer arquivo, com sanitização contra path traversal; a busca pública de autenticidade de documento usa só o código de verificação aleatório, e o nome do estudante exibido é mascarado (conformidade LGPD).

## Upload e armazenamento de arquivos

- Uploads de anexo (Central de Atendimento, Chamados) passaram a validar tipo e tamanho de arquivo, e usam disco privado em vez de público.
- Documentos oficiais emitidos pelo sistema passaram a ser armazenados em disco privado.

## Dados em templates e saída de HTML

- Dados de pessoa (nome, CPF, endereço) usados na geração de contratos passaram a ser escapados antes de compor o HTML.
- Dados estruturados embutidos em `<script>` (editor de crachá) passaram a usar `Illuminate\Support\Js::from()` em vez de `json_encode()` cru.

## Autenticação e sessão

- Restaurado o rate limiting nativo do Filament no fluxo de redefinição de senha.
- Política de senha reforçada (tamanho mínimo maior, exigência de maiúscula/minúscula/número/símbolo, e checagem de vazamento conhecido em produção).

## Headers e rede

- Adicionado middleware global de headers de segurança (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security` em HTTPS). Uma política de Content-Security-Policy mais estrita fica para uma etapa futura, já que o painel usa script/style inline em vários pontos e precisa de testes dedicados antes de ativar.
- Rate limiting adicionado nas rotas da API.

## Logs

- Removida a gravação de corpo de requisição/payload bruto em alguns pontos de log, mantendo só os campos necessários para depuração.

## Dependências

- Dependências do Composer e do npm atualizadas dentro das faixas já permitidas pelos arquivos de lock/manifest, resolvendo os avisos de segurança então conhecidos (`composer audit` e `npm audit`). Um pequeno conjunto de avisos de npm permanece em dependências transitivas de uma ferramenta de build que não roda em produção.

## Pendências conhecidas

- Padronizar os models para usar `$fillable` explícito em vez de `$guarded = []` (mudança estrutural ampla, não executada nesta rodada).
- Autenticação de dois fatores para contas administrativas: avaliada e, por ora, não implementada.
- Deploy em ambiente sem Composer/shell: ver nota no `.gitignore` sobre comitar `vendor/` quando necessário.
