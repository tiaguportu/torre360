# Ondas 72 a 79 — Índice

> Continua no [índice dos conjuntos Suprimentos e Resultado da escola](conjuntos_suprimentos_resultado_indice.md), que traz
> também as ondas 16, 38, 43, 45 e 84 implementadas em 2026-10-27. As propostas para a secretaria e o CRM (ondas 80 a 88) estão no
> [índice das ondas 80 a 88](ondas_80_a_88_indice.md).

Continuação do [índice das ondas 68 a 71](ondas_68_a_71_indice.md). Cada onda tem um documento próprio com contexto, o que
já existe no sistema (conferido no código), escopo, base legal, dependências e riscos.

- **Implementadas (6):** 72, 73 e 74; 76, 77 e 78 (implementadas em 2026-10-27).
- **Propostas (2):** 75 e 79. Não há código, migration nem teste delas.

## Visão geral

| Onda | Tema | Área | Status | Documento |
|---|---|---|---|---|
| 72 | Relatórios Configuráveis e Agendados | Transversal | **Implementada** | [relatorios_configuraveis_agendados_roadmap.md](relatorios_configuraveis_agendados_roadmap.md) |
| 73 | Exportação para a Contabilidade | Financeiro | **Implementada** | [exportacao_contabil_roadmap.md](exportacao_contabil_roadmap.md) |
| 74 | Declarações e Histórico com Assinatura Digital | Secretaria | **Implementada** | [assinatura_declaracoes_historico_roadmap.md](assinatura_declaracoes_historico_roadmap.md) |
| 75 | Acesso do Contador | Financeiro / Segurança | Proposta | [acesso_contador_roadmap.md](acesso_contador_roadmap.md) |
| 76 | Simulador do Próximo Ano Letivo | Gestão | **Implementada** | [simulador_proximo_ano_letivo_roadmap.md](simulador_proximo_ano_letivo_roadmap.md) |
| 77 | Rentabilidade e Custo por Turma | Financeiro / Gestão | **Implementada** | [rentabilidade_por_turma_roadmap.md](rentabilidade_por_turma_roadmap.md) |
| 78 | Contas Recorrentes e Fluxo de Caixa Projetado | Financeiro | **Implementada** | [contas_recorrentes_fluxo_projetado_roadmap.md](contas_recorrentes_fluxo_projetado_roadmap.md) |
| 79 | Guarda e Descarte de Documentos Escolares | Conformidade | Proposta | [guarda_descarte_documentos_roadmap.md](guarda_descarte_documentos_roadmap.md) |

## O que mudou no que já existia

- **Menu:** novo grupo **Relatórios** (*Meus Relatórios*) e, em **Financeiro**, a página **Contabilidade**.
- **Plano de Contas e Bancos:** campos novos *Código no escritório* e *Conta no escritório*. Sem eles a exportação contábil
  aponta pendência; nada mais muda para quem não usa a exportação.
- **Documentos Emitidos (QR) e Histórico Escolar Multi-Ano:** botões de assinatura nas linhas. Uma declaração ou histórico
  **com cópia assinada** perde os botões de editar, excluir, emitir PDF de novo e sincronizar: a cópia assinada é do PDF
  daquele momento.
- **Documentos para Assinatura:** coluna e seção do **nível da assinatura**; documento assinado fora do sistema não lista
  assinantes.
- **Página pública de verificação e Portal da família:** mostram a cópia assinada digitalmente quando existe.
- **Minha Mesa:** fila **Classificação contábil pendente** (permissão `View:ExportacaoContabil`).
- **Agendador:** `relatorios:executar-agendados` (a cada 5 minutos) e `relatorios:limpar-arquivos` (diário, 04:30).
- **Permissões novas**, criadas pelas migrations: `AnexarAssinado:DocumentoAssinatura` (super admin, administração,
  secretaria), `View` e `Exportar:ExportacaoContabil` (administração), `ViewAny`, `View`, `Create`, `Update`, `Delete` e
  `Executar:RelatorioSalvo` (administração, secretaria, coordenação) e `VerTodos:RelatorioSalvo` (administração). Um papel
  criado à mão precisa recebê-las em **Perfis de acesso**.
- **Novas variáveis de ambiente (opcionais):** `RELATORIOS_LIMITE_LINHAS` (20000), `RELATORIOS_LIMITE_LINHAS_PDF` (1500),
  `RELATORIOS_RETENCAO_DIAS` (30), `RELATORIOS_LIMITE_POR_RODADA` (10) e `MESA_CONTABILIDADE_DIA_LIMITE` (10).

### Acrescentado em 2026-10-27 (ondas 76, 77 e 78)

- **Menu:** em **Financeiro**, *Contas Recorrentes*, *Fluxo Projetado* e *Resultado Realizado*; em **Relatórios**, *Simulador do Próximo Ano*
  (ao lado do *Painel da Direção*, da Onda 45).
- **Contas a Pagar:** nova ação **Revisar valor**, selo "valor estimado: a revisar" no valor e filtro **Valor a revisar**. A baixa de uma
  conta gerada de despesa de valor estimado fica **travada até a revisão**. Conta gerada por despesa recorrente tem os campos
  `conta_recorrente_id`, `competencia` e `valor_revisado`.
- **Centros de Custo:** o formulário ganhou a seção *Resultado por turma* (custo direto de uma turma ou de um curso, ou **fora do
  resultado das turmas**) e a lista mostra como cada centro entra no resultado. Sem vínculo, a despesa é custo indireto e é rateada.
- **Agendador:** `financeiro:gerar-contas-recorrentes` (diário, 06:45) e `painel:fotografar` (diário, 06:55).
- **Minha Mesa:** filas *Contas recorrentes a revisar*, *Reajustes de contrato*, *Caixa projetado negativo*, *Turmas com resultado
  negativo* e *Projeção do próximo ano*.
- **Relatórios configuráveis:** três fontes novas (fluxo projetado, resultado por turma e projeção do próximo ano).
- **Permissões novas**, criadas pela migration `create_resultado_escola_permissions`: `ViewAny`, `View`, `Create`, `Update` e
  `Delete:ContaRecorrente`, `View:FluxoProjetado` e `View:RentabilidadeRealizada` (administração e financeiro);
  `View:SimuladorProximoAno` (administração, secretaria, coordenação) e `Gerenciar:SimuladorProximoAno` (administração). As do Painel
  da Direção estão no [índice dos conjuntos](conjuntos_suprimentos_resultado_indice.md). Um papel criado à mão precisa recebê-las.
- **Novas variáveis de ambiente (opcionais):** `FINANCEIRO_SALDO_BASE` (0), e as de Compras e do Painel descritas no índice dos conjuntos.

## Fusões sugeridas para dar completude

| Conjunto | Ondas | O que compartilham | Ordem dentro do conjunto |
|---|---|---|---|
| **Contabilidade ponta a ponta** | 73 + 75 + ampliações da 73 | O arquivo é gerado pela escola (73), entregue sem e-mail ao escritório (75), no leiaute do sistema dele e com a conta do plano por item de fatura. | 73 (feita), conta por item de fatura, 75 (depois do MFA), leiautes |
| **Resultado da escola** *(implementado em 2026-10-27; falta só a visão única orçado × projetado × realizado)* | 77 + 78 + 43 + 45 | Receita, custo, orçamento, projeção e o painel da direção usam as mesmas faturas, contas a pagar, centros de custo e a hora-aula. Quatro telas viram um painel com três visões: orçado, projetado e realizado. | 43, 78, 77, 45 |
| **Planejamento do ano** *(76 e 77 implementadas; o orçamento do próximo ano ainda não nasce do cenário escolhido)* | 76 + 77 + 44 + 20 | A projeção de matrículas e o ponto de equilíbrio por turma saem dos mesmos dados (rematrícula, lista de espera, risco de evasão, reajuste, custo da hora-aula). | 77, 76 |
| **Reuniões e atas** *(implementado em 2026-10-28)* | 27 + 32 | Conselho de classe e reunião com professores são a mesma coisa vista de dois lados: agendamento com reserva de sala, presença, ata que se fecha e encaminhamentos com responsável e prazo. Uma reunião com `tipo`; o conselho ganha a pauta por aluno. Veja [Reuniões e atas](reunioes_e_atas_roadmap.md). | 27 + 32 (feitas), 28 recebe os encaminhamentos |
| **Dado pessoal: ciclo de vida** | 34 + 79 | O pedido de eliminação do titular e a rotina de descarte consultam a mesma tabela de temporalidade. | 34, 79 (ou juntas) |
| **Relatórios como saída comum** | 72 + 43 + 45 + 47 | O digest diário, o painel e o orçamento podem usar os relatórios agendados e as fontes da Onda 72 como entrega, em vez de montar a sua. | Fontes novas em cada módulo |

## Ampliações propostas em ondas já existentes

| Onda | Ampliação | Observação |
|---|---|---|
| 72 (relatórios) | **Mais fontes:** CRM (funil, visitas), RH (férias, contratos), Biblioteca, Patrimônio, Cantina e Loja. **Totais e agrupamento**, compartilhar com um papel, entrega pela Central de Avisos (Onda 70). | Cada fonte é uma classe de ~80 linhas e uma entrada em `config/relatorios.php`. |
| 73 (contabilidade) | **Leiautes por sistema contábil** e importação do plano de contas do escritório; **conta do plano por item de fatura** e data de competência; **trava de período**; provisões. | A conta por item de fatura destrava a Onda 77. |
| 74 (assinatura) | **Validação técnica** da assinatura (ITI), **carimbo do tempo**, **assinatura em lote** de históricos, certificados de conclusão. | O inspetor lê o PDF e não valida a cadeia; a validação fecha esse limite. |
| 69 (assinatura) | **Lembrete automático** e **ordem de assinatura** (herdado do índice anterior). | Continua proposto. |
| 57 (FICAI) | Emitir o **ofício a partir de um modelo editável** (Onda 69), mandar para a assinatura da direção (Onda 74) e levar para a Mesa os casos parados (sem contato com a família ou sem resposta do Conselho). | A Onda 57 já registra as tentativas de contato; falta o ofício sair pelo mesmo caminho dos demais documentos. |
| 71 (mesa) | Filas de contas a pagar vencendo e relatórios agendados que falharam (a de recorrentes sem valor revisado já existe, da Onda 78). | Cada fila é uma classe pequena. |

## Achados da implementação

| Achado | Onde | O que fazer |
|---|---|---|
| **`dia_nao_letivo.curso_id` não existe em bancos MySQL montados pela cadeia de migrations**: a migration `2026_03_27_224946` remove a coluna fora do SQLite. Quatro pontos do código ainda filtram por ela (`Portal/Pages/Calendario`, `HorarioAlunoService`, `GradeHorarioService`, `ReservaEspacoConflitoService`), e o model e o formulário de dia não letivo também a preveem. No SQLite dos testes a coluna existe, então nenhum teste vê o problema. | Verificado em um banco de teste montado a partir do esquema de `torre360_demo` mais todas as migrations pendentes. **Não foi verificado em produção.** | Conferir `SHOW COLUMNS FROM dia_nao_letivo` na produção. Se a coluna não existir: recriá-la (cadastro por curso) ou tirar os filtros. |
| O estado de um `Select` com enum chega ao `Get` ora como o enum, ora como texto; comparar com `->value` falha em silêncio (o campo dependente some e o servidor recusa por "campo faltando"). | `RelatorioSalvoForm` (campo *Dia* do agendamento) | Normalizar com `Enum::tryFrom` antes de comparar. |
| `User::is_active` é um acessor, não coluna: `where('is_active', true)` quebra a consulta. | Lista de destinatários dos relatórios | Usar o escopo `User::ativos()`. |
| O usuário em memória guarda os papéis (e as permissões deles) já carregados; revogar uma permissão no meio de um teste exige reler o usuário. | Testes | `User::findOrFail($id)` depois de revogar. |
| Engenharia | A suíte roda só em SQLite; o achado acima só apareceu com MariaDB. | Rodar a suíte também em MariaDB no CI (já proposto no índice anterior). |
