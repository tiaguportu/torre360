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

- Adicionado middleware global de headers de segurança (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security` em HTTPS). A Content-Security-Policy entrou na segunda rodada (ver "Content-Security-Policy" abaixo): a camada base está ativa e a estrita roda em modo `report-only` até ser validada nas telas reais.
- Rate limiting adicionado nas rotas da API.

## Logs

- Removida a gravação de corpo de requisição/payload bruto em alguns pontos de log, mantendo só os campos necessários para depuração.

## Dependências

- Dependências do Composer e do npm atualizadas dentro das faixas já permitidas pelos arquivos de lock/manifest, resolvendo os avisos de segurança então conhecidos (`composer audit` e `npm audit`). Um pequeno conjunto de avisos de npm permanece em dependências transitivas de uma ferramenta de build que não roda em produção.

## Mass assignment nos models

- Todos os models que usavam `protected $guarded = [];` passaram a declarar `protected $fillable` explícito, listando só as colunas reais de cada tabela (introspecção feita contra o schema migrado, não contra um banco de desenvolvimento potencialmente desatualizado).
- Efeitos colaterais corrigidos junto:
  - Os importadores de CSV (`ContratoImporter`, `PessoaImporter`) dependiam de mass-assignar `id` para reimportar/atualizar um registro por ID explícito; passaram a setar o `id` diretamente em vez de via array.
  - `EducacensoPessoaExporter`: um bug pré-existente (lia `nome` diretamente em models que só têm esse dado através da relação `categoria`) ficou exposto porque o `$guarded = []` anterior mascarava o erro ao aceitar um atributo `nome` que não existe de fato nessas tabelas. Corrigido para ler via `categoria.nome`, com os eager loads correspondentes.
  - `Coordenador`/`TributacaoCurso` apontavam para um nome de tabela que diverge entre ambientes: uma migration de rename antiga ficou com os dois renames adicionados depois de já ter sido marcada como executada em ambientes mais antigos (então nunca surtiu efeito lá), enquanto um ambiente novo (testes, instalação do zero) já aplica o rename normalmente. Adicionada uma migration nova e idempotente que corrige só os ambientes que ainda estão com o nome antigo; precisa rodar `php artisan migrate` nesses ambientes para o efeito ser aplicado.

## Segunda rodada (2026-10-09): matrícula pública, uploads, credenciais e CSP

Corrige itens da revisão de segurança de 2026-10-08. Os testes ficam em `MatriculaOnlineSegurancaTest`, `UploadsSegurosTest`, `CredenciaisEmEmailSegurasTest`, `ContentSecurityPolicyTest` e `SaidaHtmlSeguraTest`.

### Matrícula online pública (`/matricular-online`)

O fluxo não exige login e cria pessoas, matrícula, contrato e conta de acesso, então o CPF/e-mail digitado **não prova identidade**. Regras em `MatriculaOnlineService`:

- **Dado digitado nunca altera cadastro existente com efeito de acesso.** O e-mail do formulário jamais é gravado numa pessoa que já existia (antes, um cadastro sem e-mail recebia o e-mail de quem só conhecia o CPF e, com ele, a conta do Portal e o acesso aos alunos vinculados). CPF e endereço só preenchem campos em branco.
- **A conta do Portal nasce do e-mail que já está no cadastro**, e a senha é definida pelo titular por link (ver "Credenciais" abaixo). Se já existe uma conta com aquele e-mail e a pessoa é nova, a conta não é amarrada a ela.
- **Aluno já conhecido** (tem matrícula, responsável vinculado ou conta de acesso) só é reaproveitado se o responsável informado já for o responsável dele (ou se for a mesma pessoa, aluno adulto). Caso contrário lança `DomainException` ("procure a secretaria") e a transação desfaz tudo. Cadastro que existe só como contato de CRM continua sendo reaproveitado, para não quebrar a conversão de leads.
- **Cadastro pré-existente é sinalizado:** o contrato registra "identidade não verificada" e a notificação da secretaria traz o aviso ⚠️; a matrícula nasce `PENDENTE`.
- **reCAPTCHA v3 + limite por IP** em `finalizarMatricula` (`seguranca.matricula_online.max_tentativas`, padrão 6 por 60 minutos, via `RateLimiter`). O token é obtido no navegador no clique (Alpine) e validado com `RecaptchaV3`; sem as chaves configuradas, a regra é ignorada como nos outros formulários públicos.
- **Erros inesperados não vazam:** o público vê uma mensagem genérica; a exceção vai para `report()`.
- **Página de confirmação** (`/matricular-online/sucesso/{matricula}`) exigia só o número da matrícula (sequencial, logo adivinhável) e mostrava aluno, responsável e documentos. O controller agora só libera na mesma sessão do navegador (`matricula_online_id`), com o link assinado ou para quem já tem acesso à matrícula; o wizard entrega um link assinado de validade configurável (`seguranca.matricula_online.link_sucesso_horas`, padrão 2 horas) e a rota tem `throttle`.

**Risco residual:** um aluno que existe só como contato de CRM, ou um responsável já cadastrado, ainda pode receber uma matrícula `PENDENTE` criada por terceiros. Ela não dá acesso a ninguém e fica marcada para a secretaria conferir antes de ativar.

### Uploads e entrega de arquivos

- `App\Support\TiposArquivo` é a lista branca (`imagens()`, `documentos()`, `materiaisDeAula()`). **Todo `FileUpload` deve declarar `acceptedFileTypes()`**; `image/*` é proibido por incluir SVG. O Filament valida o tipo no servidor (regra `mimetypes`). O teste `test_todo_file_upload_declara_tipos_e_nenhum_usa_image_curinga` falha se alguém criar um upload sem tipos (única isenção: importação de extrato bancário, que só é lida no servidor).
- `VisualizarDocumentoController` detecta o tipo pelo **conteúdo** (finfo), nunca pela extensão. PDF e imagens raster abrem na página (`inline`, só com `frame-ancestors 'self'`, porque `object-src 'none'` atrapalha o visualizador de PDF). Todo o resto baixa como `attachment`, com `Content-Type: application/octet-stream` e `Content-Security-Policy: default-src 'none'; sandbox`. A resposta passou a ser `private` (antes saía `public`).
- **Por quê:** o arquivo é servido na mesma origem do painel. Um HTML/SVG enviado por um professor (material de aula, sem restrição de tipo) rodava script com a sessão do administrador que o abrisse.

### Credenciais em e-mail, fila e log

- `WelcomeUserMail` e `UserUpdatedMail` **não enviam mais senha**: enviam um link de definição de senha (mesmo fluxo e mesmo token de "Esqueci minha senha", validade do broker `users`, 60 min). O token é gerado dentro do `toMail`, no momento do envio, então a fila (`jobs`/`failed_jobs`) não guarda segredo. Contas criadas por fluxo automático recebem uma senha aleatória de 64 caracteres descartada. `UserNewPasswordNotification` (sem uso) foi removida. Código em `App\Support\LinkDefinirSenha`.
- `LogSentMessage` grava o corpo dos e-mails por `App\Support\EmailLogSanitizer`: omite por inteiro os e-mails de credencial (boas-vindas, alteração de usuário, redefinição e verificação do Filament/Laravel) e mascara `token`, `signature` e afins em query string e os tokens de caminho de `/admissao/`, `/acordo/`, `/pesquisa-visita/` e `/convite/`.
- **O que já estava gravado continua no banco.** Rodar **uma vez por ambiente**, primeiro em simulação:
  ```
  php artisan seguranca:higienizar-credenciais --dry-run
  php artisan seguranca:higienizar-credenciais
  ```
  O comando limpa o corpo dos e-mails antigos em `email_logs` e apaga de `failed_jobs` os jobs de notificação que levavam senha. É idempotente e irreversível. Backups anteriores do banco continuam contendo os dados antigos.
- Usuários que receberam uma senha por e-mail no passado devem trocá-la; recomenda-se invalidar as senhas iniciais ainda não alteradas.

### Content-Security-Policy

`SecurityHeaders` aplica a CSP em duas camadas (`App\Support\ContentSecurityPolicy`, configuração em `config/seguranca.php`, variável `CSP_MODO`):

| Camada | Conteúdo | Quando vale |
|---|---|---|
| Base | `object-src 'none'; base-uri 'self'; frame-ancestors 'self'` | sempre (exceto `CSP_MODO=off`) |
| Estrita | `default-src 'self'` + origens usadas hoje (reCAPTCHA, Google Fonts/Bunny, Tailwind CDN, ViaCEP, ui-avatars, Google Analytics, vídeos embutidos) + `form-action 'self'` | `report-only` (padrão): só relata; `enforce`: bloqueia |

- A estrita ainda permite `'unsafe-inline'` e `'unsafe-eval'` em scripts, porque Filament/Livewire/Alpine e o Tailwind CDN dos portais públicos dependem disso. O ganho é impedir que script injetado carregue código de, ou envie dados para, domínio fora da lista. Uma política com nonce (sem `unsafe-inline`) exige o build CSP do Alpine e é a evolução natural.
- **Como ativar o bloqueio:** deixar `report-only` por cerca de uma semana de uso real (admin, portal da família, formulários públicos, editor de crachá, scanner da biblioteca), ler os avisos `CSP: violação relatada` no log (um por diretiva+origem+página por hora; o relato guarda só esquema+host e o primeiro trecho do caminho), acrescentar as origens legítimas em `CSP_EXTRA_*` e só então definir `CSP_MODO=enforce`. Se algo quebrar, `CSP_MODO=report-only` ou `off` desfaz sem deploy.
- Respostas que definem a própria CSP (download isolado de arquivos) não são sobrescritas.

### Saída de HTML, chat e PDF

- Descrição de evento escolar no Portal da Família (`{!! !!}`) passa por `Str::sanitizeHtml()`: remove `<script>`, `on*=` e `javascript:`.
- O chat do assistente só transforma em link caminhos do próprio sistema (`/...`, nunca `//host`) e URLs `http(s)`; `javascript:`, `data:` e afins ficam como texto. Links externos abrem com `rel="noopener noreferrer"`.
- `config/dompdf.php`: o `chroot` deixou de ser o projeto inteiro (que incluía `.env`) e passou a `storage/app/private`, `storage/app/public` e `public/`. Os PDFs do sistema embutem logos e fotos como data URI, então não dependem do resto.
- `ContractTemplateService::processHtmlImages` agora confere (com `realpath`) que a imagem está nas pastas de arquivos do sistema: `<img src="/visualizar-documento/../../.env">` num template de contrato embutia o `.env` em base64 no PDF.

### Novas variáveis de ambiente (todas opcionais)

`CSP_MODO` (`report-only` | `enforce` | `off`), `CSP_EXTRA_SCRIPT_SRC` / `_STYLE_SRC` / `_IMG_SRC` / `_FONT_SRC` / `_CONNECT_SRC` / `_FRAME_SRC` (origens separadas por vírgula), `MATRICULA_ONLINE_MAX_TENTATIVAS`, `MATRICULA_ONLINE_JANELA_MINUTOS`, `MATRICULA_ONLINE_LINK_SUCESSO_HORAS`.

## Pendências conhecidas

- **Autenticação de dois fatores** para contas administrativas: adiada por decisão do produto para agilizar o desenvolvimento. Plano pronto em [melhoria_mfa_painel.md](melhoria_mfa_painel.md).
- **Ativar o bloqueio da CSP** (`CSP_MODO=enforce`) depois do período em `report-only` (ver acima).
- **Rodar `php artisan seguranca:higienizar-credenciais`** em cada ambiente (ver "Credenciais em e-mail, fila e log").
- Deploy em ambiente sem Composer/shell: ver nota no `.gitignore` sobre comitar `vendor/` quando necessário.
- **Rodar `php artisan migrate` em produção** para aplicar o rename de `coordenadores`/`tributacao_cursos` (ver seção acima) — sem isso, `Coordenador`/`TributacaoCurso` não funcionam em produção.
- **Demais achados da revisão de 2026-10-08** (acesso de professores a todos os documentos, webhook de pagamento sem segredo, dados de saúde sem criptografia, ambiente/hospedagem, privacidade e retenção, sessão de 1 ano, tela de `git pull`, app mobile e outros): ver [seguranca_pendencias.md](seguranca_pendencias.md).
