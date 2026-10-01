# Hardening de segurança (Onda 1-3)

Resumo das correções de segurança aplicadas a partir de uma revisão geral do sistema. Cada item lista o risco original e a correção, para servir de referência rápida — não repete o diff, só o "porquê".

## Onda 1 — Crítico

| Risco | Correção |
|---|---|
| Upload de anexo no portal (`CentralAtendimento`) sem validação de tipo/tamanho, salvo em disco público → possível upload de arquivo executável. | `acceptedFileTypes`/`mimes`, `maxSize` e disco `local` (privado) em todos os pontos de upload de anexo (portal e admin). |
| Auto-registro aberto em `/admin/register` — qualquer pessoa virava conta no painel admin. | Removido `->registration()` e o link de cadastro do `AdminPanelProvider`. |
| Documentos oficiais (PDF com CPF/RG/endereço) gravados no disco `public`, acessíveis sem autenticação via `protocolo` sequencial. | `DocumentoService` passou a gravar em disco `local`; `VisualizarDocumentoController` ganhou checagem de posse (`isAccessibleBy()`) e sanitização contra path traversal; `ValidarDocumentoController` não aceita mais `protocolo` como chave de busca pública (só `codigo_verificacao` aleatório) e o nome do aluno exibido é mascarado (LGPD). |
| XSS armazenado: nome/CPF/endereço de `Pessoa` (podem vir de formulário público de captação) eram concatenados sem escape no HTML do contrato, renderizado com `{!! !!}` numa página autenticada. | `ContractTemplateService` escapa (`e()`) todo dado dinâmico de pessoa antes de montar o HTML (ponto único: `generateAssinaturaBlock`, mais `generateAlunoTableFallback`/`generateResponsaveisInfo`). |
| Reset de senha sem rate limit nativo (override customizado descartava o throttling do Filament). | `CustomRequestPasswordReset` voltou a usar o `request()` original do Filament. |

## Onda 2 — Alto

| Risco | Correção |
|---|---|
| Upload sem validação também em `AtendimentoChamados` (admin). | Mesmo tratamento do item de upload da Onda 1. |
| `TemplateCrachaV3Controller::save()`/`editor()` exigiam só `auth` — qualquer conta autenticada (ex.: aluno do portal) alterava o layout do crachá. | `abort_unless($request->user()->isStaff(), 403)` nas duas actions. |
| `json_encode()` cru dentro de `<script>` no editor de crachá (quebra de contexto se o JSON contivesse `</script>`). | Trocado por `Illuminate\Support\Js::from()`. |
| 63 advisories (`composer audit`) em 20 pacotes, 17 de severidade alta (Filament, Laravel, Symfony, dompdf, TinyMCE, commonmark). | `composer update` dentro das faixas já permitidas pelo `composer.json` (sem bump de versão major). `composer audit` zerou (0 advisories) depois. `pragmarx/google2fa-qrcode` não é dependência direta — é exigido pelo próprio `filament/filament` (suporte a MFA nativo), não há o que remover. |

## Onda 3 — Médio

| Risco | Correção |
|---|---|
| Nenhum header de segurança (CSP, X-Frame-Options, etc.). | Middleware `App\Http\Middleware\SecurityHeaders` (global, via `bootstrap/app.php`): `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, e `Strict-Transport-Security` quando a requisição é HTTPS. **Sem CSP** por enquanto — o painel (Filament/Livewire/Alpine/TinyMCE) usa script/style inline em vários pontos; uma CSP precisa ser desenhada e testada à parte para não quebrar o admin. |
| Política de senha era só `min:8` (sem `Password::defaults()`). | `AppServiceProvider::boot()` registra `Password::min(10)->mixedCase()->numbers()->symbols()`, com `->uncompromised()` (checagem de vazamento via HaveIBeenPwned) só em produção, pra não depender de rede em dev/testes. |
| Logs com dado sensível: `MobileTokenController` logava e ecoava o token FCM completo; webhook/serviço da Assinafy logavam o payload inteiro. | Removida a captura de `$request->all()`/payload bruto nos logs; mantidos só os campos de identificação (evento, id do documento) necessários pra depuração. |
| `routes/api.php` sem rate limiting. | `throttle:60,1` na rota `/user`, `throttle:30,1` no webhook da Assinafy. **Não há verificação de assinatura/HMAC no webhook** — não foi implementada por falta de documentação de um mecanismo de assinatura da Assinafy neste código; se a Assinafy expuser um header de assinatura, vale adicionar a verificação depois. |
| `npm audit`: `tar` (crítico), `brace-expansion` e `@xmldom/xmldom` (altos). | Resolvidos via `npm audit fix` (sem `--force`). Restam `tar`/`uuid`/`sharp` vulneráveis, mas só como dependências transitivas de `@capacitor/assets`/`@capacitor/cli` (ferramenta de build dos ícones do app mobile, não roda em produção/no servidor web) — sem fix disponível sem quebrar essas libs; risco baixo, não endereçado. |

## Pendente (não coberto nesta rodada)

- **Onda 4 (estrutural)**: migrar models de `protected $guarded = [];` para `$fillable` explícito (~60 models). Não há exploração conhecida hoje (nenhum `Model::create($request->all())` encontrado), mas é um risco estrutural para código futuro.
- **2FA real**: hoje não há MFA para contas admin. Decisão tomada: não implementar agora (fora do escopo desta rodada).
- **IDOR em `VisualizarDocumentoController`**: já havia sido corrigido antes desta rodada (ver histórico de commits), junto com proteção contra path traversal.
- **Deploy sem Composer no servidor**: ver `.gitignore` — se o servidor remoto não tem Composer/shell, a vendor precisa ser commitada manualmente a cada atualização de dependência (`composer install --no-dev --optimize-autoloader` local + `git add -f vendor`).
