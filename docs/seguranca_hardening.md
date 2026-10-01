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

## Mass assignment nos models

- Todos os models que usavam `protected $guarded = [];` passaram a declarar `protected $fillable` explícito, listando só as colunas reais de cada tabela (introspecção feita contra o schema migrado, não contra um banco de desenvolvimento potencialmente desatualizado). Dois models (`Coordenador`, `TributacaoCurso`) ficaram de fora porque já apontam para um nome de tabela que não existe no banco atual — bug pré-existente, sem relação com esta mudança, não corrigido aqui.
- Efeitos colaterais corrigidos junto:
  - Os importadores de CSV (`ContratoImporter`, `PessoaImporter`) dependiam de mass-assignar `id` para reimportar/atualizar um registro por ID explícito; passaram a setar o `id` diretamente em vez de via array.
  - `EducacensoPessoaExporter`: um bug pré-existente (lia `nome` diretamente em models que só têm esse dado através da relação `categoria`) ficou exposto porque o `$guarded = []` anterior mascarava o erro ao aceitar um atributo `nome` que não existe de fato nessas tabelas. Corrigido para ler via `categoria.nome`, com os eager loads correspondentes.

## Pendências conhecidas

- Autenticação de dois fatores para contas administrativas: avaliada e, por ora, não implementada.
- Deploy em ambiente sem Composer/shell: ver nota no `.gitignore` sobre comitar `vendor/` quando necessário.
- Corrigir o nome de tabela de `Coordenador` e `TributacaoCurso` (ver seção acima).
- Uma Content-Security-Policy mais estrita ainda não foi ativada (ver seção "Headers e rede").
