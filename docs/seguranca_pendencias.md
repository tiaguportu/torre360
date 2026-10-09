# Segurança e LGPD: pendências da revisão de 2026-10-08

Backlog dos achados da revisão de segurança que **ainda estão abertos**. O que já foi corrigido está em [seguranca_hardening.md](seguranca_hardening.md); o plano do MFA está em [melhoria_mfa_painel.md](melhoria_mfa_painel.md). Cada item foi reconferido na `main` em 2026-10-09 (commit `443003e6`).

Já corrigidos (não repetir): enumeração de contas no login, IDOR da confirmação de matrícula online, SVG/HTML servidos inline, injeção de fórmulas em exportações CSV, sessões concorrentes após troca de senha, `Permissions-Policy` bloqueando a câmera, matrícula online pública (vínculo indevido, captcha, limite), tipos de upload, senhas em e-mail/log, CSP (em `report-only`), XSS da descrição de evento, links do chat, `chroot` do dompdf.

Gravidade: **Alta** = corrigir em seguida; **Média** = planejar; **Baixa** = higiene.

## Alta

| # | Achado | Onde | Correção sugerida |
|---|---|---|---|
| A1 | **Professor lê os documentos pessoais de qualquer aluno ou candidato** (RG, CPF, certidão, anexos de atendimento). `isStaff()` conta `professor` como equipe e ignora as permissões do Shield. Viola o princípio da necessidade (LGPD art. 6º, III). | `User::isStaff()` ([User.php:118](../app/Models/User.php)); `DocumentoInserido::isAccessibleBy`, `SolicitacaoDocumento::isAccessibleBy`, `Matricula::isAccessibleBy`; contextos A–D de `VisualizarDocumentoController` | Autorizar por permissão (`View:DocumentoInserido`) ou por vínculo do professor com a turma do aluno; manter `isStaff()` só para o que for mesmo de toda a equipe. Teste: professor sem vínculo recebe 403. |
| A2 | **Webhook de pagamento aceita requisição sem assinatura quando o segredo está vazio.** `PAGAMENTOS_WEBHOOK_SECRET` não existe no `.env`. Quem souber um `gateway_id` (8 caracteres aleatórios; pode aparecer para a família em link de pagamento, a confirmar) dá baixa em fatura com valor e data à escolha. Hoje o driver é o `fake`, então o risco é latente. | `WebhookSignatureValidator::valida` (retorna `true` sem segredo); `PagamentoWebhookController` | Falhar fechado: sem segredo, responder 503/401. Definir o segredo antes de ligar um gateway real. O webhook do Assinafy se apoia na confirmação pela API e é aceitável. |
| A3 | **Dados de saúde de crianças sem criptografia em repouso** (tipo sanguíneo, alergias, plano de saúde, SUS, sintomas, medicamentos). LGPD arts. 5º II, 11 e 46. | `FichaMedica`, `AtendimentoEnfermagem` | Cast `encrypted` nas colunas sensíveis (verificar buscas/ordenações que dependam delas) e migração que recifra os dados existentes. |
| A4 | **Ambiente e hospedagem.** Este checkout usa `APP_ENV=local`, `APP_DEBUG=true`, `DEBUGBAR_OPEN_STORAGE=true`, `LOG_LEVEL=debug` e aponta para o banco de produção; a pasta guarda 337 documentos reais, dumps (`backup_banco_*`) e `.env.backup_realbkp`. O `.htaccess` da raiz só bloqueia `.env` exato e depende do `mod_rewrite` para esconder o resto. | `.env`, `.htaccess`, `.env.example` (traz `APP_DEBUG=true`) | **Confirmar o `.env` do servidor** (`APP_ENV=production`, `APP_DEBUG=false`, sem Debugbar). DocumentRoot em `public/`. Disco da estação criptografado e credencial de banco separada para desenvolvimento. Apagar dumps e `.env.*` antigos. |
| A5 | **LGPD: transparência e governança ausentes.** Não há Política de Privacidade, canal do titular nem Encarregado (DPO) no sistema; o formulário público `/quero-matricular` coleta CPF, telefone e dados de menores só com o aviso do reCAPTCHA (links do Google); o aceite da matrícula fica embutido no contrato, sem versão do texto; não há política de retenção/descarte nem plano de resposta a incidente. | formulários públicos, `MatriculaOnlineService`, `ConviteMatriculaService` | Página de Política de Privacidade e aviso nos formulários (art. 9º e 14); registrar versão do texto + data + IP do aceite; indicar Encarregado (Res. CD/ANPD 18/2024); rotina de retenção; procedimento de incidente (comunicar à ANPD em 3 dias úteis, Res. CD/ANPD 15/2024); RIPD (arts. 37–38). |

## Média

| # | Achado | Onde | Correção sugerida |
|---|---|---|---|
| M1 | **Sessão de 1 ano para qualquer User-Agent "Torre360App"** (cabeçalho forjável; celular perdido fica logado). | `PersistentMobileSession` | Prazo menor com renovação, reautenticação para papéis administrativos, vínculo ao dispositivo. Rever junto com o MFA. |
| M2 | **Tela "Atualizar Sistema" executa `git pull` e `migrate --force` em produção pela web.** Super admin comprometido fica perto de executar código no servidor. | `GitPull` | Deploy por CI/CD ou SSH; no mínimo, MFA e reautenticação nessa tela. |
| M3 | **Acordo público de inadimplência:** `token_publico` não expira, o aceite da confissão de dívida não verifica identidade nem tem trava contra clique duplo. Lei 14.063/2020 pede ao menos assinatura eletrônica simples autenticada. | `AcordoPublicoController`, `AcordoInadimplencia` | Validade do token, verificação (CPF parcial + código por e-mail/SMS) e lock na transação de aceite. |
| M4 | **XSS armazenado provável nas telas e PDF de comparação de questionários:** `valor_exibicao` (texto livre da resposta) e o enunciado saem com `{!! !!}` sem sanitização. Confirmar quem pode responder questionários. | `filament/resources/questionario-respostas/comparacao.blade.php`, `pdfs/comparacao-questionarios.blade.php`, `QuestionarioPerguntaResposta::valor_exibicao` | `e()` nas respostas de texto livre; `Str::sanitizeHtml()` só no enunciado, que é HTML do editor. |
| M5 | **Logs com dado pessoal.** O hook do assistente grava e-mail e papéis em `Log::info` a cada página e deixa comentário de debug no HTML (id, papel, permissões); `AuditMiddleware` grava a URL completa de cada GET e `audit_logs` não tem purga; o webhook de pagamento loga o payload inteiro; `activitylog:clean` não está agendado. | `resources/views/filament/hooks/assistant-chat.blade.php`, `AuditMiddleware`, `PagamentoWebhookController`, `routes/console.php` | Remover o log e o comentário; truncar URL sem query; agendar purga conforme a retenção definida; logar só campos necessários. |
| M6 | **App mobile.** `npm audit` apontava 2 vulnerabilidades críticas no Capacitor (GHSA-rvm3-566m-v7fv) em 2026-10-08 (rodar de novo; há `npm audit fix`). Configuração: `allowNavigation: ["*"]`, `allowMixedContent: true`, `webContentsDebuggingEnabled: true`, `allowBackup="true"` no Android. | `capacitor.config.json`, `android/` | Atualizar o Capacitor; restringir a navegação ao domínio; desligar debug e mixed content nas builds de produção; `allowBackup="false"`. |
| M7 | **Cookie e sessão:** `SESSION_SECURE_COOKIE` não definido e `SESSION_ENCRYPT=false`. | `config/session.php`, `.env` | `SESSION_SECURE_COOKIE=true`; avaliar `SESSION_ENCRYPT=true`. |
| M8 | **Tokens do portal de admissão guardados em texto claro** (a geração, a validade e a revogação estão corretas). Quem lê o banco ou um backup usa links ainda válidos. | `Interessado::token_documentos` | Guardar o hash (SHA-256) e comparar pelo hash. |
| M9 | **IA (Gemini): dados de menores saem para o Google** (documentos, áudios, prints, e o esquema do banco a cada pergunta do assistente). LGPD arts. 33–36 e Res. CD/ANPD 19/2024. Não é correção de código. | `GeminiAgentService`, `DocumentoIaService`, `CrmIaVendasService` | Confirmar plano contratado e uso para treinamento, contrato de operador, informar os titulares; reduzir o que é enviado (não enviar `GEMINI_DB.md` inteiro). |

## Baixa

- `MobileTokenController` está fora do CSRF e aceita `token` sem tamanho máximo ([MobileTokenController.php](../app/Http/Controllers/Api/MobileTokenController.php)). O `SameSite=lax` mitiga.
- `/api/user` devolve o modelo inteiro, inclusive `fcm_token` ([routes/api.php](../routes/api.php)).
- `RecaptchaV3` falha aberto quando o Google está fora do ar (decisão de disponibilidade a registrar).
- `dompdf`: `enable_javascript` está `true` ([config/dompdf.php](../config/dompdf.php)); os PDFs do sistema não precisam.
- `DemoVideoSeeder` cria super admin com senha `demo12345`: fazer o seeder abortar em produção.
- `.env.example` vem com `APP_DEBUG=true` e `APP_ENV=local`.
- CSP: depois do período em `report-only`, mover para nonce (Alpine CSP build) e remover `unsafe-inline`/`unsafe-eval`.

## Ordem sugerida

1. A1, A2 e a confirmação do `.env` do servidor (A4): são pequenos e fecham os maiores riscos de acesso.
2. M4 (uma linha por ponto), M5, M7 e `CSP_MODO=enforce`.
3. A3 e A5, que exigem decisão de negócio e migração de dados.
4. MFA, M1 e M2 juntos, porque se reforçam.
