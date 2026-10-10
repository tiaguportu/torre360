# Documentação do Banco de Dados - Torre360

Esta documentação descreve a estrutura do banco de dados do sistema Torre360, categorizando as tabelas por módulos funcionais e detalhando seus propósitos, colunas e relacionamentos.

> [!IMPORTANT]
> Esta documentação deve ser mantida atualizada conforme novas migrações e modelos forem adicionados ao projeto.

---

## 1. Módulo Core e Segurança
Responsável pela gestão de usuários, logs de auditoria e configurações globais do sistema.

### `users`
- **Representa:** Usuários com acesso ao painel administrativo/Filament.
- **Relacionamentos:** Muitos-para-Muitos com `pessoa`.
- **Principais Campos:** `name`, `email`, `password`, `is_active`.

### `audit_logs`
- **Representa:** Registro técnico de acessos e auditoria legada.
- **Relacionamentos:** BelongsTo `users`, MorphTo `auditable`.

### `notifications`
- **Representa:** Sistema de notificações unificado do Laravel/Filament (Sininho, E-mail e Push).
- **Propósito:** Armazena mensagens destinadas aos usuários. Suporta canais de Banco de Dados, E-mail e Push (via `FcmChannel`).
- **Campos Principais:** `id` (UUID), `type`, `notifiable_type`, `notifiable_id`, `data` (JSON), `read_at`.
- **Integração:** Utilizada pelo `NotificationService` para gerenciar disparos multicanal.

### `activity_log` (Spatie)
- **Representa:** Registro de atividades de negócio e trilha de auditoria detalhada.
- **Campos Principais:** 
    - `log_name`: Canal do log (ex: `default`, `auth`, `frequencia_escolar`).
    - `description`: Descrição amigável do evento.
    - `subject_type`, `subject_id`: Registro afetado.
    - `causer_type`, `causer_id`: Usuário/Sistema que causou a ação.
- `properties`: JSON com metadados e alterações (valores antigos e novos). Registra também tentativas e respostas de disparos de notificações (e-mail/push).

### `roles`, `permissions`, `role_has_permissions`, `model_has_roles` (Spatie Permission & Filament Shield)
- **Representa:** Sistema de controle de acesso baseado em papéis (RBAC).
- **Propósito:** Define permissões granulares para os recursos do painel administrativo (Resources, Pages, Widgets e Ações Customizadas).
- **Convenção de Nomenclatura:** Formato `PascalCase` com dois-pontos `:` (ex.: `ViewAny:Matricula`, `Create:Aluno`, `Execute:ReguaCobranca`).
- **Higienização:** Permissões legadas com formato obsoleto `::` e sintaxe mista foram completamente removidas e consolidadas no padrão oficial.
- **Mapeamento de Policies:** Cada Resource do Filament possui uma Policy correspondente em `App\Policies\{Model}Policy` consumindo as permissões associadas.
- **Papéis Padrão:** `super_admin`, `admin`, `secretaria`, `professor`, `coordenador`, `responsavel`, `aluno`.
- **Configuração:** Gerenciado via `config/filament-shield.php` e plugin `FilamentShieldPlugin`.

### `configuracao`
- **Representa:** Parâmetros globais do sistema, modelos de textos, macros dinâmicas e integrações.
- **Principais Campos:** `campo` (string chave única), `valor` (longtext/text nullable), `grupo` (string), `ordem` (integer).
- **Templates e Macros de Contrato:** As chaves de macros dinâmicas (`template_contrato_assinatura_pai`, `template_contrato_assinatura_mae`, `template_contrato_assinatura_responsavel_financeiro`, `template_contrato_assinaturas_responsaveis`, etc.) armazenam fragmentos Blade declarativos e estritos, sem blocos `@php ... @endphp`. Isso atende à política defensiva do `BladeTemplateSanitizer` contra Server-Side Template Injection (SSTI / VULN-18), com as variáveis contextuais de parentesco e responsabilidade financeira (`$pai`, `$mae`, `$paiId`, `$maeId`, `$paiResponsavel`, `$maeResponsavel`, `$isResponsavelFinanceiro`) sendo injetadas de forma segura e pré-computada pelo `ContractTemplateService`.

---

## 2. Pessoas e Geografia
Base cadastral de qualquer indivíduo ou entidade no sistema.

### `pessoa`
- **Campos Principais:** `nome`, `cpf` (armazenado apenas como dígitos numéricos, sem pontos ou traço), `data_nascimento`, `foto` (armazenamento privado), `email`, `aceita_comunicacao` (boolean, padrão true — opt-out de comunicações em massa por LGPD; não afeta notificações individuais obrigatórias), `consentimento_em`/`consentimento_versao`/`consentimento_origem`/`consentimento_ip` (prova do aceite dado no formulário público ou na pré-matrícula; gravados por `Pessoa::registrarConsentimento()`, fora do `$fillable`; pessoas antigas ficam sem registro), `descadastrado_em` (timestamp nullable — pedido de descadastro pelo link dos e-mails da régua; `Pessoa::descadastrarDeComunicacoes()` também põe `aceita_comunicacao = false`), `sexo` (Enum), `cor_raca` (Enum - 0:Não Declarada, 1:Branca, 2:Preta, 3:Parda, 4:Amarela, 5:Indígena), `tipo_nacionalidade` (Enum - 1:Brasileira, 2:Naturalizado/Exterior, 3:Estrangeira), `nacionalidade_id` (Pais).
- **Índice:** `pessoa_email_idx` (`email`). **Scope:** `busca($termo)` — nome, e-mail, telefone (ignora `( ) - + e espaço`) e CPF (dígitos); menos de 3 dígitos não entram na busca de telefone/CPF.
- **Relacionamentos:** 
    - BelongsToMany `endereco` (via `endereco_pessoa`).
    - BelongsTo `cidade` (naturalidade), `pais` (nacionalidade).
    - HasMany `matriculas`.
    - BelongsToMany `unidade` (via `representante_unidade`) como Representante Legal.
    - HasMany `necessidadesEducacaoEspecial`.
    - HasMany `transtornosAprendizagem`.
    - HasMany `recursosAcessibilidade`.

### `pais`
- **Representa:** Países cadastrados na base geográfica.
- **Campos Principais:** `nome`, `sigla`, `codigo` (Código oficial INEP/Educacenso).

### `endereco`
- **Representa:** Localização física de pessoas ou unidades.
- **Campos Principais:**
    - `tipo`: Enum ('residencial', 'comercial'). Define a natureza do endereço.
    - `logradouro`: Nome da rua/avenida.
    - `numero`: Número do imóvel.
    - `complemento`: Complemento do endereço (ex: Apto 101, Bloco B).
    - `bairro`: Bairro.
    - `cidade_id`: BelongsTo `cidade`.
    - `cep`: Código postal.
- **Relacionamentos:**
    - BelongsToMany `pessoa` (via `endereco_pessoa`).
    - HasMany `unidade`.

### `endereco_pessoa`
- **Representa:** Tabela pivô entre pessoas e endereços.
- **Campos:** `pessoa_id`, `endereco_id`.

### `instituicao_ensinos`
- **Representa:** Uma instituição de ensino que pode possuir várias unidades.
- **Campos Principais:** `nome`, `cnpj`, `codigo_inep`, `orgao_vinculado_escola_publica`, `flag_secretaria_educacao_mec`, `flag_seguranca_publica_forcas_armadas`, `flag_secretaria_saude`, `flag_outro_orgao_publico`, `logo` (caminho da imagem), `celular_whatsapp`, `instagram`, `facebook`, `youtube`, `flag_ativo`.
- **Relacionamentos:** BelongsTo `endereco`, HasMany `unidade`.

### `unidade`
- **Representa:** Uma unidade escolar ou administrativa pertencente a uma instituição.
- **Campos Principais:** `instituicao_ensino_id`, `nome`, `cnpj`, `codigo_inep`, `situacao_funcionamento` (1-Em atividade, 2-Paralisada, 3-Extinta), `telefone`, `email`, `codigo_orgao_regional_ensino`, `localizacao_zona` (1-Urbana, 2-Rural), `localizacao_diferenciada` (1, 2, 3, 7, 8), `dependencia_administrativa` (1, 2, 3, 4), `celular_whatsapp`, `instagram`, `facebook`, `youtube`.
- **Relacionamentos:** 
    - BelongsTo `instituicao_ensino`.
    - BelongsTo `endereco`.
    - HasMany `curso`.
    - BelongsToMany `pessoa` (via `representante_unidade`) para Representantes Legais.

### `template_crachas`
- **Representa:** Modelos de layout de crachás para pessoas ou turmas.
- **Campos Principais:**
    - `nome`: Identificação amigável do template.
    - `tipo_entidade`: Enum/String ('pessoa', 'turma'). Define o contexto do crachá e os campos variáveis disponíveis.
    - `largura`: Largura do crachá em pixels (default 300).
    - `altura`: Altura do crachá em pixels (default 480).
    - `imagem_fundo`: Caminho da imagem de fundo.
    - `dados_layout`: JSON contendo os elementos de texto, estilos e imagens do canvas Fabric.js.

### `categoria_necessidade_educacao_especiais`
- **Representa:** Categorias de necessidades de educação especial (ex: Baixa visão, Cegueira, Surdez, TEA, etc.).
- **Campos Principais:** `nome` (único), `descricao`.

### `necessidade_educacao_especiais`
- **Representa:** Registros de necessidades de educação especial vinculados a uma pessoa.
- **Campos Principais:** `pessoa_id` (FK `pessoa`), `categoria_necessidade_educacao_especial_id` (FK `categoria_necessidade_educacao_especiais`), `observacao`.

### `categoria_transtorno_aprendizagens`
- **Representa:** Categorias de transtornos de aprendizagem (ex: Dislexia, TDAH, Discalculia, etc.).
- **Campos Principais:** `nome` (único), `descricao`.

### `transtorno_aprendizagens`
- **Representa:** Registros de transtornos de aprendizagem vinculados a uma pessoa.
- **Campos Principais:** `pessoa_id` (FK `pessoa`), `categoria_transtorno_aprendizagem_id` (FK `categoria_transtorno_aprendizagens`), `observacao`.

### `categoria_recurso_acessabilidades`
- **Representa:** Categorias de recursos de acessibilidade (ex: Tradutor-intérprete de Libras, Prova ampliada, etc.).
- **Campos Principais:** `nome` (único), `descricao`.

### `recurso_acessabilidades`
- **Representa:** Registros de recursos de acessibilidade vinculados a uma pessoa.
- **Campos Principais:** `pessoa_id` (FK `pessoa`), `categoria_recurso_acessabilidade_id` (FK `categoria_recurso_acessabilidades`), `observacao`.

---

## 3. Gestão Acadêmica
Estrutura de ensino e turmas.

### `curso`, `serie`, `turma`
- Estrutura hierárquica de ensino. Cursos possuem Séries, que possuem Turmas.
- **Turma - Campos Principais:** `nome`, `codigo`, `serie_id`, `turno_id`, `periodo_letivo_id` (**obrigatório**, FK `restrict`: não se apaga um período que tenha turmas), `status` (string, default `ativa`; cast para o Enum `StatusTurma`: `planejada`, `ativa`, `concluida`, `cancelada` — turma "aberta para matrícula" = planejada ou ativa; scopes `Turma::ativas()`, `abertasParaMatricula()` e `vigentes()`), `etapa_ensino_agregada_id`, `etapa_ensino_id`, `professor_conselheiro_id`, `vagas_maximas`, `carga_horaria_total` (em horas), `cor`, `tipo_avaliacao` (Enum: notas, habilidades, hibrido), `tipo_mediacao_didatico_pedagogica` (1-Presencial, 2-Semipresencial, 3-EAD), `tipo_turma` (4-Atividade complementar, 5-AEE, 6-Curricular, 9-Curricular c/ Ativ. Comp.), `local_funcionamento_diferenciado` (0-Não diferenciado, 1-Sala anexa, 2-Unidade socioeducativa, 3-Unidade prisional), `turma_educacao_especial` (boolean), `forma_organizacao` (1-Série/Ano, 2-Semestral, 3-Ciclos, 4-Grupos não seriados, 5-Módulos, 6-Alternância), `modalidade_ensino` (1-Regular, 2-Especial, 3-EJA, 4-Profissional), `tipo_lingua_ministrada` (1-Português, 2-Indígena+Português, 3-Indígena), `codigo_lingua_indigena`, `turma_educacao_bilingue_surdos` (boolean), flags de AEE (`flag_aee_*`) e **Controladoria Escolar**: `mensalidade_base` (Decimal 10,2 - mensalidade de tabela de referência), `custo_docente_mensal` (Decimal 10,2 - custo mensal direto da folha docente da turma), `custo_operacional_rateado` (Decimal 10,2 - rateio fixo de infraestrutura e suporte para a turma), `meta_margem_lucro` (Decimal 5,2 - meta percentual esperada de margem líquida).
- **Relacionamentos:** BelongsTo `etapaEnsinoAgregada` (`etapa_ensino_agregada`), BelongsTo `etapaEnsino` (`etapa_ensino`), HasMany `horariosFuncionamento` (`turma_horario`), HasMany `propostasComerciais` (`proposta_comercials`).

### `matricula`
- **Representa:** Registro de matrícula acadêmica de um estudante na instituição de ensino.
- **Campos Principais:**
    - `pessoa_id`: BelongsTo `pessoa` (Aluno).
    - `turma_id`: BelongsTo `turma` (**obrigatório**, FK `restrict`: não se apaga uma turma que tenha matrículas). Toda matrícula nasce numa turma (rematrícula, wizard, matrícula em lote e online escolhem a turma).
    - **Período letivo e série NÃO são colunas** (as antigas `periodo_letivo_id` e `serie_id` foram removidas): vêm sempre da turma. No model, `periodoLetivo()` e `serie()` são `HasOneThrough` via `turma`; `periodo_letivo_id` e `serie_id` são accessors somente leitura; `Matricula::doPeriodo($id)` e `daSerie($id)` filtram por elas. `Matricula` carrega a `turma` por padrão (`$with`). Criar/atualizar com essas chaves lança `LogicException` nos testes. A migration `require_turma_and_drop_periodo_serie_from_matricula_table` aborta, sem alterar nada, se existir matrícula sem turma; o `down()` recria as colunas e as preenche a partir da turma.
    - `situacao`: Enum `SituacaoMatricula` (`ativa`, `trancada`, `cancelada`, `concluido`, `reserva`, `pendente`, `evasao`).
    - `data_ativacao`: Data em que a matrícula entrou em vigência.
    - `data_desativacao`: Data de cancelamento ou encerramento da matrícula.
- **Relacionamentos:**
    - BelongsTo `pessoa`, BelongsTo `turma`, BelongsTo `serie`, BelongsTo `periodoLetivo`.
    - HasOne `contrato`.
    - HasMany `rematriculas` (via `matricula_origem_id`).
    - HasMany `frequencias`.
    - HasMany `notas`.

### `etapa_ensino_agregada`
- **Representa:** Agrupamento/Categoria macro das etapas do Educacenso/INEP (ex: 301 - Educação Infantil, 302 - Ensino Fundamental, 304 - Ensino Médio, etc.).
- **Campos Principais:** `codigo` (string/unique), `nome`.
- **Relacionamentos:** HasMany `etapasEnsino` (`etapa_ensino`).

### `etapa_ensino`
- **Representa:** Etapa específica de ensino conforme a classificação oficial do Educacenso/INEP.
- **Campos Principais:** `etapa_ensino_agregada_id`, `codigo` (string/unique), `nome`.
- **Relacionamentos:** BelongsTo `etapaEnsinoAgregada` (`etapa_ensino_agregada`).

### `turma_horario`
- **Representa:** Horário de funcionamento da turma por dia da semana (Domingo a Sábado).
- **Campos Principais:** `turma_id`, `dia_semana` (0=Domingo, 1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado), `hora_inicio`, `hora_fim`.
- **Relacionamentos:** BelongsTo `turma`.

### `disciplina`
- **Representa:** Matérias ou componentes curriculares.
- **Campos Principais:** `nome`, `slug`, `cor`, `ordem_boletim`.
- **Relacionamentos:** HasMany `cronograma_aula`, HasMany `habilidades`, HasMany `avaliacoes`, HasManyThrough `notas` (via `avaliacao`), BelongsToMany `turma` (via `turma_disciplina`).
- **Propósito da `ordem_boletim`:** Define a sequência numérica para ordenação das disciplinas na visualização e impressão de boletins.
- **Restrição de Integridade:** Não pode ser editada nem excluída (individualmente ou em lote) caso já possua notas lançadas em alguma avaliação vinculada (`possuiNotasVinculadas()`). Bloqueio aplicado na camada de UI (Filament), nas páginas de edição/listagem.

### `turma_disciplina`
- **Representa:** Tabela pivô que define a grade curricular (disciplinas) de uma turma específica.
- **Campos:** `turma_id`, `disciplina_id`, `professor_id` (pivot, nullable).

### `matriz_curricular`
- **Representa:** Grade de referência (série × disciplina): o que uma turma dessa série deve ter e a carga horária semanal esperada. É a origem do vínculo `turma_disciplina` — ao criar uma turma, `MatrizCurricularService::sincronizarTurmaDisciplinas()` popula automaticamente as disciplinas faltantes (sem sobrescrever vínculos manuais já existentes).
- **Campos Principais:** `serie_id`, `disciplina_id`, `carga_horaria_semanal` (nullable), `obrigatoria` (boolean, padrão true), `ordem` (nullable). Único por `(serie_id, disciplina_id)`.
- **Relacionamentos:** BelongsTo `serie`, BelongsTo `disciplina`.
- **UI:** Relation manager na tela de Série; ação "Sincronizar Disciplinas da Matriz" na tela de Turma.

### `sala`
- **Representa:** Ambiente físico da unidade (sala de aula, laboratório, quadra etc.), usado para reservar espaço na grade horária. **Não confundir** com o remanejamento de `EnsalamentoService`, que move alunos entre turmas.
- **Campos Principais:** `unidade_id`, `nome`, `capacidade` (nullable), `tipo` (nullable), `ativa` (boolean, padrão true).
- **Relacionamentos:** BelongsTo `unidade`, HasMany `grade_horario`.

### `grade_horario`
- **Representa:** Horário recorrente da grade semanal de uma turma — vale para todas as semanas do período letivo. É a origem do `cronograma_aula` gerado por `GradeHorarioService::gerarCronograma()`.
- **Campos Principais:** `turma_id`, `disciplina_id`, `professor_id` (FK `pessoa`, nullable), `sala_id` (nullable), `dia_semana` (mesma convenção de `turma_horario`: 0=Domingo...6=Sábado), `hora_inicio`, `hora_fim`.
- **Relacionamentos:** BelongsTo `turma`, `disciplina`, `professor` (Pessoa), `sala`.
- **Conflitos:** `GradeHorarioConflitoService::conflitos()` detecta sobreposição de horário (mesmo dia da semana + intervalo cruzado) para a mesma turma, o mesmo professor ou a mesma sala; usado para bloquear o cadastro na UI (RelationManager da Turma).

### `matricula`
- **Representa:** Vínculo do aluno com uma turma em um período letivo.
- **Campos Principais:**
    - `situacao`: Enum (`App\Enums\SituacaoMatricula`). Estados: `ativa`, `pendente`, `trancada`, `cancelada`, `concluido`, `reserva`, `evasao`.
    - `data_ativacao`: Data (date, nullable) de ativação da matrícula.
    - `data_desativacao`: Data (date, nullable) de desativação ou encerramento da matrícula.
    - `periodo_letivo_id`: BelongsTo `periodo_letivo`.
    - `turma_id`: BelongsTo `turma`.
    - `pessoa_id`: BelongsTo `pessoa` (Aluno).
- **Lógica de Negócio (Modelo):**
    - `hasActivePreceptoria()`: verifica existência de sessões futuras agendadas.
    - `hasPreceptoriaInActiveCycles()`: verifica existência de sessões (passadas ou futuras) vinculadas a ciclos vigentes.
    - `hasAvailablePreceptoriaWindows()`: verifica existência de horários livres no sistema.
    - `notifyPossibilityPreceptoria()`: dispara notificações multicanal (E-mail, Push, Banco) registradas em log.

---

## 4. Avaliação e Frequência
### `categoria_avaliacao`
- **Representa:** Categorias de avaliações (ex: Prova, Trabalho, Simulado).
- **Campos Principais:** `nome`, `descricao`, `ordem_boletim`.
- **Propósito da `ordem_boletim`:** Define a sequência numérica para ordenação das categorias de avaliação no boletim.

### `avaliacao`
- **Representa:** Atividades avaliativas aplicadas às turmas.
- **Campos Principais:** `turma_id`, `disciplina_id`, `etapa_avaliativa_id`, `categoria_avaliacao_id`, `professor_id` (nullable), `data_prevista`, `data_limite_lancamento`, `nota_maxima`, `peso_etapa_avaliativa`.
- **Restrição de Unicidade:** Possui um índice composto exclusivo (`avaliacao_composite_unique`) que impede a existência de duas avaliações com a mesma combinação de: **Turma, Disciplina, Etapa Avaliativa, Categoria e Professor**.
- **Auditoria:** Mapeado para o log de atividades (`activity_log` com `log_name: avaliacao`), gravando ações de criação, alteração e exclusão com a identificação do usuário responsável (`causer`).

### `nota`
- **Representa:** Notas individuais dos alunos em cada avaliação.
- **Campos Principais:** `avaliacao_id`, `matricula_id`, `valor` (nullable), `situacao` (nullable, Enum `SituacaoNota`: `faltou`, `nao_se_aplica`).
- **Situação (justificativa de ausência de nota):** Para avaliações aplicadas só a alunos específicos ou quando o aluno faltou, a nota é gravada com `valor = null` e `situacao` preenchida. Esse registro conta como **resolvido** em `Avaliacao::scopePendentes()`, `tem_pendencia` e `notas_pendentes_count` (resolvida = `valor` não nulo **ou** `situacao` não nula), mas **não entra em médias**, pois boletim e fechamento de ciclo consideram apenas `valor` não nulo. `situacao` e `valor` são mutuamente exclusivos (o serviço `NotaLancamentoService` zera um ao gravar o outro).
- **Relacionamentos:** BelongsTo `avaliacao`, BelongsTo `matricula` (Aluno).

### `periodo_letivo` (colunas de fechamento do ciclo)
- **Campos de corte:** `nota_aprovacao` (padrão 7.00), `nota_recuperacao_minima` (padrão 5.00) — usados por `FechamentoCicloService::classificarSituacao()`.
- **Campos de recuperação/exame final:** `recuperacao_por_etapa` (boolean, padrão `false` — liga o modo "recuperação por etapa" em vez do modo anual, ver `situacao_final_disciplina` abaixo), `exame_final_habilitado` (boolean, padrão `false`), `nota_aprovacao_pos_exame` (decimal, padrão 5.00 — nota de corte após o exame final).

### `situacao_final_disciplina`
- **Representa:** Situação final (Aprovado/Recuperação/Reprovado) de uma matrícula numa disciplina, gerada pelo Fechamento do Ciclo Letivo (`FechamentoCicloService::fecharPeriodoLetivo()`). Um registro por `(matricula_id, disciplina_id, periodo_letivo_id)` — recalcular substitui o anterior.
- **Campos Principais:** `matricula_id`, `disciplina_id`, `periodo_letivo_id`, `media_final` (nullable), `situacao` (Enum `SituacaoFinal`: aprovado/recuperacao/reprovado), `calculado_em`.
- **Campos de exame final:** `nota_exame_final`, `media_final_pos_exame` (= média simples entre `media_final` e `nota_exame_final`), `situacao_final_pos_exame` (Enum `SituacaoFinal`, só aprovado/reprovado). Lançados via `FechamentoCicloService::registrarExameFinal()`, só permitido quando `situacao` é `recuperacao` e o período letivo tem `exame_final_habilitado`. Um recálculo do fechamento apaga esses três campos se a situação deixar de ser `recuperacao`, e preserva se continuar sendo.
- **Recuperação anual vs. por etapa:** controlado por `periodo_letivo.recuperacao_por_etapa`. No modo anual (padrão), todas as avaliações de `categoria_avaliacao.eh_recuperacao=true` do período são somadas num único valor que substitui só a menor média de etapa. No modo por etapa, cada avaliação de recuperação (via seu próprio `etapa_avaliativa_id`) só substitui a média daquela mesma etapa, de forma independente.
- **Relacionamentos:** BelongsTo `matricula`, `disciplina`, `periodoLetivo`.

### `campo_experiencias`
- **Representa:** Categorias da BNCC para Educação Infantil (ex: "O eu, o outro e o nós").
- **Campos Principais:** `nome`, `descricao`.
- **Relacionamentos:** HasMany `habilidades`.

### `habilidades`
- **Representa:** Banco de competências e habilidades (BNCC ou Institucionais).
- **Campos Principais:** `codigo` (BNCC), `nome`, `tipo` (Enum: BNCC, Institucional), `campo_experiencia_id` (BelongsTo).
- **Nome de tabela (histórico):** uma migração antiga renomeia `habilidades` para o singular `habilidade`, e migrações posteriores tratavam a existência de qualquer uma das duas como "schema já correto". Numa instalação do zero isso deixava a tabela apenas com o schema legado (sem `codigo`/`nome`/`tipo`) e uma migração seguinte a excluía por completo, quebrando qualquer teste ou instalação nova (`2026_04_18_000000_create_habilidades_tables.php` corrigido para checar a coluna `codigo`, não só o nome da tabela). No banco já em uso isso não muda nada — a tabela `habilidades` já existia com o schema correto.

### `turma_habilidade`
- **Representa:** Tabela pivô que define quais habilidades serão avaliadas em uma turma específica.
- **Campos:** `turma_id`, `habilidade_id`.

### `avaliacao_habilidades`
- **Representa:** Registro do cabeçalho da avaliação de habilidades em uma determinada turma e etapa.
- **Campos Principais:** `turma_id`, `etapa_avaliativa_id`, `professor_id` (nullable).

### `nota_habilidades`
- **Representa:** Registro do desempenho de um aluno em uma habilidade específica, associado a uma avaliação de habilidade.
- **Campos Principais:** `avaliacao_habilidade_id`, `matricula_id`, `habilidade_id`, `conceito`, `observacao` (nullable).
- **Conceito (Enum):** `realiza_bem`, `em_desenvolvimento`, `nao_realiza`, `nao_observado`.

### `cronograma_aula`
- **Representa:** Planejamento e agendamento de aulas.
- **Relacionamentos:** BelongsTo `turma`, BelongsTo `disciplina`, BelongsTo `pessoa` (Professor), HasMany `frequencias`.
- **Campos Principais:** `turma_id`, `disciplina_id`, `pessoa_id`, `data`, `hora_inicio`, `hora_fim`, `conteudo_ministrado`.

### `plano_aula`
- **Representa:** O que o professor planeja lecionar numa data futura (objetivos, metodologia, recursos, avaliação prevista e habilidades BNCC). Ao ser executado (`PlanoAulaService::executar()`), gera o registro real no diário (`cronograma_aula`) e não pode mais ser editado nem executado de novo.
- **Campos Principais:** `turma_id`, `disciplina_id`, `professor_id` (FK `pessoa`, nullable), `data_prevista`, `objetivos`, `metodologia` (nullable), `recursos` (nullable), `avaliacao` (nullable), `anexo_material` (json, nullable), `cronograma_aula_id` (nullable, preenchido ao executar), `executado_em` (nullable).
- **Relacionamentos:** BelongsTo `turma`, `disciplina`, `professor` (Pessoa), `cronogramaAula`; BelongsToMany `habilidades` (via `plano_aula_habilidade`).
- **Escopo por professor:** mesmo critério de `CronogramaAulaResource` (professor da disciplina na turma, professor conselheiro, ou autor do plano).

### `plano_aula_habilidade`
- **Representa:** Tabela pivô entre `plano_aula` e `habilidades` (habilidades BNCC previstas), copiada para `cronograma_aula_habilidade` quando o plano é executado.
- **Campos:** `plano_aula_id`, `habilidade_id`.

### `frequencia_escolar`
- **Representa:** Presença ou falta dos alunos em uma aula do cronograma.
- **Relacionamentos:** BelongsTo `matricula`, BelongsTo `cronograma_aula`.
- **Campos Principais:** `matricula_id`, `cronograma_aula_id`, `situacao` (Enum/String: 'presente', 'ausente').
- **Auditoria:** Mapeado para o log de atividades (`activity_log` com `log_name: frequencia_escolar`), gravando ações de criação, alteração e exclusão das frequências com a identificação do aluno, aula e situação atribuída.
- **Observer:** `FrequenciaFaltaObserver` (registrado via atributo `#[ObservedBy]` no model) dispara `FrequenciaAusenciaNotification` para o(s) responsável(is) do aluno sempre que `situacao` é criada ou alterada para `'ausente'`. Não notifica quando a data da aula está fora do intervalo `data_ativacao`–`data_desativacao` da matrícula. Ver seção 14.

---

## 5. Gestão Financeira
### `contrato`
- Acordo comercial de prestação de serviço.
- **Principais Campos:** `valor_total`, `data_aceite`, `template_contrato_id`, `matricula_id`.
- **Campos de Assinatura (Assinafy):** `assinafy_id`, `assinafy_status`, `assinafy_request_log`.
- **Relacionamentos:** BelongsTo `matricula` (Aluno), HasMany `responsavel_financeiro`, HasMany `faturas`, BelongsTo `template_contratos`.

### `template_contratos`
- **Representa:** Modelos de contrato com conteúdo HTML e macros.
- **Campos Principais:**
    - `nome`: Identificação do template.
    - `cabecalho` (longText, nullable): Conteúdo de cabeçalho.
    - `conteudo` (longText): Conteúdo principal.
    - `rodape` (longText, nullable): Conteúdo de rodapé.
    - `is_padrao` (boolean): Flag que indica se este é o modelo padrão ativo no sistema.
- **Relacionamentos:** HasMany `contrato`.

### `faturas` e `item_faturas`
- **Representa:** Cobranças e parcelamentos gerados a partir dos contratos de prestação de serviços educacionais.
- **`faturas` — Campos Principais:**
    - `contrato_id`: FK `contrato`.
    - `vencimento`: Data de vencimento da fatura.
    - `status`: Enum (`App\Enums\StatusFatura`). Estados: `pendente` (amarelo), `pago` (verde), `atrasado` (vermelho), `cancelado` (cinza), `parcial` (azul - pago parcialmente).
    - `pix_copia_e_cola`: Código ou chave PIX para pagamento (herdado da antiga `titulos`; desde a Onda 6, preenchido de verdade por `GatewayPagamento::criarCobranca()`, não mais um texto fixo).
    - `gateway`, `gateway_id` (único), `status_gateway`, `linha_digitavel`, `boleto_url`, `link_pagamento`: campos da Onda 6 — dados da cobrança gerada no gateway configurado (`config('pagamentos.driver')`). `gateway_id` é o que o webhook de pagamento usa para localizar a fatura.
- **Lógica de Negócio e Atributos Computados (Model `Fatura`):**
    - `valor_bruto`: Soma total dos itens sem descontos.
    - `valor`: Valor total líquido com descontos aplicados (absolutos ou percentuais).
    - `valor_pago`: Soma das transações bancárias de entrada vinculadas à fatura.
    - `valor_restante`: Saldo devedor pendente (`valor` − `valor_pago`).
- **Relacionamentos:** BelongsTo `contrato`, HasMany `itens` (`item_faturas`), HasMany `transacoes` (`transacao_bancarias`).

### `transacao_bancarias`
- **Representa:** Registros do fluxo de caixa e conciliação bancária (entradas e saídas).
- **Campos Principais:**
    - `banco_id`: FK `bancos`.
    - `fatura_id` (nullable): FK `faturas` — vincula a transação a uma cobrança recebida.
    - `plano_conta_id` (nullable): FK `plano_contas`.
    - `centro_custo_id` (nullable): FK `centro_custos`.
    - `fornecedor_id` (nullable): FK `fornecedors`.
    - `tipo`: Enum/String (`entrada`, `saida`).
    - `valor`: Valor numérico da transação.
    - `data_transacao`: Data de efetivação/movimentação.
    - `descricao`: Descrição ou observação.
    - `conciliado`: Boolean que indica se a movimentação foi confirmada no extrato.
    - `external_id`: Identificador da transação no banco/OFX.
- **Relacionamentos:** BelongsTo `banco`, BelongsTo `fatura`, BelongsTo `planoConta`, BelongsTo `centroCusto`, BelongsTo `fornecedor`.
- **Conciliação automática (Onda 6):** `ConciliacaoBancariaService::conciliarCreditosComFaturas()` casa entradas ainda sem `fatura_id` com uma fatura em aberto, por identificador na descrição ou por valor + janela de data em torno do vencimento — só concilia automaticamente quando há exatamente uma candidata.

### `conta_pagars`
- **Representa:** Contas a pagar da instituição (Onda 6) — complementa `transacao_bancarias` (que registra o movimento já realizado) com o lado de obrigação futura/pendente.
- **Campos Principais:** `descricao`, `valor`, `vencimento`, `status` (Enum `App\Enums\StatusContaPagar`: pendente/pago/atrasado/cancelado), `data_pagamento` (nullable), `fornecedor_id`/`plano_conta_id`/`centro_custo_id` (nullable), `transacao_bancaria_id` (nullable, preenchido ao dar baixa), `observacao`.
- **Relacionamentos:** BelongsTo `fornecedor`, `planoConta`, `centroCusto`, `transacaoBancaria`.
- **Atualização automática:** comando `financeiro:atualizar-contas-pagar-atrasadas` (diário, 07:00) marca como `atrasado` as contas `pendente` com vencimento passado.

### `regua_cobrancas`
- **Representa:** Definição das etapas e regras automatizadas de notificação preventiva e cobrança de inadimplência escolar.
- **Campos Principais:**
    - `nome`: Nome amigável da régua (ex: "Lembrete Preventivo (5 dias antes)", "Vencimento Hoje", "Aviso de Atraso (3 dias)").
    - `dias_offset`: Número inteiro representando o intervalo em dias em relação à data de vencimento da fatura (valores negativos para antes do vencimento, 0 para no dia do vencimento, valores positivos para dias de atraso).
    - `tipo_gatilho`: Enum/String (`antes_vencimento`, `no_vencimento`, `apos_vencimento`).
    - `canal`: Enum/String (`todos`, `email`, `portal`, `push`).
    - `assunto`: Assunto do e-mail ou título da notificação com suporte a macros dinâmicas.
    - `mensagem`: Texto do lembrete com suporte a interpolação de tags (`{{RESPONSAVEL_NOME}}`, `{{ALUNO_NOME}}`, `{{NUMERO_FATURA}}`, `{{VALOR}}`, `{{DATA_VENCIMENTO}}`, `{{DIAS_ATRASO}}`, `{{LINK_PAGAMENTO}}`, `{{PIX_COPIA_COLA}}`).
    - `is_ativo`: Boolean que ativa ou pausa os disparos automáticos desta regra.
    - `horario_envio`: Horário de execução diária (padrão: 08:00).
    - `ordem`: Sequência numérica de ordenação.
- **Relacionamentos:** HasMany `logs` (`regua_cobranca_logs`).

### `regua_cobranca_logs`
- **Representa:** Trilha histórica de notificações disparadas pela régua para cada fatura e responsável, prevenindo envios duplicados no mesmo dia.
- **Campos Principais:**
    - `regua_cobranca_id`: FK `regua_cobrancas`.
    - `fatura_id`: FK `faturas`.
    - `pessoa_id`: FK `pessoa` (Responsável notificado).
    - `canal`: Canal utilizado (`email`, `portal`, `push`).
    - `destinatario`: E-mail ou identificador de destino.
    - `mensagem_enviada`: Conteúdo textual processado com os dados reais interpolados.
    - `status_envio`: Situação da entrega (`sucesso`, `falha`).
    - `erro`: Mensagem de erro em caso de falha de conexão/transporte.
    - `data_envio`: Data em que a notificação foi emitida.
- **Relacionamentos:** BelongsTo `reguaCobranca`, BelongsTo `fatura`, BelongsTo `pessoa`.

---

## 6. CRM e Prospecção
### `interessado`
- **Representa:** Leads para novos alunos.
- **Campos Principais:** `pessoa_id`, `status_interessado_id`, `origem_interessado_id`, `campanha_marketing_id` (FK nullable, `nullOnDelete`), `utm_source`/`utm_medium`/`utm_campaign` (string nullable — atribuição de campanha, first touch), `usuario_id` (opcional/nullable), `observacoes`, `data_proximo_contato` (datetime nullable), `valor_estimado` (decimal nullable), `temperatura` (string nullable: quente/morno/frio), `motivo_perda` (string nullable — padronizado pelas opções de `Interessado::MOTIVOS_PERDA`; na perda para concorrente grava "Concorrência: Nome da escola"), `data_primeiro_contato` (datetime nullable — primeiro contato da ESCOLA com a família, preenchido pelo primeiro atendimento registrado; o cadastro pelo formulário público não a preenche), `data_conversao` (datetime nullable), `ultimo_alerta_em` (timestamp nullable — último aviso de atraso/estagnação ao consultor, controla o intervalo entre alertas).
- **Motivos de Perda:** Padronizados na constante `Interessado::MOTIVOS_PERDA` (`Preço`, `Concorrência`, `Distância`, `Mudança`, `Vagas Esgotadas`, `Metodologia`, `Sem retorno`, `Desistência`, `Outro`).
- **Redes sociais:** `redes_sociais` (JSON nullable, cast `array`) — lista de `{rede, url}`. Redes aceitas em `Interessado::REDES_SOCIAIS` (instagram, facebook, linkedin, tiktok, x, youtube, outra). Editável no Repeater "Redes Sociais" da aba Dados do Negócio e preenchido pela importação com IA.
- **Convite de Matrícula Online (Onda 7):** `token_convite` (string nullable, único), `token_convite_expira_em` (datetime nullable), `token_convite_usado_em` (datetime nullable), `dados_pre_matricula` (texto longo nullable, **cifrado** com a `APP_KEY` pelo cast `ArrayCriptografado` — array JSON; `dados_pre_matricula_em` = última atualização do rascunho, base da retenção `crm:expurgar-rascunhos-pre-matricula`; conteúdo: responsáveis, alunos, endereço e aceite LGPD preenchidos pela família; usado para pré-preencher o `EnrollmentWizard` e zerado em `registrarConversao()`). Gerado por `ConviteMatriculaService::gerarConvite()`; `Interessado::conviteValido()` verifica existência + validade + não-uso. Rota pública `/quero-matricular/convite/{token}`.
- **Portal de Pré-Admissão & Documentos:** `token_documentos` (string nullable, token seguro de acesso sem login), `token_documentos_expira_em` (datetime nullable, validade do link: **7 dias**, renovados sempre que a equipe gera/copia/envia o link; token sem validade, vencido ou revogado responde 410 e nunca é revivido — a equipe recebe um token novo; "Gerar novo link" troca o token na hora).
- **Linha do Tempo 360° Omnichannel:** Visão agregada unificada consumida pelo `Customer360TimelineService` e `TimelineRelationManager`.
- **Relacionamentos:** 
    - BelongsTo `pessoa`.
    - BelongsTo `status_interessado`.
    - BelongsTo `origem_interessado`.
    - BelongsTo `campanha_marketing` (`campanha`).
    - BelongsTo `users` (Consultor Responsável).
    - HasMany `dependentes` (InteressadoDependente).
    - HasMany `historico_contato`.
    - HasMany `visita_interessado` (`visitas`).
    - HasMany `interacoes` (histórico sem os registros automáticos).
    - HasOne `ultimoHistorico` (última interação, ignorando registros automáticos).
    - HasOne `proximaVisita` (visita agendada futura mais próxima).
- **Índices (desempenho):** `interessado_proximo_contato_idx` (`data_proximo_contato`), `interessado_status_proximo_idx` (`status_interessado_id`, `data_proximo_contato`), `interessado_status_atualizado_idx` (`status_interessado_id`, `updated_at`), `interessado_usuario_status_idx` (`usuario_id`, `status_interessado_id`), `interessado_lead_score_idx`, `interessado_data_conversao_idx`, `interessado_created_at_idx`.
- **Auditoria:** Trilha de auditoria via `activity_log` com `log_name: crm`, rastreando mudanças em status, temperatura, consultor e valor.
- **Scopes:** `ativos()` (não finalizados), `precisaContato()` (contato atrasado), `doConsultor($id)`, `estagnados($dias)` (sem interação humana/da família nos últimos N dias), `comTokenDocumentosValido($token)`.
- **Métodos de Negócio:** `precisaDeContato()`, `diasNoFunil()`, `totalContatos()` (sem registros automáticos), `diasSemInteracao()`, `estaEstagnado()`.
- **Regras de movimentação:** feitas por `LeadFunilService` (mover etapa ativa, perder com motivo, marcar matriculado, registrar atendimento); a conversão por matrícula vem de `InteressadoMatriculaService::registrarConversao()` (também usada pela matrícula 100% online). Cadastro pelo formulário público: `CaptacaoInteressadoService` (reenvio não sobrescreve o lead).


### `interessado_status_historico`
- **Representa:** Trilha e histórico de transições de etapas do lead no funil de captação e vendas (Lote D1).
- **Propósito:** Rastreia cada movimentação de etapa, registrando de onde o lead veio (`status_anterior_id`), para onde foi (`status_novo_id`), o consultor/usuário que realizou a movimentação (`usuario_id`), a data/hora exata (`data_transicao`), motivo de perda quando aplicável e se o registro é estimado (`estimada` via backfill).
- **Campos Principais:** `interessado_id` (FK `interessado`, cascade), `status_anterior_id` (FK `status_interessado`, nullOnDelete), `status_novo_id` (FK `status_interessado`, cascade), `usuario_id` (FK `users`, nullOnDelete), `motivo_perda` (string nullable), `data_transicao` (datetime), `estimada` (boolean, default false).
- **Índices:** `idx_transicao_lead_data` (`interessado_id`, `data_transicao`), `idx_transicao_status_novo_data` (`status_novo_id`, `data_transicao`), `idx_transicao_de_para` (`status_anterior_id`, `status_novo_id`), `idx_transicao_data` (`data_transicao`).
- **Relacionamentos:** BelongsTo `interessado`, BelongsTo `statusAnterior`, BelongsTo `statusNovo`, BelongsTo `usuario`.
- **Serviço Analítico:** Consumido por `FunilAnaliticoService` para métricas de tempo médio e conversão etapa a etapa.

### `interessado_dependente` (Alunos Vinculados)
- **Representa:** Os potenciais alunos vinculados a um interessado principal.
- **Campos Principais:** `interessado_id`, `nome_crianca`, `serie_id` (nullable), `unidade_id` (FK `unidade`, nullable — unidade de preferência), `turno_preferencia` (string nullable: Manhã, Tarde, Integral, Sem preferência), `vinculo` (Pai, Mãe, Parente, Tutor), `data_nascimento` (cast `date`).
- **Reenvio do formulário:** o dependente é reconhecido pelo nome normalizado (`InteressadoDependente::nomeNormalizado()`) e só recebe campos vazios; nenhum dependente é apagado.

### `historico_contato`
- **Representa:** Registro de cada interação com o interessado (ligação, visita, etc).
- **Campos Principais:** `relato`, `data_contato`, `usuario_id` (FK `users`, nullable — quem registrou), `duracao_minutos` (integer nullable), `resultado` (string nullable: agendou_visita, retornar, sem_interesse, matriculou, outro), `automatico` (boolean, padrão false — registro gerado pelo sistema/IA: e-mail da régua, análise de documento, dossiê da IA; **não conta como interação** para estagnação, Lead Score e resumo ao consultor). Tipos de contato do sistema: `Movimentação no Funil`, `Formulário do Site`, `Portal de Admissão`, `Análise de IA`.
- **Relacionamentos:** BelongsTo `interessado`, BelongsTo `tipo_contato_interessado`, BelongsTo `users` (usuário que registrou).

### `status_interessado`
- **Representa:** Etapas do funil de vendas.
- **Campos Principais:** `nome`, `cor`, `ordem`, `is_final` (boolean — indica status de encerramento), `is_ganho` (boolean — indica conversão/matrícula).
- **Lógica de Negócio (Modelo):** `isPerda(): bool` identifica se o status representa encerramento por perda/descarte (`is_final && !is_ganho` ou nomes de perda), ativando o Stage Gate obrigatório de motivo de perda no Kanban. Atalhos por flag/nome: `StatusInteressado::inicial()` (etapa de entrada), `::ganho()` (matrícula), `::perdido()` (perda padrão).
- **Relacionamentos:** HasMany `interessado`.

### `origem_interessado`
- **Representa:** Fontes de captação de leads (Site, Indicação, Instagram, etc).
- **Campos Principais:** `nome`.

### `tipo_contato_interessado`
- **Representa:** Tipos de contato disponíveis (Telefone, WhatsApp, Presencial, etc).
- **Campos Principais:** `nome`.

### `campanha_marketing`
- **Representa:** Campanhas de captação (Google Ads, Meta Ads, evento etc.) usadas para atribuir e medir leads.
- **Campos Principais:** `nome`, `canal` (chave de `CampanhaMarketing::CANAIS`, nullable), `codigo_utm` (string nullable, **único**, sempre minúsculo — casa com `utm_campaign`), `data_inicio`/`data_fim` (date nullable), `custo` (decimal 12,2, padrão 0), `ativa` (boolean, padrão true), `observacoes`.
- **Relacionamentos:** HasMany `interessado`.

### `visita_interessado`
- **Representa:** Visita de um lead à escola.
- **Campos Principais:** `interessado_id` (FK, `cascadeOnDelete`), `interessado_dependente_id` (FK nullable, `nullOnDelete`), `usuario_id` (FK `users` nullable — consultor), `data_hora`, `status` (Enum `App\Enums\StatusVisitaInteressado`: `agendada`, `realizada`, `faltou`, `cancelada`), `observacoes`, `lembrete_enviado_em` (datetime nullable).
- **Índices:** (`status`, `data_hora`) e `visita_interessado_lead_status_idx` (`interessado_id`, `status`).
- **Relacionamentos:** BelongsTo `interessado`, BelongsTo `interessado_dependente` (`dependente`), BelongsTo `users` (`usuario`).

### `landing_leads`
- **Representa:** Pedidos de demonstração recebidos pela landing page do produto (`/`). São leads **B2B** (escolas), independentes de `interessado`.
- **Campos Principais:** `nome`, `email`, `whatsapp` (nullable), `mensagem` (nullable), `status` (`novo` padrão, `em_contato`, `descartado`).

### `regua_follow_ups`
- **Representa:** Regras automatizadas de follow-up, triggers e workflows de comunicação do CRM escolar.
- **Campos Principais:**
    - `nome`: Título identificador da automação.
    - `gatilho`: Enum `App\Enums\GatilhoReguaFollowUp` (`lead_criado`, `visita_lembrete`, `visita_realizada`, `visita_faltou`, `lead_estagnado`, `contato_atrasado`).
    - `dias_offset`: Número inteiro representando o intervalo em dias em relação ao momento do evento (ex: 1 para 1 dia antes da visita agendada, 1 para 1 dia após a visita realizada, 7 para 7 dias sem interação).
    - `canal`: Enum `App\Enums\CanalReguaFollowUp` (`email`, `notificacao_sistema`).
    - `assunto`: Assunto do e-mail ou título do alerta no painel com interpolação de tags dinâmicas.
    - `mensagem`: Corpo da mensagem com suporte a formatação rica e tags (`{{NOME_RESPONSAVEL}}`, `{{NOME_ALUNO}}`, `{{SERIE_INTERESSE}}`, `{{NOME_CONSULTOR}}`, `{{DATA_VISITA}}`, `{{HORARIO_VISITA}}`, `{{ESCOLA_NOME}}`).
    - `origem_interessado_id`: FK opcional para `origem_interessado` (segmentação de disparo).
    - `status_interessado_id`: FK opcional para `status_interessado` (segmentação de etapa do funil).
    - `is_ativo`: Boolean indicando se a automação está em execução.
    - `horario_envio`: Horário de disparo. A régua roda de hora em hora e a regra sai na primeira execução a partir deste horário (o comando `crm:executar-regua-follow-up` ignora o horário com `--ignorar-horario` ou `--data=`).
    - `ordem`: Ordenação numérica de prioridade.
- **Relacionamentos:** HasMany `logs` (`regua_follow_up_logs`), BelongsTo `origem` (`origem_interessado`), BelongsTo `status` (`status_interessado`).

### `regua_follow_up_logs`
- **Representa:** Trilha histórica de auditoria dos disparos realizados pela régua de follow-up, assegurando idempotência e evitando envios duplicados.
- **Campos Principais:**
    - `regua_follow_up_id`: FK `regua_follow_ups` (deleção em cascata).
    - `interessado_id`: FK `interessado` (deleção em cascata).
    - `visita_interessado_id`: FK opcional `visita_interessado` (null on delete).
    - `canal`: Canal utilizado (`email`, `notificacao_sistema`).
    - `destinatario`: E-mail ou identificador do usuário notificado.
    - `assunto_enviado`: Assunto gerado pós-interpolação.
    - `mensagem_enviada`: Corpo completo do texto enviado.
    - `status_envio`: Situação da entrega (`sucesso`, `falha`).
    - `erro`: Mensagem de erro em caso de falha de transporte ou opt-out de LGPD.
    - `data_envio`: Data de referência do envio.
- **Relacionamentos:** BelongsTo `regua` (`regua_follow_ups`), BelongsTo `interessado`, BelongsTo `visita` (`visita_interessado`).

---

## 7. Documentação
### `tipo_documento`
- **Representa:** Definição dos tipos de documentos exigidos (ex: RG, CPF, Comprovante de Residência).
- **Relacionamentos:** HasMany `documento_inserido`.

### `documento_inserido`
- **Representa:** Os arquivos enviados pelos alunos/responsáveis.
- **Campos Principais:**
    - `status`: Enum (`App\Enums\SituacaoDocumento`). Estados: `pendente`, `em_analise`, `aprovado`, `rejeitado`.
    - `arquivo_path`: Caminho no storage.
    - `hash_arquivo`: Integridade do arquivo.
- **State Machine:** As transições de estado são validadas pelo Enum e controladas no modelo/formulário. Transições permitidas:
    - Pendente -> Em Análise, Aprovado, Rejeitado.
    - Em Análise -> Aprovado, Rejeitado.
    - Aprovado -> Em Análise, Rejeitado.
    - Rejeitado -> Pendente, Em Análise.

---

## 8. Questionários e Avaliação Institucional
### `questionarios`
- **Representa:** O cabeçalho do questionário/formulário.
- **Campos Principais:** `titulo`, `inicio_aplicacao`, `fim_aplicacao`, `is_anonimo`, `is_ativo`, `max_respostas_por_usuario`, `ultimo_envio_aviso`.
- **Campo `max_respostas_por_usuario`:** Limite de respostas por usuário logado. Nulo representa sem limite (infinito).
- **Campo `ultimo_envio_aviso`:** Timestamp do último disparo de e-mails para aviso de questionário pendente aos respondedores.
- **Relacionamentos:** HasMany `blocos`, HasMany `alvos`, HasMany `respostas`.

### `questionario_blocos`
- **Representa:** Agrupamentos de perguntas (seções).
- **Relacionamentos:** BelongsTo `questionarios`, HasMany `perguntas`.

### `questionario_perguntas`
- **Representa:** As perguntas individuais.
- **Campos Principais:** `enunciado`, `tipo` (discursiva, objetiva, multipla_escolha, likert), `opcoes` (JSON), `condicao_exibicao` (JSON).
- **Campo `condicao_exibicao`:** JSON nullable que define a lógica de visibilidade condicional da pergunta. Estrutura: `{"pergunta_id": <id>, "operador": "igual|diferente|contem|nao_contem|preenchido|nao_preenchido", "valor": "<valor>"}`. Quando nulo, a pergunta é sempre exibida.
- **Relacionamentos:** BelongsTo `questionario_blocos`.
- **Lógica de Negócio (Modelo):** `deveSerExibida(array $respostas)`: avalia se a pergunta deve ser exibida com base na condição configurada e nas respostas fornecidas.


### `questionario_responsaveis`
- **Representa:** Gestores (Donos) e visualizadores (Observadores) autorizados do questionário.
- **Campos Principais:**
    - `responsavel_type`: Tipo de responsavel (Role, User).
    - `responsavel_id`: ID da entidade correspondente.
    - `nivel`: Enum (`dono`, `observador`).
- **Propósito:** Controla quem pode editar e visualizar as respostas de forma granular por questionário, além do super_admin.

### `questionario_alvos`
- **Representa:** Definição do público-alvo para o questionário.
- **Campos Principais:** 
    - `alvo_type`: Tipo de alvo (Unidade, Curso, Serie, Turma, Role, User).
    - `alvo_id`: ID da entidade correspondente.
- **Propósito:** Controla a visibilidade e permissão de resposta baseada no perfil ou identificação do usuário.

### `questionario_respostas`
- **Representa:** Submissão de respostas de um questionário.
- **Campos Principais:**
    - `questionario_id`: ID do questionário correspondente.
    - `user_id`: ID do usuário respondente (nullable para anônimos).
    - `perfil_institucional`: Perfil de acesso do usuário no envio.
    - `status`: Estado do envio (pendente, enviado).
- **Relacionamentos:** BelongsTo `questionarios`, BelongsTo `users`, HasMany `perguntaRespostas`, HasMany `feedbacks` (`questionario_resposta_feedbacks`).

### `questionario_resposta_feedbacks`
- **Representa:** Feedbacks, comentários e pareceres avaliativos cadastrados por gestores/avaliadores sobre uma submissão de questionário específica.
- **Campos Principais:**
    - `questionario_resposta_id`: FK para `questionario_respostas` (deleção em cascata).
    - `user_id`: FK para `users` que gerou o feedback (deleção nula).
    - `texto`: Parecer descritivo e anotações.
- **Relacionamentos:** BelongsTo `resposta` (`questionario_respostas`), BelongsTo `user` (`users`).

### `questionario_pergunta_respostas`
- **Representa:** Respostas individuais para cada pergunta de uma submissão de questionário.
- **Campos Principais:** `questionario_resposta_id`, `questionario_pergunta_id`, `resposta_texto`, `resposta_json`.
- **Relacionamentos:** BelongsTo `questionario_respostas`, BelongsTo `questionario_perguntas`.

---

## 9. Módulo de Preceptoria

### `ciclo_preceptorias`
- **Representa:** Divisões temporais ou acadêmicas para realização de preceptorias (ex: Trimestres).
- **Campos Principais:** `uuid`, `nome`, `data_inicio`, `data_fim`, `periodo_letivo_id` (BelongsTo).
- **Relacionamentos:** HasMany `preceptoria`.

### `template_relatorio_preceptoria`
- **Representa:** Modelos reutilizáveis para preencher relatórios de preceptoria.
- **Campos Principais:** `nome` (string), `corpo` (longText HTML).
- **Uso:** Pode ser carregado em qualquer `RelatorioPreceptoria`, substituindo o campo `corpo`.

### `preceptoria`
- **Representa:** Agendamento de uma sessão de preceptoria entre um professor e um aluno.
- **Campos Principais:**
  - `ciclo_preceptoria_id` — FK → `ciclo_preceptorias.id` (NullOnDelete). Obrigatório.
  - `data` (date) — obrigatório.
  - `hora_inicio` (time) — obrigatório.
  - `hora_fim` (time) — nullable.
  - `professor_id` — FK → `pessoa.id` (RestrictOnDelete). Obrigatório.
  - `matricula_id` — FK → `matricula.id` (NullOnDelete). Nullable.
- **Relacionamentos:** 
  - BelongsTo `CicloPreceptoria`.
  - BelongsTo `Pessoa` (Professor).
  - BelongsTo `Matricula`.
  - HasMany `RelatorioPreceptoria`.
- **Lógica de Negócio (Modelo):**
  - `isCompletamenteAgendada()`: Verifica se todos os campos necessários para o agendamento estão preenchidos.
  - `isAgendamentoNoDiaSeguinte()`: Identifica se a sessão ocorre amanhã para alertas visuais.
  - `relembrarAgendamento()`: Dispara notificações de lembrete multicanal (E-mail, Push, Banco) para todos os envolvidos, registrando o evento `notificacao_lembrete_preceptoria` no log de atividades.
- **Notificações:** O sistema envia notificações automáticas (via canais configurados na tabela `notifications`) para os usuários vinculados ao Professor sempre que houver um novo agendamento ou liberação de horário.

### `relatorio_preceptoria`
- **Representa:** Relatório de uma sessão de preceptoria.
- **Campos Principais:**
  - `preceptoria_id` — FK → `preceptoria.id` (CascadeOnDelete).
  - `tipo` (string/enum) — Tipo do relatório (Análise Geral, Plano Pessoal, etc).
  - `corpo` (longText HTML) — editado com TinyEditor.
  - `publico` (boolean) — define se o relatório é visível para o aluno e seus responsáveis.
- **Relacionamentos:** BelongsTo `Preceptoria`.

---

## 10. Saúde Escolar e Ambulatório

### `ficha_medicas`
- **Representa:** Ficha médica e de restrições alimentares do estudante.
- **Campos Principais:** `pessoa_id` (FK `pessoa`, unique), `tipo_sanguineo`, `has_alergia_lactose`, `has_alergia_gluten`, `has_alergia_amendoim`, `outras_alergias_alimentares`, `observacoes_alimentares`, `plano_saude`, `numero_carteira_sus`, `hospital_preferencia`, `observacoes_gerais`.
- **Relacionamentos:** BelongsTo `Pessoa`, HasMany `MedicamentoAluno`, HasMany `ContatoEmergencia`.

### `medicamento_alunos`
- **Representa:** Medicamentos de uso contínuo do estudante.
- **Campos Principais:** `ficha_medica_id`, `nome_medicamento`, `dosagem`, `horario_administracao`, `instrucoes`, `autorizado_responsaveis`, `arquivo_receita_path`.

### `contato_emergencias`
- **Representa:** Contatos de emergência para casos de urgência médica.
- **Campos Principais:** `ficha_medica_id`, `nome`, `parentesco_grau`, `telefone_principal`, `telefone_secundario`, `observacoes`.

### `atendimento_enfermagems`
- **Representa:** Prontuário de atendimentos prestados no ambulatório escolar.
- **Campos Principais:** `pessoa_id`, `atendido_por_user_id` (FK `users`), `data_hora`, `sintomas_queixa`, `procedimento_realizado`, `medicamento_ministrado`, `notificado_responsaveis`, `observacoes`.

---

## 11. Convivência e Ocorrências Escolares

### `tipo_ocorrencias`
- **Representa:** Tipos e classificações de ocorrências disciplinares/operacionais/pedagógicas.
- **Campos Principais:** `nome`, `categoria` (`disciplinar`, `operacional`, `pedagogico`, `saude`), `gravidade` (`positiva`, `leve`, `media`, `grave`), `notificar_responsaveis_padrao`.

### `ocorrencia_escolars`
- **Representa:** Ocorrências registradas na rotina escolar do aluno.
- **Campos Principais:** `matricula_id` (FK `matricula`), `tipo_ocorrencia_id` (FK `tipo_ocorrencias`), `registrado_por_user_id` (FK `users`), `data_hora`, `descricao`, `providencias_tomadas`, `notificar_responsaveis` (boolean), `notificacao_enviada_em` (datetime).
- **Notificação:** Dispara automaticamente `OcorrenciaRegistradaNotification` via e-mail/push/painel para os responsáveis do aluno quando `notificar_responsaveis == true`.

---

## 12. Secretaria Digital e Rematrícula Online

### `template_documentos`
- **Representa:** Modelos e minutas de documentos oficiais escolares com suporte a tags/macros dinâmicas.
- **Campos Principais:**
  - `nome`: Título do template (ex: Declaração de Matrícula Oficial, Declaração de Quitação Anual).
  - `tipo`: Enum `TipoTemplateDocumento` (`declaracao_matricula`, `declaracao_frequencia`, `declaracao_quitacao`, `declaracao_transferencia`, `historico_escolar`, `declaracao_conclusao`, `outro`).
  - `conteudo`: Corpo do documento em formato HTML enriquecido (editado via RichEditor/TinyEditor).
  - `cabecalho_personalizado`, `rodape_personalizado`: Textos ou minutas complementares.
  - `exige_autenticidade`: Boolean indicando se o documento deve receber código hash verificador e QR Code de autenticidade pública.
  - `ativo`: Flag booleana indicando disponibilidade do template.
- **Relacionamentos:** HasMany `SolicitacaoDocumento`.
- **Macro `{{TABELA_HISTORICO}}`** (usada no tipo `historico_escolar`): desde a Onda 5, `DocumentoService::gerarTabelaHistoricoHtml()` busca a situação final real (`situacao_final_disciplina`) de todas as matrículas do aluno, uma tabela por ano/período letivo, já refletindo o resultado do exame final quando houver (`docs/recuperacao_etapa_exame_final.md`). Antes disso, a macro só mostrava as disciplinas da turma atual com situação fixa "Regular".

### `solicitacao_documentos`
- **Representa:** Requerimento e emissão de declarações ou documentos oficiais solicitados por pais/responsáveis ou emitidos pela secretaria.
- **Campos Principais:**
  - `aluno_id`: FK `pessoa.id` (aluno).
  - `solicitante_id`: FK `pessoa.id` (responsável ou interessado solicitante).
  - `template_documento_id`: FK `template_documentos.id`.
  - `matricula_id`: FK `matricula.id` (vínculo com o histórico/ano letivo em questão).
  - `status`: Enum `StatusSolicitacaoDocumento` (`solicitado`, `em_processamento`, `emitido`, `rejeitado`, `cancelado`).
  - `observacoes`: Justificativas ou instruções da secretaria.
  - `arquivo_pdf_path`: Caminho no storage privado do arquivo PDF timbrado gerado.
  - `codigo_autenticidade`: Token hash criptográfico único (ex: `SEC-ABC12345-DEF6`) para conferência pública.
  - `autenticado_em`: Data e hora em que a via oficial e o QR Code foram lavrados.
  - `emitido_por_user_id`: FK `users.id` do operador que lavrou o documento.
- **Relacionamentos:** BelongsTo `Pessoa` (aluno e solicitante), BelongsTo `TemplateDocumento`, BelongsTo `Matricula`, BelongsTo `User` (emitidoPor).

### `periodo_rematriculas`
- **Representa:** Campanhas de rematrícula online para as famílias.
- **Campos Principais:**
  - `nome`: Nome da campanha (ex: Rematrícula 2027).
  - `periodo_letivo_origem_id`, `periodo_letivo_destino_id`: FK `periodo_letivo` (ano/semestre atual e o de destino da renovação).
  - `template_contrato_id`: FK `template_contratos` (nullable) — modelo usado para gerar o novo contrato; sem ele, a rematrícula só cria a nova Matrícula, sem Contrato/faturas.
  - `valor_taxa`: Valor total do novo contrato gerado.
  - `quantidade_parcelas_padrao` (padrão 12), `valor_entrada_padrao` (padrão 0) — Onda 7: usados por `GeracaoFaturasContratoService` para gerar a cobrança automaticamente ao efetivar.
  - `data_inicio`, `data_fim`, `is_ativo`: vigência da campanha no Portal (`PeriodoRematricula::isAberto()`).
  - `mensagem_orientacao`: texto livre exibido à família no Portal.
- **Relacionamentos:** BelongsTo `periodoLetivoOrigem`/`periodoLetivoDestino` (`PeriodoLetivo`), BelongsTo `templateContrato`, HasMany `rematriculas`.

### `rematriculas`
- **Representa:** O processo de rematrícula de um aluno dentro de uma campanha.
- **Campos Principais:**
  - `periodo_rematricula_id`: FK `periodo_rematriculas`.
  - `matricula_origem_id`: FK `matricula` (matrícula do ano corrente sendo renovada).
  - `serie_destino_id` (nullable), `turno_pretendido_id` (nullable): preferências informadas pela família no Portal. `turma_destino_id` (nullable até efetivar): turma escolhida **pela secretaria** ao efetivar (obrigatória em `RematriculaService::efetivar()`; não há mais busca automática nem matrícula sem turma). Ao efetivar, os três são gravados a partir da turma escolhida.
  - Índice único `(periodo_rematricula_id, matricula_origem_id)` (`rematriculas_campanha_matricula_origem_unique`, criado se não houver duplicatas).
  - `solicitante_user_id`: FK `users` (quem confirmou os dados pelo Portal).
  - `status`: Enum `StatusRematricula`: `iniciada` → `dados_confirmados` → `aguardando_assinatura` (Onda 7: quando o contrato foi enviado ao Assinafy) → `confirmada` (Onda 7: só quando o contrato é efetivamente assinado — ver webhook do Assinafy) — ou `cancelada`. `cancelada` libera a vaga e cancela as faturas em aberto (`RematriculaService::cancelar()`/`aplicarCancelamento()`, também pelo evento `updated` do model); uma rematrícula cancelada não é reativada por assinatura tardia nem pode ser efetivada.
  - `contrato_id`: FK `contrato` (nullable, o novo contrato gerado).
  - `nova_matricula_id`: FK `matricula` (nullable, a matrícula criada no período de destino). A matrícula nasce `pendente` quando a campanha tem contrato (vira `ativa` ao assinar, via `Rematricula::confirmarPelaAssinatura()`) e `ativa` quando não tem. Rematrícula efetivada e não cancelada **não pode ser excluída** (evento `deleting`).
  - `observacoes`, `data_confirmacao`.
- **Relacionamentos:** BelongsTo `periodoRematricula`, `matriculaOrigem`/`novaMatricula` (`Matricula`), `turmaDestino` (`Turma`), `serieDestino` (`Serie`), `turnoPretendido` (`Turno`), `solicitante` (`User`), `contrato` (`Contrato`).
- **Fluxo completo (Onda 7):** `RematriculaService::efetivar()` cria a nova Matrícula e o Contrato (sem `data_aceite`, que só é gravada quando o contrato é assinado), gera as faturas via `GeracaoFaturasContratoService` (vencimentos a partir do dia da rematrícula, informado como data-base) e envia o contrato para assinatura via `AssinafyService::enviarContrato()`. A efetivação roda em transação com lock da linha e é idempotente: se `nova_matricula_id` já existe, devolve a matrícula criada sem gerar nada de novo. `AssinafyService::handleWebhook()` identifica o contrato pela nova relação `Contrato::rematricula()` e, ao receber a confirmação de assinatura, marca a `Rematricula` como `confirmada`.

---

## 13. Comunicação Escolar, Eventos (RSVP) e Central de Atendimento

### `eventos_escolares`
- **Representa:** Atividades, reuniões, celebrações e passeios pedagógicos organizados pela instituição.
- **Campos Principais:**
  - `uuid`: Identificador único UUID para compartilhamento/segurança.
  - `unidade_id`: FK `unidade.id` (unidade escolar responsável).
  - `titulo`: Nome do evento (ex: Reunião Geral de Pais, Visita Cultural ao Museu).
  - `tipo`: Enum `TipoEventoEscolar` (`reuniao_pais`, `passeio_cultural`, `festa_comemorativa`, `palestra`, `formatura`, `outro`).
  - `descricao`: Programação e detalhes em texto HTML.
  - `local`: Espaço físico ou endereço de destino.
  - `data_inicio`, `data_fim`: Horários de início e término.
  - `limite_vagas`: Lotação máxima permitida (null = livre).
  - `prazo_confirmacao`: Data limite para as famílias manifestarem RSVP.
  - `exige_autorizacao`: Booleano indicando necessidade de assinatura formal dos pais para saídas escolares.
  - `termo_autorizacao`: Minuta legal da autorização de saída.
  - `valor_por_pessoa`: Custo de participação (transporte/ingresso) para fins informativos.
  - `publico_alvo`: String (`todos` ou `turmas_especificas`).
  - `ativo`: Booleano de visibilidade no calendário.
- **Relacionamentos:** BelongsTo `Unidade`, BelongsToMany `Turma` (via `evento_escolar_turmas`), HasMany `EventoConfirmacao`.

### `evento_escolar_turmas`
- **Representa:** Tabela pivô de vínculo entre eventos direcionados e turmas participantes.
- **Campos:** `evento_escolar_id`, `turma_id`.

### `evento_confirmacoes`
- **Representa:** A confirmação de presença (RSVP), contagem de acompanhantes e autorização legal dos responsáveis.
- **Campos Principais:**
  - `evento_escolar_id`: FK `eventos_escolares.id`.
  - `matricula_id`: FK `matricula.id` (estudante vinculado).
  - `responsavel_id`: FK `pessoa.id` (responsável que respondeu).
  - `status`: Enum `StatusRsvp` (`pendente`, `confirmado`, `recusado`).
  - `quantidade_acompanhantes`: Número de familiares extras declarados.
  - `autorizado`: Booleano com aceite formal do termo de saída.
  - `data_resposta`: Data e hora da resposta.
  - `ip_resposta`: Endereço IP do responsável para validade jurídica da autorização.
  - `observacoes`: Mensagens ou orientações da família para a coordenação.
- **Relacionamentos:** BelongsTo `EventoEscolar`, BelongsTo `Matricula`, BelongsTo `Pessoa`.

### `atendimento_setores`
- **Representa:** Áreas e departamentos da escola disponíveis para abertura de chamados no Portal da Família.
- **Campos Principais:** `nome` (Secretaria, Financeiro, Coordenação, Ambulatório, Ouvidoria), `descricao`, `email_notificacao`, `ativo`, `ordem`.
- **Relacionamentos:** HasMany `AtendimentoChamado`.

### `atendimento_chamados`
- **Representa:** Protocolos e tickets de atendimento abertos pelas famílias ou registrados pela recepção.
- **Campos Principais:**
  - `protocolo`: Código único formatado (ex: `ATD-2026-ABC12`).
  - `setor_id`: FK `atendimento_setores.id`.
  - `matricula_id`: FK `matricula.id` (opcional, para vincular a dúvida a um aluno).
  - `solicitante_id`: FK `pessoa.id` (responsável/solicitante).
  - `responsavel_atendimento_id`: FK `users.id` (atendente escolar atribuído).
  - `assunto`: Resumo da solicitação.
  - `prioridade`: Enum `PrioridadeChamado` (`baixa`, `normal`, `alta`, `urgente`).
  - `status`: Enum `StatusChamado` (`aberto`, `em_andamento`, `aguardando_solicitante`, `resolvido`, `fechado`).
  - `avaliacao_nota`: Nota de 1 a 5 estrelas concedida pela família na resolução.
  - `avaliacao_comentario`: Opinião descritiva sobre o atendimento.
  - `fechado_em`: Data e hora de encerramento do chamado.
- **Relacionamentos:** BelongsTo `AtendimentoSetor`, BelongsTo `Matricula`, BelongsTo `Pessoa`, BelongsTo `User`, HasMany `AtendimentoMensagem`.

### `atendimento_mensagens`
- **Representa:** Linha do tempo e mensagens trocadas dentro de um chamado de atendimento.
- **Campos Principais:**
  - `chamado_id`: FK `atendimento_chamados.id`.
  - `user_id`: FK `users.id` (quando a mensagem é da equipe escolar).
  - `pessoa_id`: FK `pessoa.id` (quando a mensagem é da família).
  - `mensagem`: Texto da mensagem.
  - `anexo_path`: Caminho no storage de arquivo/comprovante anexado.
  - `lida_em`: Data e hora de visualização.
- **Relacionamentos:** BelongsTo `AtendimentoChamado`, BelongsTo `User`, BelongsTo `Pessoa`.

---

## 14. Régua de Cobrança e Gestão de Inadimplência

### `regua_cobrancas`
- **Representa:** Parâmetros e regras de disparo automatizado de lembretes e avisos de cobrança (preventivos, no dia e de atraso).
- **Campos Principais:**
  - `nome`: Identificação amigável da régua (ex: Lembrete Preventivo 5 dias, Vencimento Hoje, Notificação Crítica).
  - `dias_offset`: Dias relativos ao vencimento (Negativo = antes do vencimento, 0 = no dia, Positivo = após o vencimento).
  - `tipo_gatilho`: Enum/String (`antes_vencimento`, `no_vencimento`, `apos_vencimento`).
  - `canal`: Meio de notificação (`email`, `portal`, `push`, `todos`).
  - `assunto`: Título/assunto da mensagem com suporte a macros dinâmicas.
  - `mensagem`: Conteúdo do lembrete com macros (`{{ALUNO_NOME}}`, `{{RESPONSAVEL_NOME}}`, `{{NUMERO_FATURA}}`, `{{VALOR}}`, `{{DATA_VENCIMENTO}}`, `{{DIAS_ATRASO}}`).
  - `is_ativo`: Booleano para ligar/desligar a régua.
  - `horario_envio`: Horário diário preferencial de envio.
  - `ordem`: Ordem de exibição e processamento.
- **Relacionamentos:** HasMany `ReguaCobrancaLog`.

### `regua_cobranca_logs`
- **Representa:** Histórico de auditoria e registro de disparos de lembretes vinculados a faturas específicas.
- **Campos Principais:**
  - `regua_cobranca_id`: FK `regua_cobrancas.id`.
  - `fatura_id`: FK `faturas.id`.
  - `pessoa_id`: FK `pessoa.id` (responsável notificado).
  - `canal`: Canal efetivo do envio (`portal`, `email`, `push`).
  - `destinatario`: E-mail ou identificador de destino.
  - `mensagem_enviada`: Cópia fiel do texto renderizado com as variáveis resolvidas.
  - `status_envio`: String (`sucesso`, `falha`).
  - `erro`: Mensagem de erro caso o envio falhe.
  - `data_envio`: Data (Y-m-d) em que o disparo foi concretizado (evita duplicações no mesmo dia).
- **Relacionamentos:** BelongsTo `ReguaCobranca`, BelongsTo `Fatura`, BelongsTo `Pessoa`.

---

## 15. Comunicação: Canais e Disparo em Massa

> A régua de cobrança (seção 14) já envia por portal/e-mail/push com sua própria lógica.
> O que segue é uma camada mais genérica, para alertas individuais (ex: falta do aluno) e
> disparos em massa fora do contexto financeiro (CRM).

### `comunicacao_em_massa`
- **Representa:** Um envio de e-mail para um grupo de pessoas (leads do CRM ou responsáveis de turma), processado em fila.
- **Campos Principais:**
  - `nome`: Identificação interna (não aparece para quem recebe).
  - `tipo_publico`: Enum `TipoPublicoComunicacao` (`interessados`, `responsaveis_turma`) — define como `filtros` é interpretado.
  - `filtros`: JSON com os critérios de segmentação:
    - `interessados`: `interessado_ids` (seleção explícita, tem prioridade) ou `status_interessado_ids`/`origem_interessado_ids` (pelo menos um filtro é exigido — sem nenhum filtro nem seleção, não retorna ninguém).
    - `responsaveis_turma`: `turma_ids` (responsáveis dos alunos com matrícula ativa nessas turmas).
  - `canal`: Hoje só `'email'` (`CanalMensagemManager`); campo já preparado para outros canais.
  - `assunto`, `corpo`: Conteúdo do e-mail; aceitam a variável `[Nome]`.
  - `status`: Enum `StatusComunicacaoEmMassa` (`rascunho`, `enviando`, `concluida`, `falhou`).
  - `total_destinatarios`, `total_enviados`, `total_falhas`: Contadores gravados ao final do envio.
  - `enviado_por_user_id`: FK `users.id`. `enviado_em`: datetime.
- **Relacionamentos:** BelongsTo `User` (`enviadoPor`).
- **Serviço:** `ComunicacaoEmMassaService::destinatarios()` resolve `filtros` em uma lista de `Pessoa`, já excluindo quem não tem e-mail ou tem `aceita_comunicacao = false`.
- **Job:** `EnviarComunicacaoEmMassaJob` (fila) envia via `EmailCanal`, atualiza os contadores/status e notifica quem criou o envio ao concluir.
- **Origem do envio:** ação em lote **Enviar Comunicação por E-mail** na tabela de Interessados (cria com `filtros.interessado_ids` = seleção) ou a tela **CRM / Comercial → Comunicação em Massa** (segmentação por status/origem/turma).

### Canais de mensagem (`App\Contracts\CanalMensagem`)
- **Não é tabela**, é uma abstração de código: `EmailCanal` (envia e registra em `email_logs`) e `FcmCanal` (push para os usuários vinculados à Pessoa), resolvidos por `CanalMensagemManager`.

---

## 16. Central de Ajuda — Vídeos Tutoriais

### `lead_score_configuracao`
- **Representa:** Personalização dos pesos/faixas do Lead Score feita na tela "Pesos do Lead Score" (sobrescreve `config/lead_score.php`).
- **Campos Principais:** `valores` (JSON com as chaves de `config/lead_score.php` alteradas), `atualizado_por` (FK `users`, nullable).
- **Relacionamentos:** BelongsTo `users` (`atualizadoPor`). Vale a linha mais recente; sem linhas, valem os padrões do arquivo.

### `video_tutorial`
- **Representa:** Vídeos curtos de treinamento exibidos na Central de Ajuda (`/admin/video-tutorials`) e, opcionalmente, dentro do modal de "Ajuda" de uma tela específica.
- **Campos Principais:**
  - `titulo`: Nome do vídeo.
  - `descricao`: Texto curto explicando o que o vídeo ensina (nullable).
  - `categoria`: Texto livre para agrupamento visual (ex: CRM, Secretaria, Acadêmico) — nullable.
  - `chave_pagina`: Chave nullable/indexada que liga o vídeo ao modal de "Ajuda" de uma tela específica. Ver `App\Models\VideoTutorial::CHAVES_PAGINA` para a lista de chaves válidas (uma mesma chave pode ser reaproveitada por mais de uma página do mesmo fluxo).
  - `arquivo`: Path no disco `public` (`storage/app/public/video-tutoriais/...`) do arquivo de vídeo enviado — nullable, tem prioridade sobre `url_externo` quando ambos estão preenchidos.
  - `url_externo`: Link do YouTube/Vimeo, usado quando não há `arquivo` — nullable.
  - `duracao_segundos`: Duração informada manualmente (não há detecção automática) — nullable.
  - `ordem`: Inteiro para ordenação manual na listagem.
  - `ativo`: Booleano; quando `false`, some tanto da Central de Ajuda quanto do modal de Ajuda da tela relacionada.
- **Sem relacionamentos com outras tabelas** — é uma entidade independente, apenas linkada a telas do Filament via `chave_pagina` (string), não por FK.
- **Acessores do Model:** `arquivo_url` (URL pública via `Storage::disk('public')`), `url_embed` (converte link do YouTube/Vimeo para URL de embed em iframe), `url_assistir` (`arquivo_url` ?? `url_externo`), `duracao_formatada` (ex: "1min 27s").
- **Seeder:** `VideoTutorialSeeder` (não roda no `DatabaseSeeder` principal — é chamado sob demanda com `php artisan db:seed --class=VideoTutorialSeeder`, copiando os arquivos de vídeo para o disco `public` e criando os registros via `updateOrCreate` por `chave_pagina`).

---

## 17. Histórico Escolar Oficial Multi-Ano

### `historico_escolars`
- **Representa:** O cabeçalho e os metadados do Histórico Escolar Oficial de um estudante para uma determinada etapa/curso da Educação Básica (Ensino Fundamental ou Ensino Médio).
- **Campos Principais:**
  - `pessoa_id`: FK `pessoa` (Aluno).
  - `curso_id`: FK `curso` (nullable — ex: Ensino Fundamental, Ensino Médio).
  - `unidade_id`: FK `unidade` (Unidade escolar expedidora).
  - `codigo_autenticidade`: Token hash criptográfico único (ex: `HIST-2026-ABCD-1234`) para conferência pública e QR Code.
  - `situacao`: String/Enum (`em_curso`, `concluido`, `transferido`).
  - `data_conclusao`: Data de término do ciclo/formatura (nullable).
  - `data_emissao`: Data em que o documento oficial foi expedido.
  - `titulo_certificacao`: Título do termo de conclusão (ex: "Certificado de Conclusão do Ensino Fundamental").
  - `texto_certificacao`: Texto legal formal atestando a conclusão com base na LDB 9.394/96.
  - `observacoes`: Amparo legal, observações de aproveitamento, convalidações ou transferências.
  - `emitido_por_user_id`: FK `users` (operador que lavrou o documento).
- **Relacionamentos:** BelongsTo `Pessoa`, BelongsTo `Curso`, BelongsTo `Unidade`, BelongsTo `User` (`emitidoPor`), HasMany `anos` (`HistoricoEscolarAno`).

### `historico_escolar_anos`
- **Representa:** Cada coluna/série na matriz curricular do histórico escolar (cada ano letivo cursado pelo estudante, no Torre360 ou em outras instituições de ensino anteriores).
- **Campos Principais:**
  - `historico_escolar_id`: FK `historico_escolars`.
  - `matricula_id`: FK `matricula` (nullable — vinculada à matrícula interna caso cursada no Torre360).
  - `ano_letivo`: Ano civil do período letivo (ex: 2023, 2024, 2025).
  - `serie_id`: FK `serie` (nullable).
  - `serie_nome`: Nome amigável da série/ano (ex: "6º Ano", "1ª Série EM").
  - `ordem`: Sequência numérica para ordenação da esquerda para a direita na matriz tabular.
  - `tipo`: String (`interno` para cursado no Torre360, `externo` para cursado em outra escola antes da transferência).
  - `escola_nome`: Nome do estabelecimento de ensino onde o ano foi cursado.
  - `escola_cidade`, `escola_uf`: Município e Estado da instituição.
  - `dias_letivos`: Total de dias letivos (padrão: 200).
  - `carga_horaria_total`: Carga horária total cumprida no ano letivo (em horas).
  - `frequencia_percentual`: Taxa percentual de frequência global no ano (ex: 98.50).
  - `situacao_ano`: Resultado final do ano letivo (`Aprovado`, `Reprovado`, `Classificado`, `Transferido`, `Cursando`).
  - `observacoes`: Anotações ou ressalvas específicas do ano escolar.
- **Relacionamentos:** BelongsTo `HistoricoEscolar`, BelongsTo `Matricula`, BelongsTo `Serie`, HasMany `disciplinas` (`HistoricoEscolarDisciplina`).

### `historico_escolar_disciplinas`
- **Representa:** As notas e cargas horárias dos componentes curriculares obtidas pelo estudante em cada ano/série do histórico escolar.
- **Campos Principais:**
  - `historico_escolar_ano_id`: FK `historico_escolar_anos`.
  - `disciplina_id`: FK `disciplina` (nullable).
  - `disciplina_nome`: Nome do componente curricular (ex: "Língua Portuguesa", "Matemática", "História").
  - `area_conhecimento`: Área do conhecimento conforme a BNCC (ex: "Linguagens", "Matemática", "Ciências da Natureza", "Ciências Humanas", "Parte Diversificada").
  - `carga_horaria`: Carga horária anual da disciplina (em horas).
  - `nota_final`: Média final obtida no ano (decimal:2).
  - `conceito`: Conceito avaliativo alternativo quando a avaliação não for puramente numérica.
  - `situacao`: Situação da disciplina (`Aprovado`, `Reprovado`, `Dispensado`).
  - `ordem`: Sequência de ordenação vertical.
- **Relacionamentos:** BelongsTo `ano` (`HistoricoEscolarAno`), BelongsTo `disciplina` (`Disciplina`).

---

## 18. Secretaria Digital e Auto-Atendimento de Declarações Oficiais

### `template_documentos`
- **Representa:** Modelos e templates oficiais de declarações, certidões e atestados emitidos pela escola, com suporte a variáveis dinâmicas e macros de preenchimento.
- **Campos Principais:**
  - `nome`: Nome do modelo (ex: "Declaração de Matrícula Regular", "Declaração de Frequência Escolar", "Declaração para Passe Escolar e Transporte", "Declaração de Quitação de Débitos").
  - `tipo`: Enum `TipoTemplateDocumento` (`declaracao_matricula`, `declaracao_frequencia`, `declaracao_quitacao`, `declaracao_conclusao`, `declaracao_transferencia`, `declaracao_transporte`, `declaracao_horario`, `historico_escolar`, `personalizado`).
  - `descricao`: Finalidade descritiva apresentada nos cartões do Portal.
  - `conteudo`: HTML com macros dinâmicas (`{{ALUNO_NOME}}`, `{{ALUNO_CPF}}`, `{{HORARIO_AULAS}}`, `{{PROTOCOLO}}`, etc.).
  - `validade_dias`: Prazo padrão em dias de validade do documento emitido (ex: 30, 60, 90).
  - `is_ativo`: Booleano para disponibilizar no auto-atendimento.
- **Relacionamentos:** HasMany `SolicitacaoDocumento`.

### `solicitacao_documentos`
- **Representa:** Registros de emissões e certidões oficiais geradas com código de verificação rastreável e QR Code.
- **Campos Principais:**
  - `protocolo`: Número identificador único formatado (ex: `DOC-2026-000001`).
  - `codigo_verificacao`: Token hash criptográfico de validação pública anti-raspagem LGPD (ex: `TR36-XXXX-XXXX-XXXX`).
  - `matricula_id`: FK `matricula.id` (estudante titular do documento).
  - `template_documento_id`: FK `template_documentos.id`.
  - `solicitado_por_user_id`: FK `users.id` (usuário que solicitou via Portal ou equipe escolar).
  - `atendido_por_user_id`: FK `users.id` (em caso de atendimento ou despacho manual).
  - `status`: Enum `StatusSolicitacaoDocumento` (`solicitado`, `em_processamento`, `disponivel`, `rejeitado`).
  - `arquivo_path`: Caminho no storage privado do PDF timbrado gerado (`documentos_emitidos/DOC-2026-XXXXXX.pdf`).
  - `observacao_solicitante`: Texto de finalidade ou observação informado pela família.
  - `justificativa_recusa`: Motivo caso o pedido seja rejeitado.
  - `data_solicitacao`: Data/hora do requerimento.
  - `data_emissao`: Data/hora em que o PDF oficial com QR Code foi processado e assinado.
  - `data_validade`: Data limite de validade jurídica do documento.
- **Relacionamentos:** BelongsTo `Matricula`, BelongsTo `TemplateDocumento`, BelongsTo `User` (`solicitadoPor`), BelongsTo `User` (`atendidoPor`).

---

## 19. CRM de Admissões, Tour Escolar e Pesquisa NPS

### `interessados`
- **Representa:** Leads e famílias interessadas em vagas na instituição de ensino.
- **Campos Principais:** `pessoa_id`, `usuario_id` (consultor), `origem_interessado_id`, `status_interessado_id`, `data_primeiro_contato`, `data_proximo_contato`, `temperatura`, `score_engajamento`, `valor_estimado`, `token_documentos` (String 48 chars - Token aleatório para acesso seguro da família ao Portal de Pré-Admissão e checklist de documentos), `motivo_perda`, `concorrente_id` (FK `crm_concorrentes.id` - nullable), `fator_decisivo_concorrente` (String - nullable), `detalhes_concorrencia` (Text - nullable).
- **Relacionamentos:** BelongsTo `Pessoa`, BelongsTo `User` (consultor), BelongsTo `Concorrente` (`crm_concorrentes`), HasMany `dependentes`, HasMany `visitas`, HasMany `historicoContatos`, HasOne `indicacao` (`IndicacaoInteressado`), HasMany `documentosInseridos` (`DocumentoInserido`).

### `crm_concorrentes` (Battlecards de Concorrentes & Inteligência de Mercado)
- **Representa:** Escolas e colégios concorrentes mapeados na região para subsidiar a equipe comercial com diferenciais de valor e registrar perdas competitivas.
- **Campos Principais:**
  - `nome`: Nome oficial do colégio concorrente (indexado).
  - `sigla`: Sigla usual ou apelido da instituição (ex: CDB, IEV).
  - `cidade_id`: FK `cidade.id` (nullable).
  - `bairro`: Bairro ou região geográfica de atuação.
  - `faixa_preco`: Enum/String (`mais_barato`, `equivalente`, `mais_caro`).
  - `mensalidade_estimada`: Decimal (10,2) - Valor estimado da mensalidade cobrada por eles.
  - `proposta_pedagogica`: String - Linha pedagógica (ex: Tradicional, Construtivista, Bilíngue).
  - `pontos_fortes`: JSON (Array) - Argumentos e atributos que atraem famílias para eles.
  - `pontos_fracos`: JSON (Array) - Vulnerabilidades e pontos onde deixam a desejar (turmas cheias, frieza, rotatividade).
  - `diferenciais_nossos`: Text - Argumentos comprovados por que a nossa escola é superior a eles.
  - `estrategia_abordagem`: Text - Dicas de ouro e postura tática para o consultor negociar eticamente sem criticar a outra escola.
  - `observacoes`: Text - Anotações gerais de inteligência de mercado.
  - `is_ativo`: Boolean (default true) - Se a escola está ativa no radar competitivo.
- **Relacionamentos:** BelongsTo `Cidade`, HasMany `interessadosPerdidos` (`Interessado`).

### `crm_objecoes` (Matriz de Objeções Comerciais & Scripts de Contorno)
- **Representa:** Repositório oficial de contorno de dúvidas e resistências das famílias para treinamento e consulta rápida da equipe de admissões.
- **Campos Principais:**
  - `titulo`: Identificação clara da objeção (ex: "Mensalidade acima do orçamento", "Distância de casa", "Dúvidas sobre o método").
  - `categoria`: String (`preco`, `distancia`, `pedagogico`, `estrutura`, `vagas`, `outro`).
  - `descricao`: Text (nullable) - Como a família expressa a dúvida no atendimento.
  - `resposta_sugerida`: Text - Roteiro verbal pronto, empático e de alto valor educacional para o consultor falar.
  - `pergunta_virada`: Text (nullable) - Pergunta aberta para devolver a reflexão aos pais e avançar o fechamento.
  - `dicas_postura`: Text (nullable) - Raciocínio psicológico e postura recomendada para o consultor não ficar na defensiva.
  - `ordem`: Integer (default 0) - Prioridade de exibição na matriz.
  - `is_ativo`: Boolean (default true) - Se a objeção deve aparecer nos battlecards e consultas.

### `indicacao_interessados` (Programa "Família Indica Família" / MGM)
- **Representa:** Registro de indicações de novos alunos feitas por responsáveis e famílias de alunos da escola.
- **Campos Principais:**
  - `indicador_pessoa_id`: FK `pessoa.id` (família que fez a indicação).
  - `interessado_id`: FK `interessados.id` (lead/candidato indicado).
  - `codigo_indicacao`: String (ex: `SILV-A7K2`) - Código promocional da família utilizado na indicação.
  - `status`: String/Enum (`pendente`, `matriculado`, `recompensado`, `cancelado`).
  - `recompensa_tipo`: String (ex: `desconto_mensalidade`, `brinde`, `bolsa`).
  - `recompensa_detalhe`: String/Text - Descrição da bonificação acordada.
  - `valor_recompensa`: Decimal (8,2) - Valor financeiro do benefício.
  - `data_conversao`: Datetime - Preenchido automaticamente quando o lead é matriculado.
  - `data_recompensa`: Datetime - Preenchido quando o benefício é liberado à família.
  - `recompensado_por_usuario_id`: FK `users.id` - Colaborador que aprovou/concedeu a recompensa.
  - `observacoes`: Text - Anotações internas da equipe de captação e financeiro.
- **Relacionamentos:** BelongsTo `Pessoa` (`indicador` / `quemIndicou`), BelongsTo `Interessado`, BelongsTo `User` (`recompensadoPor`).

### `documento_inserido` (Extensão para Pré-Admissão com Validador IA)
- **Representa:** Documentos enviados por alunos, famílias ou candidatos para comprovação cadastral.
- **Campos Estendidos:**
  - `matricula_id`: FK `matricula.id` (tornado nullable para permitir envio prévio na fase de pré-admissão antes da formalização da matrícula).
  - `interessado_id`: FK `interessados.id` (vincula o documento ao lead candidato).
  - `interessado_dependente_id`: FK `interessado_dependente.id` (nullable - vincula a um dependente específico caso o lead tenha mais de um filho).
  - `tipo_documento_id`: FK `tipo_documento.id`.
  - `status`: Enum `SituacaoDocumento` (`EM_ANALISE`, `VERIFICADO`, `REJEITADO`).
  - `arquivo_path`, `nome_arquivo_original`, `hash_arquivo`, `observacoes` (motivo de rejeição).
  - `dados_ia`: JSON (nullable) - Parecer estruturado pericial do Gemini Vision com score de confiança (0-100), aferição de nitidez/enquadramento, verificação de correspondência com o tipo solicitado, extração segura de campos (nome do titular, CPF, RG, data de nascimento, filiação, endereço) e orientações em caso de divergência.
  - `analisado_ia_em`: Timestamp (nullable) - Registro cronológico da execução assíncrona do job de validação por IA.
- **Validação Inteligente e OCR Efêmero:** Os uploads despacham o job assíncrono `ValidarDocumentoComIaJob`, realizando análise de imagem em segundo plano sem travar a interface da família nem reter imagens para treinamento (em conformidade com o Art. 7º, V e Art. 14 da LGPD). Os dados extraídos podem ser sincronizados com 1 clique para a ficha cadastral do aluno/responsável no painel administrativo.
- **Lógica de Transição e Herança:** Quando o lead é matriculado, os registros de `documento_inserido` são automaticamente associados à nova `matricula_id`, mantendo integridade com a auditoria e evitando qualquer reenvio documental pela família.

### `visita_interessado`
- **Representa:** Agendamentos de visitas presenciais (tour escolar) realizadas pelas famílias candidatas.
- **Campos Principais:** `interessado_id`, `interessado_dependente_id`, `usuario_id` (consultor), `data_hora`, `status` (Enum `StatusVisitaInteressado`: `Agendada`, `Realizada`, `Cancelada`, `Faltou`), `observacoes`, `lembrete_enviado_em`.
- **Relacionamentos:** BelongsTo `Interessado`, BelongsTo `InteressadoDependente`, BelongsTo `User`, HasOne `pesquisa` (`PesquisaSatisfacaoVisita`).

### `visita_pesquisa_satisfacao`
- **Representa:** Pesquisa de satisfação e Net Promoter Score (NPS) pós-tour escolar respondida pelas famílias.
- **Campos Principais:**
  - `visita_interessado_id`: FK única `visita_interessado.id`.
  - `interessado_id`: FK `interessados.id`.
  - `token`: String (40 chars) - Token aleatório criptográfico e seguro para acesso público da família sem autenticação.
  - `nota_nps`: Integer (0 a 10, nullable até ser respondido) - Escala NPS.
  - `nota_atendimento`: Integer (1 a 5, nullable) - Pilar de acolhimento e recepção.
  - `nota_infraestrutura`: Integer (1 a 5, nullable) - Pilar de estrutura física e instalações.
  - `nota_proposta_pedagogica`: Integer (1 a 5, nullable) - Pilar de clareza da proposta pedagógica e metodologia.
  - `comentario`: Text (nullable) - Observações, elogios e feedbacks abertos da família.
  - `respondido_em`: Datetime (nullable) - Timestamp do envio da avaliação pela família.
  - `ip`: String (45 chars, nullable) - Endereço IP de origem para auditoria.
- **Relacionamentos:** BelongsTo `VisitaInteressado`, BelongsTo `Interessado`.

### `regua_follow_ups` e `regua_follow_up_logs`
- **Representa:** Regras automatizadas e esteira de follow-up do funil comercial de captação (WhatsApp e E-mail), com interpolação de tags dinâmicas (`{{LINK_PESQUISA}}`, `{{PRIMEIRO_NOME}}`, `{{DATA_VISITA}}`, etc.).

### `mensagem_whatsapp_template` (Modelos de WhatsApp)
- **Representa:** Modelos oficiais de mensagem do CRM (disparo rápido, pesquisa pós-visita e base de referência para o Copiloto WhatsApp IA).
- **Campos Principais:** `nome`, `conteudo` (texto com variáveis como `[Nome do Responsável]`), `instrucoes_ia` (Text, nullable — instruções específicas para a IA quando o modelo é usado como base do Copiloto; somam-se às regras gerais de `copiloto_ia_configuracoes`; máx. 1500 caracteres, sem efeito no envio direto do modelo), `ativo`.

### `copiloto_ia_configuracoes`
- **Representa:** Personalização do comportamento do Copiloto WhatsApp IA feita na tela "Comportamento do Copiloto IA" (sobrescreve `config/copiloto_ia.php`).
- **Campos Principais:** `valores` (JSON com as chaves de `config/copiloto_ia.php` alteradas: `persona`, `diretrizes`, `mencionar`, `evitar`, `objetivos`, `tons`, `gemini`), `atualizado_por` (FK `users`, nullable).
- **Relacionamentos:** BelongsTo `users` (`atualizadoPor`). Vale a linha mais recente (`CopilotoIaConfiguracao::valores()`, com cache); sem linhas, valem os padrões do arquivo. `objetivos`, `tons` e `gemini` mesclam por chave; `diretrizes` é substituída por inteiro.

### `proposta_comercials` (Simulador de Propostas Comerciais & Revenue Management com Alçadas de Desconto)
- **Representa:** Simulações e propostas comerciais formais emitidas pela equipe de admissões para famílias interessadas, com governança de alçadas de desconto por hierarquia corporativa.
- **Campos Principais:**
  - `codigo`: String única (ex: `PROP-2026-ABC123`) para identificação e citação segura.
  - `interessado_id`: FK `interessados.id` (lead/família solicitante).
  - `interessado_dependente_id`: FK `interessado_dependente.id` (nullable - aluno/candidato quando especificado).
  - `serie_id`: FK `serie.id` (série/ano de ingresso pretendido).
  - `turma_id`: FK `turma.id` (nullable - turma específica simulada).
  - `periodo_letivo_id`: FK `periodo_letivo.id` (ano/semestre letivo da proposta).
  - `turno_id`: FK `turno.id` (nullable - turno pretendido).
  - `consultor_id`: FK `users.id` (consultor comercial que elaborou a proposta).
  - `aprovado_por_id`: FK `users.id` (nullable - gestor que autorizou a proposta caso tenha entrado em alçada).
  - `valor_mensalidade_tabela`: Decimal (10,2) - Valor cheio de tabela da mensalidade.
  - `percentual_desconto_mensalidade`: Decimal (5,2) - Desconto pretendido na mensalidade (ex: 12.50%).
  - `valor_mensalidade_com_desconto`: Decimal (10,2) - Valor líquido calculado por parcela.
  - `quantidade_parcelas`: Integer (default 12) - Número de mensalidades contratuais.
  - `valor_anuidade_total`: Decimal (10,2) - Montante anual total contratado com desconto.
  - `valor_taxa_matricula_tabela`: Decimal (10,2) - Taxa de matrícula cheia de tabela.
  - `percentual_desconto_matricula`: Decimal (5,2) - Desconto concedido na matrícula.
  - `valor_taxa_matricula_com_desconto`: Decimal (10,2) - Taxa de matrícula líquida negociada.
  - `nivel_alcada`: Enum `NivelAlcadaComercial` (`consultor` até 7%, `coordenacao` de 7.01% a 15%, `diretoria` acima de 15%).
  - `status`: Enum `StatusPropostaComercial` (`rascunho`, `pendente_aprovacao`, `aprovada`, `recusada`, `expirada`, `convertida`).
  - `justificativa_comercial`: Text (nullable) - Motivo da concessão do desconto (ex: irmãos, transferência tardia, pagamento pontual).
  - `motivo_recusa`: Text (nullable) - Justificativa formal registrada pelo gestor ao reprovar a alçada.
  - `termos_condicoes`: Text (nullable) - Cláusulas e condições especiais acordadas.
  - `data_validade`: Date - Limite temporal de vigência da proposta comercial.
  - `aprovado_em`: Datetime (nullable) - Timestamp da liberação da alçada.
  - `recusado_em`: Datetime (nullable) - Timestamp de eventual recusa.
  - `convertido_em`: Datetime (nullable) - Data da conversão em matrícula.
  - `matricula_id`: FK `matricula.id` (nullable) - Matrícula efetivada originada por esta proposta.
- **Relacionamentos:** BelongsTo `Interessado`, BelongsTo `InteressadoDependente`, BelongsTo `Serie`, BelongsTo `Turma`, BelongsTo `PeriodoLetivo`, BelongsTo `Turno`, BelongsTo `User` (`consultor` e `aprovador`), BelongsTo `Matricula`.
- **Governança & Integrações:** 
  - Cálculo instantâneo via `RevenueManagementService` com bloqueio contra estouro de margem sem alçada.
  - Propostas dentro da alçada do consultor são auto-aprovadas instantaneamente; propostas acima disparam notificação interna aos aprovadores com permissão `Aprovar:PropostaComercial`.
  - Visualização timbrada (espelho da proposta comercial em modal), disparo instantâneo via WhatsApp e herança transparente de condições no **Assistente de Matrícula** (`/admin/enrollment-wizard?proposta_id=...`).

### `planilha_lei_mensalidades` (Planilha de Variação de Custos e Reajuste Anual - Lei Federal 9.870/1999)
- **Representa:** Memória de cálculo oficial exigida pela Lei Federal 9.870/99 e Decreto 3.274/99 para embasamento de reajustes anuais de anuidade escolar, com prazo de publicação prévia de 45 dias antes da matrícula.
- **Campos Principais:**
  - `ano_base`: Ano corrente/anterior de referência contábil (ex: 2026).
  - `ano_letivo_destino`: Ano seguinte planejado com a nova anuidade (ex: 2027).
  - `titulo`: Identificação formal do demonstrativo.
  - `unidade_id`: FK `unidade.id` (nullable - escopo por unidade ou geral).
  - `curso_id`: FK `curso.id` (nullable - escopo por curso/nível ou geral).
  - `alunos_base`: Quantidade de alunos pagantes no ano base.
  - `mensalidade_media_base`: Valor médio praticado no exercício base.
  - `receita_anual_base`: Projeção de faturamento anual do ano base.
  - `custo_pessoal_base`: Total folha salarial de professores e administrativos + encargos.
  - `custo_custeio_base`: Total de despesas operacionais e manutenção geral.
  - `custo_investimento_base`: Investimentos e melhorias físicas/tecnológicas realizadas no ano base.
  - `custo_total_base`: Consolidação dos 3 grupos de custo no ano base.
  - `percentual_dissidio_pessoal`: Variação salarial da convenção coletiva / dissídio docente.
  - `variacao_pessoal_valor`: Acréscimo financeiro em pessoal.
  - `custo_pessoal_projetado`: Folha projetada do exercício seguinte.
  - `percentual_inflacao_custeio`: Variação de inflação/insumos para custeio geral.
  - `variacao_custeio_valor`: Acréscimo financeiro em custeio.
  - `custo_custeio_projetado`: Custeio projetado do exercício seguinte.
  - `valor_novos_investimentos`: Aporte em melhorias pedagógicas e infraestrutura autorizadas pelo Art. 1º, § 3º.
  - `custo_investimento_projetado`: Custo projetado de investimentos.
  - `custo_total_projetado`: Montante total de despesas projetadas.
  - `variacao_custo_total_percentual`: Índice matemático resultante da fórmula oficial da lei.
  - `percentual_reajuste_sugerido`: Índice de reajuste recomendado.
  - `percentual_reajuste_adotado`: Índice efetivamente homologado pela diretoria.
  - `mensalidade_projetada`: Nova mensalidade fixada por aluno.
  - `anuidade_projetada`: Novo valor da anuidade anual (12 parcelas).
  - `meta_alunos_projetada`: Projeção de estudantes no exercício seguinte.
  - `status`: Enum `StatusPlanilhaLei` (`rascunho`, `em_analise`, `homologada`, `publicada`).
  - `justificativa_pedagogica`: Descrição formal das melhorias e inovações didáticas (exigência PROCON).
  - `data_afixacao`: Data em que a planilha foi tornada pública no mural/portal.
  - `responsavel_user_id`: FK `users.id` (elaborador).
  - `homologado_por_user_id`: FK `users.id` (diretor que homologou).
  - `homologado_em`: Data e hora da homologação.
- **Relacionamentos:** BelongsTo `Unidade`, BelongsTo `Curso`, BelongsTo `User` (`responsavel` e `homologador`).

### `acordo_inadimplencias` e `acordo_parcelas` (Central de Acordos Online & Confissão de Dívida)
- **Representa:** Renegociação formal de faturas vencidas com emissão de Termo de Acordo e Confissão de Dívida com eficácia de Título Executivo Extrajudicial (Art. 784, III do CPC).
- **Campos Principais (`acordo_inadimplencias`):**
  - `codigo`: Identificador sequencial auditável (ex: `ACD-2026-00001`).
  - `contrato_id`: FK `contrato.id` (nullable).
  - `matricula_id`: FK `matricula.id` (estudante vinculado).
  - `responsavel_pessoa_id`: FK `pessoa.id` (responsável financeiro devedor).
  - `criado_por_user_id`: FK `users.id` (operador do financeiro).
  - `valor_original_total`: Soma histórica das mensalidades em atraso.
  - `valor_multa_original`: Multa moratória acumulada das faturas.
  - `valor_juros_original`: Juros de mora acumulados.
  - `quantidade_faturas_originais`: Contagem de mensalidades refinanciadas.
  - `faturas_originais_ids`: Array JSON contendo os IDs das faturas originais.
  - `percentual_desconto_concedido`: Abatimento concedido sobre encargos/dívida para estimular quitação.
  - `valor_desconto`: Valor nominal do desconto aplicado.
  - `valor_total_acordo`: Montante líquido consolidado a ser pago pela família.
  - `valor_entrada`: Sinal/entrada exigido no acordo.
  - `data_vencimento_entrada`: Prazo para pagamento da entrada.
  - `quantidade_parcelas`: Número de parcelas do acordo (1 a 24).
  - `valor_parcela`: Valor nominal de cada parcela mensal.
  - `dia_vencimento_parcelas`: Dia fixo do mês para os vencimentos subsequentes.
  - `primeiro_vencimento`: Data de vencimento da primeira parcela mensal.
  - `token_publico`: Token hash seguro (64 caracteres) para acesso e assinatura digital pela família sem login.
  - `status`: Enum `StatusAcordoInadimplencia` (`simulado`, `aguardando_aceite`, `ativo`, `cumprido`, `quebrado`, `cancelado`).
  - `termo_confissao_texto`: Minuta jurídica completa da confissão de dívida e cláusula resolutiva expressa.
  - `aceito_em`: Timestamp do aceite online formal da família.
  - `ip_aceite`: Endereço IP do dispositivo no momento da assinatura eletrônica.
  - `user_agent_aceite`: Identificador do navegador/dispositivo do devedor.
  - `observacoes`: Anotações internas do financeiro.
- **Campos Principais (`acordo_parcelas`):**
  - `acordo_inadimplencia_id`: FK `acordo_inadimplencias.id` (cascade).
  - `numero_parcela`: 0 para entrada facilitada, 1..N para parcelas mensais.
  - `valor`: Valor da parcela.
  - `data_vencimento`: Data de vencimento da parcela.
  - `data_pagamento`: Data em que a parcela foi quitada.
  - `valor_pago`: Montante efetivamente recebido.
  - `status`: String (`pendente`, `pago`, `atrasado`, `cancelado`).
  - `forma_pagamento`: Meio de pagamento (`pix`, `dinheiro`, `cartao`, `boleto`).
  - `fatura_gerada_id`: FK `faturas.id` (nullable).
---

## 10. Módulo de Documentos e Secretaria Escolar
Gestão de tipos de documentos, anexos comprobatórios da família e checklist de admissão e matrícula.

### `tipo_documento`
- **Representa:** Categorias e tipos de documentos exigidos pela instituição escolar para admissão, formalização contratual e vida acadêmica.
- **Campos Principais:**
  - `nome`: Nome oficial do documento (ex: Certidão de Nascimento, RG do Responsável, Histórico Escolar Anterior).
  - `categoria_exigencia`: Enum `CategoriaExigenciaDocumento` com as opções:
    - `obrigatorio_contrato`: Documento bloqueante para emissão do Contrato Escolar e ativação da matrícula. Sem ele, a matrícula permanece como `pendente`.
    - `obrigatorio_historico`: Exigido para conformidade com a vida acadêmica e órgãos educacionais (MEC). Não bloqueia a emissão do contrato.
    - `opcional`: Documento complementar (ex: laudo médico, cartão de vacina, convênio). Aparece no Portal da Família / Admissão para envio facultativo.
    - `interno_secretaria`: Documento de uso exclusivo e arquivo interno da secretaria. Oculto dos portais externos da família e dos wizards de matrícula.
  - `flag_obrigatorio`: Boolean legado mantido e sincronizado automaticamente via evento `saving` do Model (true para `obrigatorio_contrato` e `obrigatorio_historico`, false para os demais).
  - `modelo_arquivo`: Caminho de arquivo PDF com modelo/termo para download pelo responsável.
  - `modelo_link`: Link externo com orientações ou formulário oficial.
- **Relacionamentos & Regras de Abrangência por Curso:**
  - BelongsToMany `curso` (tabela pivô `tipo_documento_curso`):
    - **Cursos Específicos:** Quando um tipo de documento é vinculado a um ou mais cursos específicos, sua exigência (para contrato ou histórico) restringe-se exclusivamente aos alunos e interessados matriculados ou pretendentes desses cursos.
    - **Documentos Globais / Gerais:** Quando nenhum curso é vinculado (relação pivô vazia), o documento possui abrangência universal e é cobrado de todos os cursos da instituição.
    - **Isolamento de Pendências:** Documentos vinculados a outros cursos não são exibidos nos portais de admissão de candidatos de cursos distintos, não bloqueiam a geração de contrato no `EnrollmentWizard` e não são contabilizados em `getMissingMandatoryDocuments()` de matrículas não relacionadas.
  - BelongsToMany `turma` (tabela pivô `tipo_documento_turma`).
  - HasMany `documento_inserido`.

### `documento_inserido`
- **Representa:** Arquivos e comprovantes digitais enviados pelos candidatos/famílias ou arquivados pela secretaria escolar.
- **Campos Principais:**
  - `tipo_documento_id`: FK `tipo_documento.id`.
  - `interessado_id`: FK `interessados.id` (nullable, quando anexado durante a etapa de prospecção/admissão pelo CRM).
  - `matricula_id`: FK `matricula.id` (nullable, quando vinculado à matrícula definitiva do estudante).
  - `arquivo_path`: Caminho do arquivo armazenado no storage.
  - `nome_arquivo_original`: Nome do arquivo enviado pelo usuário.
  - `status`: Enum `SituacaoDocumento` (`pendente`, `em_analise`, `verificado`, `rejeitado`).
  - `observacoes`: Parecer da secretaria ou motivo de rejeição.
  - `metadados_ia`: JSON com validações automáticas por OCR e Inteligência Artificial (`ValidarDocumentoComIaJob`).
- **Relacionamentos:**
  - BelongsTo `tipo_documento`.
  - BelongsTo `interessado`.
  - BelongsTo `matricula`.

---

## 11. Módulo de Biblioteca
Gestão do acervo de livros e controle de empréstimos e devoluções para a comunidade escolar.

### `livros`
- **Representa:** Obras e títulos disponíveis no acervo bibliográfico da instituição.
- **Campos Principais:**
  - `titulo`: Título completo da obra.
  - `autor`: Nome do autor ou autores principais.
  - `isbn`: Código padrão internacional de identificação do livro (10 ou 13 dígitos).
  - `editora`: Casa publicadora da edição.
  - `categoria`: Classificação temática / assunto da obra.
  - `capa`: Caminho da imagem da capa armazenada no disco público (`livros/capas/...`). Suporta upload manual e download automático via APIs (Open Library, BrasilAPI e Google Books).
  - `quantidade_total`: Total de cópias físicas pertencentes ao acervo.
  - `quantidade_disponivel`: Quantidade de exemplares livres para novos empréstimos.
  - `codigo`: Código de tombo ou chamada único do exemplar/obra (ex: `LIV-0001`), facilitando leitura por leitor de código de barras.
  - `faixa_etaria`: Classificação etária indicativa (ex: `Livre`, `4 a 6 anos`, `7 a 9 anos`, `10 a 12 anos`, `13 a 15 anos`, `16+ anos`).
  - `segmentos`: Array JSON com os segmentos de ensino indicados (`educacao_infantil`, `fundamental_1`, `fundamental_2`, `ensino_medio`).
- **Relacionamentos:**
  - HasMany `emprestimos`.

### `emprestimos`
- **Representa:** Empréstimos físicos de livros realizados por alunos, professores e responsáveis.
- **Campos Principais:**
  - `livro_id`: FK `livros.id` (cascade on delete).
  - `pessoa_id`: FK `pessoa.id` (usuário que realizou o empréstimo).
  - `data_emprestimo`: Data de retirada da obra.
  - `data_previsao_devolucao`: Prazo limite para devolução.
  - `data_devolucao`: Data efetiva de entrega da obra.
  - `status`: Situação do empréstimo (`ativo`, `devolvido`, `atrasado`).
  - `observacoes`: Informações adicionais ou notas de conservação do exemplar.
- **Relacionamentos:**
  - BelongsTo `livro`.
  - BelongsTo `pessoa`.

### `inventarios_acervo`
- **Representa:** Sessões de auditoria física e inventário periódicas do acervo da biblioteca.
- **Campos Principais:**
  - `titulo`: Nome descritivo da conferência (ex: *Inventário Anual 2026*).
  - `data_inicio`: Data em que a auditoria foi aberta.
  - `data_fim`: Data de encerramento da contagem física.
  - `status`: Situação do inventário (`em_andamento`, `concluido`).
  - `user_id`: FK `users.id` (colaborador/bibliotecário responsável).
  - `observacoes`: Anotações gerais da auditoria.
- **Relacionamentos:**
  - HasMany `inventarioItens`.
  - BelongsTo `user`.

### `inventario_itens`
- **Representa:** Registros de livros fisicamente localizados e conferidos (bipados) durante uma sessão de auditoria.
- **Campos Principais:**
  - `inventario_id`: FK `inventarios_acervo.id` (cascade).
  - `livro_id`: FK `livros.id` (cascade).
  - `bipado_em`: Data e hora exata da conferência óptica/leitura.
  - `quantidade_conferida`: Quantidade física contada.
  - `user_id`: FK `users.id` do conferente.
- **Relacionamentos:**
  - BelongsTo `inventario`.
  - BelongsTo `livro`.

### `sacolas_leitura`
- **Representa:** Lotes de empréstimo coletivo de livros destinados a turmas e salas de aula (ex: Educação Infantil e Fundamental I), gerenciados por professores regentes.
- **Campos Principais:**
  - `codigo`: Tombo único da sacola (ex: `SAC-0001`).
  - `titulo`: Nome descritivo da sacola (ex: *Sacola Literária - 1º Ano A (Outubro)*).
  - `turma_id`: FK `turma.id` (turma beneficiada).
  - `responsavel_id`: FK `pessoa.id` (professor(a) regente responsável pela retirada).
  - `user_id`: FK `users.id` (colaborador/bibliotecário que expediu a sacola).
  - `data_retirada`: Data em que a sacola saiu da biblioteca.
  - `data_prevista_devolucao`: Prazo previsto para retorno da sacola (geralmente ciclo de 15 a 30 dias).
  - `data_devolucao`: Data efetiva de conclusão da devolução.
  - `status`: Situação da sacola (`em_circulacao`, `parcialmente_devolvida`, `devolvida`, `atrasada`).
  - `observacoes`: Anotações pedagógicas e cuidados com as obras.
- **Relacionamentos:**
  - BelongsTo `turma`.
  - BelongsTo `responsavel` (`Pessoa`).
  - BelongsTo `user`.
  - HasMany `itens` (`SacolaLeituraItem`).

### `sacola_leitura_itens`
- **Representa:** Obras individuais vinculadas e reservadas para uma sacola de leitura específica.
- **Campos Principais:**
  - `sacola_id`: FK `sacolas_leitura.id` (cascade).
  - `livro_id`: FK `livros.id` (cascade).
  - `devolvido`: Booleano indicando se o exemplar já retornou fisicamente ao acervo da biblioteca.
  - `devolvido_em`: Data e hora exata em que a baixa/conferência do exemplar foi realizada.
  - `observacao_devolucao`: Notas de conservação ou avarias ao receber a obra.
- **Relacionamentos:**
  - BelongsTo `sacola`.
  - BelongsTo `livro`.

---

## 15. Secretaria e Consentimentos LGPD (Onda 25)
Módulo responsável pela gestão de autorizações de uso de imagem, voz e consentimentos de privacidade (LGPD e ECA) das famílias para cada estudante matriculado.

### `tipo_consentimentos`
- **Representa:** Catálogo de finalidades de consentimento e termos jurídicos escolares (ex.: *Autorização de Uso de Imagem e Voz*, *Divulgação em Redes Sociais*, *Material Publicitário Impresso*).
- **Campos Principais:**
  - `nome`: Título da autorização (string, unique).
  - `descricao`: Texto explicativo e finalidades claras de tratamento apresentadas aos responsáveis (text).
  - `periodicidade_meses`: Intervalo em meses para renovação obrigatória (integer, nullable). Se nulo, a vigência é válida para todo o ciclo letivo.
  - `is_ativo`: Booleano indicando se o consentimento está em vigor para coleta no Portal da Família.
- **Relacionamentos:**
  - HasMany `consentimentosMatricula` (`ConsentimentoMatricula`).

### `consentimento_matriculas`
- **Representa:** Registro do aceite ou recusa digital (ou lançamento manual pela secretaria) para um aluno específico matriculado.
- **Campos Principais:**
  - `matricula_id`: FK `matricula.id` (cascade on delete).
  - `tipo_consentimento_id`: FK `tipo_consentimentos.id` (cascade on delete).
  - `status`: Enum `StatusConsentimento` (`pendente`, `autorizado`, `nao_autorizado`).
  - `respondido_em`: Timestamp exato em que a resposta foi registrada.
  - `respondido_por_user_id`: FK `users.id` do usuário que assinou/registrou.
  - `respondido_ip`: Endereço IP de origem do responsável no momento da assinatura digital no Portal.
  - `observacao`: Justificativas ou notas complementares colhidas pela secretaria.
  - `vigencia_fim`: Data de expiração calculada da autorização com base na periodicidade do tipo.
- **Relacionamentos:**
  - BelongsTo `matricula`.
  - BelongsTo `tipoConsentimento`.
  - BelongsTo `respondidoPorUser` (`User`).

