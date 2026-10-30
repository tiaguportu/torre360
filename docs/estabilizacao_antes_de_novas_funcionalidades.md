# Estabilização antes de novas funcionalidades (análise de 2026-10-30)

> **Status: recomendação, sem código próprio.** Registra a análise do sistema feita em 2026-10-30 (leitura do código, das rotas, do
> agendador e dos roteiros; nada foi executado na produção) e o que mudou naquele dia. Complementa as Ondas
> [89](paridade_esquema_testes_mariadb_roadmap.md) (paridade de esquema e testes em MariaDB) e
> [90](publicacao_segura_deploy_roadmap.md) (publicação segura), do [índice das ondas 80 a 90](ondas_80_a_88_indice.md).

> **Este repositório é público (conferido em 2026-10-30).** Documento de auditoria não deve listar versões com aviso de segurança,
> configuração de produção nem credenciais, e arquivo versionado não deve conter dado real de aluno, responsável ou funcionário. Esses
> assuntos ficam fora do Git.

## O tamanho do sistema (conferido no código)

| O quê | Quanto |
|---|---|
| Models / recursos do painel / páginas do painel | 218 / 136 / 36 |
| Entradas de menu (recursos e páginas com grupo) | cerca de 160, em 23 grupos |
| Policies / migrations / arquivos de teste | 151 / 400 / 341 (cerca de 3.400 métodos) |
| Documentos em `docs/` | cerca de 120; **cerca de 30 marcados "não implementado"**, 9 deles propostas do índice 80-90 |

## Por que o risco passou a ser acabamento e manutenção, não falta de função

**1. Funções entregues pela metade.** Cada uma promete algo que o sistema não cumpre:

| Função | O que falta | Roteiro |
|---|---|---|
| Central de Atendimento | `AtendimentoSetor.email_notificacao` é cadastrado e **nunca lido**: a tela promete um e-mail que não sai; o chamado também não grava notificação. | [83](atendimento_unificado_roadmap.md) |
| Proposta comercial | `AceitaPelaFamilia`, `Convertida` e `Expirada` estão no enum e são lidos em regras, mas **nenhum código atribui** esses estados; o desconto negociado não chega ao contrato. | [87](proposta_comercial_aceite_roadmap.md) |
| Permissões | `Aprovar:PropostaComercial`, `Homologar:Avaliacao`, `Homologar:PlanilhaLeiMensalidade` e `ReenviarNotificacao:OcorrenciaEscolar` são usadas no código e **não são criadas por nenhuma migration ou seeder**. | — |
| Canais de mensagem | O conceito de "canal" existe três vezes (`CanalMensagemManager`, `CanalReguaFollowUp` e o `canal` da régua de cobrança), sem que um use o outro. | [88](whatsapp_oficial_roadmap.md) |

**2. Menu difícil de percorrer.** Os maiores grupos têm 23 (Financeiro), 22 (Secretaria), 22 (Acadêmico) e 21 (Configurações) entradas.
Há grupos quase iguais (**RH** com 15 e **Gestão de RH** com 1; **CRM / Comercial** com 12 e **CRM** com 1) e grupos de um item só
(Patrimônio, Currículo (BNCC), Convivência e Disciplina, Ajuda). A *Minha Mesa* e a busca global ajudam, mas o sistema não registra quais
telas a escola de fato abre; sem esse número, decide-se o que construir por palpite.

**3. Custo fixo de cada função nova.** Policy, permissões, migration, testes, manual (`MANUAL_USUARIO.md`), dicionário
(`GEMINI_DB.md`) e roteiro. Os testes rodam só em SQLite enquanto a produção é MySQL, a suíte completa leva de 12 a 25 minutos, e a
cadeia de migrations não roda do zero em MariaDB (Onda 89). A publicação não tem etapa de proteção (Onda 90). Cada onda nova aumenta o
que uma publicação ruim pode quebrar.

**4. Coordenação.** Várias sessões trabalham no mesmo checkout e já implementaram a mesma onda em duplicidade (Ondas 49 e 57, em
2026-10-23) e commitaram arquivos umas das outras. Quanto mais ondas em paralelo, mais retrabalho.

## Recomendação

Pausar as funções **grandes** até fechar as Ondas 89 e 90. Seguem liberadas as pequenas e independentes (Ondas 81 e 82 e o aviso do
chamado). Uma rodada de estabilização, em quatro frentes:

1. **Entrega:** Ondas 89 e 90 (e, nelas, integração contínua com os testes de guarda e o subconjunto em MariaDB).
2. **Acabamento:** zerar a tabela de funções pela metade acima, uma por vez, cada uma com teste.
3. **Menu:** unificar os grupos duplicados e os de item único antes de criar novos.
4. **Medir o uso:** registrar quais telas são abertas, por papel, antes de decidir o que construir ou retirar.

É um julgamento, não uma regra: se a escola depende de uma função específica, a ordem muda.

## O que foi feito em 2026-10-30

- Removido o `migrate --force` automático (`Schema::hasTable` no `boot()`) das seis páginas da Régua de Cobrança e da Régua de Follow-up.
- README: o frontend usa Livewire 4 (dizia "3+").
- Git: removidos 13 worktrees limpos e 27 branches locais totalmente integradas na `main`. Ficaram `publish/lote-d` (o worktree tem alteração
  sem commit), `feat/resumo-diario-faltas` (sessão ativa) e `feat/ondas-72-74` e `snapshot/ondas-49-57-72-74-completo` (um commit
  exclusivo cada).
- [Onda 81](qualidade_cadastros_roadmap.md), parcial: detecção de pessoas possivelmente duplicadas (tela *Secretaria > Cadastros duplicados*).
- Correção de afirmações dos roteiros: a página *Atualizar Sistema* tem `canAccess` restrito a `super_admin` desde 2026-04-06 (o roteiro da
  Onda 90 dizia que não); o painel do Portal ganhou o sino de notificações em 2026-10-28.
