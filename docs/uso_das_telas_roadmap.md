# Uso das Telas e Adoção do Sistema (Onda 106) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste do relatório. **A coleta já existe** (ver abaixo). Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).
> Responde à quarta frente da [análise de estabilização](estabilizacao_antes_de_novas_funcionalidades.md): "medir o uso" antes de decidir o que construir ou retirar.

## Contexto

O sistema tem mais de 130 recursos e cerca de 160 entradas de menu. Decide-se o que construir, manter ou tirar por palpite, porque ninguém sabe quais telas cada papel abre, que módulo novo ninguém
usa e **quantas famílias entraram no Portal no último mês**. A análise de 2026-10-30 dizia que o sistema "não registra quais telas a escola de fato abre". **Registra**, e esta onda é o relatório que falta.

## O que já existe (conferido no código)

- `AuditMiddleware` está nos **dois painéis** (admin e Portal). A cada requisição `GET` de usuário autenticado que não seja AJAX ele grava uma linha em `audit_logs`
  (`event = view`, `user_id`, `url`, `ip_address`, `user_agent`, data) e uma entrada do `activitylog` com o texto "**{papel} visualizou recurso: {recurso} | URL: ...**".
  Ativo por `app.audit_enabled`. (Leitura do código, não executado.)
- **Retenção:** `audit:limpar-logs --dias=180` e `activitylog:clean --days=180`, diários. Portanto o histórico de uso é de seis meses.
- **Nenhuma tela ou relatório lê esses dados:** `AuditLog` só aparece no middleware, no model e no comando de limpeza.
- Limites do dado como está: a URL vem crua, com identificadores (`/admin/matriculas/123/edit`); `audit_logs` **não guarda o papel ativo** (só o texto do `activitylog` guarda); o nome do
  "recurso" no texto sai do trecho depois de `admin/`, então no **Portal** ele tende a ser só "Portal" e a tela só se descobre pela URL; há uma linha por
  requisição, o que cresce depressa; e o IP e o agente do navegador são gravados em **todo** acesso.
- `UserSession` e a página *Controle de Sessões* mostram quem está logado agora, não o uso ao longo do tempo.
- Para o relatório: `RelatorioSalvo` e `config/relatorios.php` (Onda 72), `PainelDirecao` (Onda 45) e o catálogo de ondas e módulos nos índices.

## Escopo proposto

### Agregar antes de expurgar
- Tabela `uso_tela_diario`: data, painel, **recurso ou página** (nome da rota normalizado, sem identificador), papel, usuários distintos e acessos. Um comando diário lê o dia anterior de `audit_logs` e do
  `activitylog`, normaliza e grava o agregado, **antes** do expurgo de 180 dias. O agregado não tem IP nem agente de navegador e pode ser guardado por anos.
- Catálogo `config/uso_telas.php`: recurso → módulo → onda. Permite ver "o que a Onda 78 entregou está sendo usado?".

### Relatório *Uso do sistema*
- **Telas mais e menos usadas por papel**, com a lista de entradas de menu **sem nenhum acesso em 90 dias** (candidatas a sair do menu ou a ganhar treinamento).
- **Adoção do Portal:** famílias com ao menos um acesso nos últimos 30 dias, sobre o total de famílias com usuário, por série e por turma. É o número que mostra se vale investir em aviso, calendário
  e pesquisa, e quem precisa de convite de novo.
- **Adoção de módulo novo** depois do lançamento (acessos por semana nas 8 semanas seguintes) e tempo desde o último acesso de cada papel.
- Fonte de relatório agendado (Onda 72) e, opcionalmente, indicador no Painel da Direção.

### Regras
- **Sem nome por padrão:** o relatório é por papel e por tela. Ver o uso de uma pessoa é outra permissão, com motivo registrado.
- **Não é avaliação de desempenho de funcionário.** O texto da tela diz isso; o objetivo é decidir produto e treinamento.
- Ajustar a coleta: reduzir o que se grava (por exemplo, parar de gravar `user_agent` completo e IP em todo acesso de leitura, se a segurança não precisar) e **sampling** se o volume incomodar.

## Base legal

LGPD, art. 6º (finalidade, necessidade e adequação): o agregado sem identificação serve ao objetivo com menos dado. O registro bruto por IP e agente de navegador tem finalidade de segurança e
auditoria; mantê-lo por seis meses é decisão a registrar na política de privacidade e na [Onda 79](guarda_descarte_documentos_roadmap.md).

## Dependências e riscos

- A normalização de URL em recurso é o trabalho real: telas Filament, páginas Livewire, rotas de PDF. Começar pelas 30 mais comuns e deixar o resto em "outras".
- Os dados anteriores ao agregado têm no máximo 180 dias: **começar a agregar já** vale mais do que esperar o relatório pronto.
- Contagem de acesso a tela não mede valor: uma tela usada uma vez por ano (o informe anual) pode ser essencial.

## Esforço

Pequeno.

## Pacote

**P11 (Plataforma e conformidade)**, como a primeira fatia, ao lado de testes em MariaDB e publicação segura. Ver a [proposta de unificação](unificacao_de_ondas_proposta.md).
