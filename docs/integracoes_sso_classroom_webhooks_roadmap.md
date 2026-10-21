# Integrações: SSO, Google Classroom e Webhooks (Onda 48) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Escolas que usam Google Workspace ou Microsoft 365 querem que professores entrem no Torre360 com a
conta institucional, que as turmas apareçam no Google Classroom sem digitação dupla e que sistemas
de terceiros (catraca, BI, ERP) sejam avisados dos eventos importantes.

## O que já existe (ponto de partida)

- Autenticação por e-mail e senha do Filament, com vínculo `User` ↔ `Pessoa`.
- `AssinafyService`, `GatewayPagamentoManager` e `FcmService`: integrações de saída já isoladas por
  serviços e configuradas por `.env`.
- `WebhookSignatureValidator`: validação de assinatura de webhooks **recebidos**.
- API móvel mínima (`MobileTokenController`).
- Não há login social (o pacote Socialite não está instalado), sincronização com plataformas de
  aprendizagem nem webhooks de saída.

## Escopo

### 1. SSO (Google e Microsoft)
- Login com a conta institucional, restrito a domínios autorizados; o e-mail precisa já existir em
  `users` (o SSO **não cria conta**; a ativação continua sendo da secretaria).
- Convivência com a senha local para contas de emergência.
- Em conjunto com a Onda 46: o segundo fator pode ser exigido pelo provedor.

### 2. Google Classroom
- Cadastro de uma conexão por escola (OAuth de administrador ou conta de serviço com delegação de
  domínio, conforme a política do Workspace).
- Sincronização **unidirecional** Torre360 → Classroom: turma/disciplina → curso, professores e
  alunos matriculados → participantes.
- Relatório de divergências (aluno sem e-mail institucional, professor sem conta).

### 3. Webhooks de saída
- `WebhookEndpoint`: URL, segredo, eventos assinados (matrícula criada, fatura paga, falta lançada...).
- Entrega assinada com HMAC, com repetição e backoff, e registro de cada tentativa.
- Fila própria e painel de falhas.

### Regras
- Mínimo de dados no payload (identificadores, não dados pessoais completos), com a possibilidade de
  o receptor consultar a API para detalhes autenticado.
- Credenciais e segredos só em `.env`/cofre; nunca no banco em texto puro.
- Sincronização com Classroom envolve dados de menores: base legal e contrato com o fornecedor
  precisam estar fechados antes.

## Dependências já satisfeitas

- Padrão de serviços de integração e filas.

## Risco e observação

- Cada integração tem aprovação e custo próprios (verificação de app no Google, limites de API):
  priorizar a que a escola realmente usa.
- O Classroom muda de política com frequência: manter a integração atrás de uma interface.
