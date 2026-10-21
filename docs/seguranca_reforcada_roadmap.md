# Segurança Reforçada: MFA, Retenção de Logs e Criptografia de Saúde (Onda 46) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda. Reúne três itens já levantados em `docs/seguranca_pendencias.md` e
> `docs/melhoria_mfa_painel.md`.

## Contexto

O sistema guarda dados de crianças e adolescentes (documentos, saúde, notas), dados financeiros e
contratos. A revisão de segurança de 2026-10-08 deixou três reforços abertos que dependem de decisão
do produto e de impacto na operação: segundo fator de autenticação, política de retenção dos logs e
proteção em repouso dos campos de saúde.

## O que já existe (ponto de partida)

- `docs/melhoria_mfa_painel.md`: plano do MFA e o motivo de estar fora até aqui (decisão do produto
  de 2026-10-09). O plano prevê reaproveitar o suporte nativo do Filament instalado, sem biblioteca nova.
- `docs/seguranca_hardening.md`: correções já aplicadas (CSP em `report-only`, limites de requisição,
  sessões e outras).
- `activitylog` (log de atividades) e `AuditLog`; `HigienizarCredenciaisLogadasCommand`.
- Campos com cast `encrypted` em módulos recentes: parecer e observação da avaliação docente
  (Onda 19) e motivo dos ajustes de ponto (Onda 41). Os dados de saúde mais antigos ainda não.
- `docs/lgpd_saude_policies.md`: acesso a `FichaMedica`, `AtendimentoEnfermagem` e `OcorrenciaEscolar`.

## Escopo

### 1. MFA
- Segundo fator por aplicativo autenticador (TOTP) com códigos de recuperação.
- Obrigatório para `super_admin`, `admin` e papéis com acesso financeiro ou a dados de saúde;
  opcional para os demais; prazo de carência para ativação.
- Fluxo de redefinição assistida (perda do aparelho) com trilha no log.
- Portal da Família: opcional.

### 2. Retenção de logs
- Política por tipo de log (autenticação, atividade, auditoria, envio de e-mail, webhooks): prazo e
  finalidade documentados (necessidade e minimização, LGPD).
- Comando agendado de expurgo, com contagem do que foi removido e exceção para logs sob investigação.
- Relatório mensal de volume por log para dimensionar o banco.

### 3. Criptografia de campos de saúde
- Cast `encrypted` em textos livres de `FichaMedica`, `AtendimentoEnfermagem` e
  `MedicamentoAluno`; migração dos dados existentes em lote, com backup e verificação.
- Revisar consultas que filtram por esses textos (SQL `like` deixa de funcionar): a leitura passa a
  ser feita em PHP, como já faz o cruzamento de alergias do cardápio (Onda 40), ou usa colunas
  derivadas não sensíveis (flags estruturadas).

## Dependências já satisfeitas

- Planos documentados e política de acesso a dados de saúde.

## Risco e observação

- **MFA:** o risco operacional é bloquear a única conta administrativa. Exigir códigos de recuperação
  e ter um procedimento de emergência fora do sistema.
- **Criptografia:** perder a `APP_KEY` perde os dados. Backup da chave em cofre separado, antes de
  migrar qualquer dado.
- **Retenção:** apagar log vencido pode conflitar com a necessidade de prova; alinhar com o jurídico.
