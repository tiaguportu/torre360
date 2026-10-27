# Conjuntos "Suprimentos" e "Resultado da escola" — Índice

> **Status: implementados** em 2026-10-27, junto com a Onda 84. Continua no [índice das ondas 72 a 79](ondas_72_a_79_indice.md).

Dois conjuntos que a análise de fusões do [índice das ondas 72 a 79](ondas_72_a_79_indice.md) sugeriu, mais a Onda 84 (Mesa do
consultor). Cada onda tem o seu documento, com o que foi entregue, as decisões, os parâmetros, as permissões, os testes e o que ficou
de fora; este índice mostra como as peças se ligam e o que mudou para quem já usava o sistema.

## O que foi implementado

| Onda | Tema | Onde fica | Documento |
|---|---|---|---|
| 84 | Mesa do consultor e próxima ação obrigatória | CRM / Comercial, Minha Mesa | [mesa_consultor_proxima_acao_roadmap.md](mesa_consultor_proxima_acao_roadmap.md) |
| 38 | Compras e cotações | Suprimentos → Compras | [compras_cotacoes_roadmap.md](compras_cotacoes_roadmap.md) |
| 16 (fase 2) | Estoque de consumíveis | Suprimentos → Estoque | [patrimonio_estoque_roadmap.md](patrimonio_estoque_roadmap.md) |
| 43 | Orçamento anual × realizado | Financeiro → Orçamento Anual e Orçado × Realizado | [orcamento_anual_realizado_roadmap.md](orcamento_anual_realizado_roadmap.md) |
| 78 | Contas recorrentes e fluxo de caixa projetado | Financeiro → Contas Recorrentes e Fluxo Projetado | [contas_recorrentes_fluxo_projetado_roadmap.md](contas_recorrentes_fluxo_projetado_roadmap.md) |
| 77 | Resultado realizado por turma | Financeiro → Resultado Realizado | [rentabilidade_por_turma_roadmap.md](rentabilidade_por_turma_roadmap.md) |
| 45 | Painel da Direção | Relatórios → Painel da Direção e Metas dos Indicadores | [painel_direcao_roadmap.md](painel_direcao_roadmap.md) |
| 76 | Simulador do próximo ano letivo | Relatórios → Simulador do Próximo Ano | [simulador_proximo_ano_letivo_roadmap.md](simulador_proximo_ano_letivo_roadmap.md) |

## Como as peças se ligam

**Suprimentos (38 + estoque + 43).** A requisição de compra escolhe o destino de cada item. No **recebimento** o item de estoque entra
no saldo (com o custo da compra), o bem durável vira cadastro no patrimônio e nasce a **conta a pagar** (com o centro de custo e a
conta do plano da requisição). Na **aprovação**, o saldo do orçamento da conta (Onda 43), já descontado o que pedidos aprovados
ainda vão gastar, aparece para quem decide. O estoque abaixo do mínimo e o orçamento estourado chegam à Minha Mesa; a reposição é
uma nova requisição.

**Resultado da escola (43 + 78 + 77 + 45 + 76).** Quatro telas leem as mesmas faturas, contas a pagar, transações bancárias, centros de
custo e hora-aula, e cada uma responde uma pergunta diferente:

| Pergunta | Tela | Ligação |
|---|---|---|
| O que prevíamos? | Orçado × Realizado (43) | O realizado é o do DRE |
| O que vai acontecer? | Fluxo Projetado (78) | Usa as contas recorrentes que ainda não viraram conta |
| Quanto cada turma rendeu e custou? | Resultado Realizado (77) | Mesma base de contas a pagar e hora-aula; compara com o planejado da Controladoria de Turmas |
| Como a escola está? | Painel da Direção (45) | Chama os serviços acima; uma definição só por número |
| Quantas turmas abrir? | Simulador (76) | Usa o custo planejado da turma e a tabela de preços da rematrícula |

As três primeiras são telas separadas, ligadas por links e pelas mesmas fontes. A **visão única** (orçado, projetado e realizado lado a
lado) continua sendo uma melhoria possível.

## O que mudou no que já existia

- **Menu:** novo grupo **Suprimentos** (Compras, Estoque); em **Financeiro**, *Orçamento Anual*, *Orçado × Realizado*, *Contas
  Recorrentes*, *Fluxo Projetado* e *Resultado Realizado*; em **Relatórios**, *Painel da Direção*, *Metas dos Indicadores* e *Simulador do
  Próximo Ano*.
- **CRM:** atendimento sem próxima ação é **recusado** (exceto *Efetuou matrícula* e *Sem interesse*). Lead antigo sem próxima ação
  aparece na fila *Leads sem próxima ação*. Ver o [documento da Onda 84](mesa_consultor_proxima_acao_roadmap.md).
- **Contas a Pagar:** ação **Revisar valor**, selo "valor estimado: a revisar" e filtro **Valor a revisar**. A baixa de uma conta gerada de
  despesa de valor estimado fica **travada até a revisão**. A conta que vem de um recebimento de compra traz o vínculo com o pedido.
- **Centros de Custo:** o formulário ganhou *Resultado por turma* (custo direto de uma turma ou de um curso, ou **fora do resultado das
  turmas**) e a lista mostra como cada centro entra na conta. Sem vínculo, a despesa é custo indireto e é rateada.
- **Controladoria de Turmas (Rentabilidade de Turmas):** não mudou; o *Resultado Realizado* é a tela irmã, com o realizado.
- **Minha Mesa:** 16 filas novas (lista completa em [mesa_pendencias_roadmap.md](mesa_pendencias_roadmap.md)).
- **Relatórios configuráveis:** três fontes novas (fluxo projetado, resultado por turma e projeção do próximo ano).
- **Papel `professor`:** ganhou `ViewAny`, `View`, `Create` e `Update:RequisicaoCompra` (pedir material), só vê as próprias requisições.

## Permissões novas

Criadas pelas migrations `2026_10_26_500000_create_suprimentos_permissions` e `2026_10_27_500000_create_resultado_escola_permissions`.
Papéis que a escola não criou (como `financeiro`) são ignorados; um papel feito à mão precisa recebê-las em **Perfis de acesso**.

| Permissão | Quem recebe |
|---|---|
| `ViewAny`, `View`, `Create`, `Update`, `Delete:Orcamento`; `View:OrcadoRealizado` | administração e financeiro (`Orçado × Realizado` também a coordenação) |
| `Aprovar:Orcamento` | administração |
| `ViewAny`, `View:ItemEstoque`; `Create`, `Update:ItemEstoque`; `Movimentar:ItemEstoque`; `Delete:ItemEstoque` | consulta: administração, secretaria, financeiro, coordenação; cadastro: administração, secretaria; movimentar: administração, secretaria, coordenação; excluir: administração |
| `ViewAny`, `View`, `Create`, `Update:RequisicaoCompra` | administração, secretaria, financeiro, coordenação, professor |
| `VerTodas:RequisicaoCompra` | administração e financeiro |
| `Cotar:RequisicaoCompra` | administração, secretaria, financeiro |
| `Receber:PedidoCompra` | administração e secretaria |
| `AprovarNivel1:PedidoCompra` | administração e coordenação |
| `AprovarNivel2:PedidoCompra` | administração |
| `AprovarNivel3:PedidoCompra` | super administrador |
| `ViewAny`, `View`, `Create`, `Update`, `Delete:ContaRecorrente`; `View:FluxoProjetado`; `View:RentabilidadeRealizada` | administração e financeiro |
| `View:PainelDirecao` | administração, financeiro, coordenação |
| `Fotografar:PainelDirecao`; `Create`, `Update`, `Delete:IndicadorMeta` | administração |
| `ViewAny`, `View:IndicadorMeta` | administração e financeiro |
| `View:SimuladorProximoAno` | administração, secretaria, coordenação |
| `Gerenciar:SimuladorProximoAno` | administração |

Cada indicador do Painel da Direção ainda exige a permissão da tela de onde o número vem (por exemplo `View:RelatorioInadimplencia`); ver o
[documento da Onda 45](painel_direcao_roadmap.md).

## Variáveis de ambiente (todas opcionais)

| Variável | Padrão | Para quê |
|---|---|---|
| `CRM_MESA_CARENCIA_HORAS`, `CRM_MESA_SEM_PROXIMA_ACAO_DIAS`, `CRM_MESA_VISITA_SEM_RESULTADO_HORAS`, `CRM_MESA_QUENTE_PARADO_DIAS`, `CRM_MESA_SLA_JANELA_DIAS` | 2, 2, 4, 3, 30 | Filas do CRM na Minha Mesa |
| `COMPRAS_ALCADA_1_ATE`, `COMPRAS_ALCADA_2_ATE` | 1000, 10000 | Tetos das alçadas de aprovação |
| `COMPRAS_COTACAO_1_ATE`, `COMPRAS_COTACAO_2_ATE` | 500, 5000 | Faixas do mínimo de cotações (1, 2 e 3) |
| `COMPRAS_BLOQUEAR_ACIMA_DO_ORCAMENTO`, `COMPRAS_SEGREGAR_FUNCOES` | falso, verdadeiro | Travas de compras |
| `COMPRAS_MESES_HISTORICO_FORNECEDOR` | 12 | Janela do sinal de fornecedor com divergência |
| `COMPRAS_MESA_COTACAO_DIAS`, `COMPRAS_MESA_APROVACAO_DIAS`, `COMPRAS_MESA_ENTREGA_DIAS` | 3, 2, 15 | Prazos das filas de compras |
| `ORCAMENTO_ALERTA_PERCENTUAL` | 90 | Início da faixa amarela do orçado × realizado |
| `FINANCEIRO_SALDO_BASE` | 0 | Saldo anterior ao primeiro extrato, somado ao fluxo projetado |
| `PAINEL_CACHE_SEGUNDOS` | 600 | Cache do valor de cada indicador do painel |

Os demais parâmetros ficam em `config/financeiro.php` (recorrentes, projeção e rentabilidade), `config/simulador.php` e
`config/painel_direcao.php`.

## Agendador

| Comando | Quando | O que faz |
|---|---|---|
| `financeiro:gerar-contas-recorrentes` | diário, 06:45 | Gera as contas a pagar das despesas recorrentes |
| `painel:fotografar` | diário, 06:55 | Guarda o valor de cada indicador do painel e apaga as fotografias com mais de 800 dias |

## Migrations, em ordem

`2026_10_26_100000` (próxima ação do lead), `200000` (orçamento), `300000` (estoque), `400000` (compras), `500000` (permissões de
suprimentos); `2026_10_27_100000` (contas recorrentes e colunas em `conta_pagars`), `200000` (vínculos do centro de custo), `300000`
(painel: metas e fotografias), `400000` (cenários do simulador) e `500000` (permissões do resultado da escola). Dicionário de dados em
`GEMINI_DB.md`, seções 43 a 50.

## Achados da implementação

| Achado | Onde | O que fazer / o que foi feito |
|---|---|---|
| O `Gate::before` dá `true` ao super administrador para **qualquer** habilidade, inclusive em método de policy como `aprovar`. | Compras | As telas conferem a situação da requisição e a segregação de funções pelo serviço, não só a policy; há teste com super admin. |
| `User::can()` segue o **papel ativo** da sessão; no console vale a união dos papéis. | Filas da Mesa por alçada, testes | Testes fixam `active_role`; a fila *Pedidos para aprovar* mostra o que a **alçada** da pessoa cobre. |
| Quem aprova ou recebe não via a requisição de outra pessoa (a lista filtrava só "dono ou *Ver todas*"), e o link da fila da Mesa levaria a um 404. | Compras | Corrigido: cada pessoa abre as próprias e as que precisam dela; teste cobre cada papel. |
| Coluna com cast `date` grava `Y-m-d 00:00:00` no SQLite; um `updateOrCreate` pela data como texto não encontrava a linha e duplicava. | Fotografias do painel | Consultar por `whereDate`; o dicionário de dados avisa. |
| `Turma` já tinha `mensalidade_base`, `custo_docente_mensal`, `custo_operacional_rateado` e `meta_margem_lucro` e existia a *Rentabilidade de Turmas* (planejado). | Onda 77 | O realizado entrou como tela irmã; não se mexeu na Controladoria. |
| O sistema não tem o conceito de "próxima série": a família escolhe a série de destino na rematrícula. | Onda 76 | A projeção é por série de destino; quem não escolheu entra à parte, só no total. |
| A baixa de fatura não classifica a conta do plano (ver Onda 73), então parte das saídas e entradas fica sem classificação. | Ondas 43 e 77 | As telas avisam quanto ficou de fora; classificar o movimento é pré-requisito para os números fecharem. |
| `ApuracaoHoraAula` é por professor e mês, sem turma. | Onda 77 | Divisão pela proporção das aulas do cronograma; o que não tem aula vai para o custo indireto e a tela avisa. |
| **Registro administrativo no histórico do lead** (tipo "Formulário do site" ou "Movimentação no Funil") passou a exigir a próxima ação. Achado pela suíte completa (`CrmSlaPrimeiroContatoPelaFichaTest`). | Onda 84 | Corrigido: esses tipos não são conversa com a família, então não pedem nem alteram a próxima ação; teste novo cobre. |
| **Quatro falhas que já vêm da `main`** (conferidas rodando os mesmos testes na `origin/main` pura): `UploadsSegurosTest` aponta `Resources/Candidatos/Schemas/CandidatoForm.php` (campo `foto_path` sem `acceptedFileTypes`); `EducacaoBasicaMelhoriasTest::test_can_record_atendimento_enfermagem` (o campo de saúde agora é criptografado e o teste ainda procura o texto puro); `ShieldWidgetsTest::test_questionarios_widget_requires_both_shield_permission_and_pending_questionnaires`; `UnificacaoMatriculaDocumentosTest::test_redirecionamento_suave_de_link_de_convite_legado_para_portal_unificado` (o token do link legado não é mais o esperado). | Fora desta entrega | Arquivos não tocados. Cada uma pede um ajuste próprio (lista branca de `TiposArquivo` no formulário do candidato; teste de saúde lendo o valor descriptografado; revisão dos outros dois). |

## Como foi verificado

- **Testes novos (242):** `MesaDoConsultorTest` (18), `OrcamentoTest` (20), `EstoqueTest` (12), `ComprasServiceTest` (26), `ComprasTelasTest` (22),
  `ContasRecorrentesFluxoTest` (24), `ContasRecorrentesTelasTest` (16), `RentabilidadeTurmaTest` (16), `RentabilidadeTelasTest` (12),
  `PainelDirecaoTest` (27), `PainelDirecaoTelasTest` (14), `SimuladorProximoAnoTest` (20) e `SimuladorProximoAnoTelasTest` (15), mais ajustes em
  `AcoesCrmAutorizacaoTest`, `FunilAcoesInteressadosTest` e `CrmSlaPrimeiroContatoPelaFichaTest`.
- **Suíte completa** (cerca de 3.200 testes, em 6 processos paralelos): as únicas falhas, depois da correção acima, são as **4 que também falham na
  `origin/main` pura**. Depois do rebase sobre as novidades do CRM de outra sessão, os testes de CRM e da Minha Mesa (180) voltaram a passar.
- **MariaDB 10.4 (banco descartável copiado de `torre360_demo`, nunca a produção):** as 10 migrations novas aplicaram, e um roteiro de fumaça
  rodou os serviços de contas recorrentes (inclusive o índice único contra duplicata), fluxo projetado nos três cenários, resultado por turma,
  estoque, orçamento, compras de ponta a ponta (requisição → cotação → aprovação → recebimento → estoque, patrimônio e conta a pagar), painel,
  simulador, todas as filas da Minha Mesa e as três fontes de relatório, sem erro. O banco foi apagado depois.
- **Conferência visual** (Chrome headless sobre o HTML renderizado, em desktop e em 360 px): Painel da Direção, Fluxo Projetado, Resultado
  Realizado, Simulador, requisição de compra com o mapa de cotações, Contas Recorrentes e Orçado × Realizado. Corrigiu o nome do mês em
  inglês e as seções de Compras em meia largura. A tabela larga do simulador rola dentro do cartão no celular. **Limite:** o CSS compilado
  disponível na máquina é de 1º/10, sem as classes de cor e espaçamento novas (as mesmas que dez telas existentes usam), então semáforo,
  setas de variação, faixas de aviso e alguns espaçamentos só aparecem como no desenho depois do build do deploy.
- **Não foi verificado:** a aparência com o CSS recompilado e no tema escuro; as telas com o JavaScript do Livewire em funcionamento (a captura foi
  do HTML sem scripts); o recebimento com nota fiscal real em disco de produção; o desempenho das filas e do painel com o volume real da
  escola (o cálculo percorre faturas, hora-aula e transações do período; as filas mais pesadas usam cache de minutos a horas); e as
  permissões em perfis feitos à mão.

## O que continua proposto

> As ondas 27 e 32 foram entregues depois, em [Reuniões e atas](reunioes_e_atas_roadmap.md).

Veja o [índice das ondas 72 a 79](ondas_72_a_79_indice.md) (75 e 79), o [das ondas 68 a 71](ondas_68_a_71_indice.md) (68 e 70) e os anteriores. Em
especial o conjunto **Ciclo de vida do dado pessoal** (ondas [34](portal_titular_lgpd_roadmap.md) e [79](guarda_descarte_documentos_roadmap.md)):
o pedido do titular (acesso, correção, eliminação) e a rotina de descarte dependem da **mesma tabela de temporalidade** — o que a escola
pode apagar agora e o que precisa guardar, e por quê. Sem ela, atender o pedido de eliminação seria uma decisão manual a cada vez.
