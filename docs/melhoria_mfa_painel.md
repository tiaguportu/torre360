# Melhoria futura: autenticação em dois fatores (MFA) no painel

**Situação: NÃO implementada, por decisão do produto (2026-10-09), para agilizar o desenvolvimento.** Este documento registra o plano para quando a decisão mudar. A revisão de segurança de 2026-10-08 classificou a ausência de MFA como risco **alto** (item 3 da prioridade alta), e ela continua aberta.

## Por que vale a pena

O sistema guarda dados de crianças e adolescentes (documentos pessoais, dados de saúde, notas), dados financeiros e contratos. Hoje a única barreira de uma conta administrativa é e-mail + senha. Uma senha vazada ou reaproveitada de outro serviço dá acesso a tudo o que aquele papel enxerga, e um `super_admin` comprometido executa `git pull` e `migrate` em produção pela tela *Atualizar Sistema*.

- LGPD: art. 46 (medidas de segurança técnicas e administrativas aptas a proteger os dados) e art. 6º, VII (segurança). Um incidente com credencial sem MFA é difícil de defender perante a ANPD.
- Boas práticas: OWASP ASVS (V2.8) e NIST SP 800-63B recomendam segundo fator para contas com acesso privilegiado.

## O que já existe no Filament instalado (v5.9)

Nada precisa de pacote novo. O painel aceita provedores de MFA nativos:

- `Filament\Auth\MultiFactor\App\AppAuthentication` — aplicativo autenticador (TOTP: Google Authenticator, Authy, Microsoft Authenticator), com códigos de recuperação (`->recoverable()`, `->recoveryCodeCount(8)`).
- `Filament\Auth\MultiFactor\Email\EmailAuthentication` — código de uso único por e-mail.
- `Panel::multiFactorAuthentication([...], isRequired: true)` — liga os provedores e, com `isRequired: true`, obriga quem ainda não configurou a configurar no próximo acesso (página `SetUpRequiredMultiFactorAuthentication`).

## Plano de implementação

1. **Banco** — migration adicionando em `users`:
   - `app_authentication_secret` (`text`, nullable) — segredo TOTP;
   - `app_authentication_recovery_codes` (`text`, nullable) — códigos de recuperação;
   - `has_email_authentication` (`boolean`, default `false`), se o código por e-mail for usado.
2. **Model `User`** — implementar `HasAppAuthentication`, `HasAppAuthenticationRecovery` (e `HasEmailAuthentication`, se for o caso) e castear os dois primeiros campos com `encrypted` / `encrypted:array`. Os campos **não** podem entrar em `$fillable` nem sair em serialização (`$hidden`).
3. **`AdminPanelProvider`** (e `PortalPanelProvider`, se o portal entrar no escopo):
   ```php
   ->multiFactorAuthentication([
       AppAuthentication::make()->recoverable()->recoveryCodeCount(8),
       EmailAuthentication::make(),   // opcional: plano B sem aplicativo
   ], isRequired: fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'secretaria', 'coordenador']) ?? false)
   ```
   Os papéis do sistema são `super_admin`, `admin`, `secretaria`, `professor`, `coordenador`, `responsavel` e `aluno` (ver `RolesSeeder`). Começar obrigando só os que têm acesso a dados em massa e ao financeiro; professores e responsáveis podem ser opcionais numa primeira fase.
4. **Recuperação de conta** — definir quem redefine o MFA de um usuário que perdeu o celular (sugestão: só `super_admin`, com registro em log de atividade). Sem isso, o primeiro bloqueio vira chamado de suporte.
5. **Testes** — o login nos testes usa `actingAs`, que não passa pelo desafio de MFA, então a suíte atual não muda. Acrescentar: login com TOTP válido/inválido, uso de código de recuperação (uso único), bloqueio de acesso ao painel sem MFA configurado para os papéis obrigatórios.
6. **Documentação** — atualizar `docs/seguranca_hardening.md` (remover a pendência) e o `MANUAL_USUARIO.md` (como configurar o autenticador, o que fazer ao trocar de celular).

## Pontos de atenção

- **App mobile (Capacitor).** O app é um WebView do próprio site e identifica-se por `Torre360App` no User-Agent; `PersistentMobileSession` dá sessão de 1 ano a esse User-Agent. Com MFA, o desafio ocorre no login, então a sessão longa continua valendo. Revisar os dois juntos: sessão de 1 ano e MFA se compensam, mas um User-Agent forjado também ganha a sessão longa (ver item de sessão na revisão de segurança).
- **Fila e e-mail.** O código por e-mail depende do worker da fila e do SMTP; se o e-mail falhar, o usuário fica sem entrar. Por isso o aplicativo autenticador deve ser o provedor principal e o e-mail apenas um plano B.
- **Implantação gradual.** Ativar primeiro para `super_admin` e `admin`, observar uma semana, depois ampliar. Manter ao menos um `super_admin` com códigos de recuperação guardados fora do sistema.
- **Esforço estimado:** de 0,5 a 1 dia de desenvolvimento e testes, mais o tempo de comunicar e acompanhar a adoção.
