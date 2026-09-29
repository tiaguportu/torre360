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
+
+### `roles`, `permissions`, `model_has_roles` (Spatie/Shield)
+- **Representa:** Sistema de controle de acesso baseado em papéis.
+- **Propósito:** Define permissões granulares para os recursos do painel administrativo (Resources, Pages e Widgets).
+- **Configuração:** Gerenciado via `filament-shield`.

---

## 2. Pessoas e Geografia
Base cadastral de qualquer indivíduo ou entidade no sistema.

### `pessoa`
- **Campos Principais:** `nome`, `cpf` (armazenado apenas como dígitos numéricos, sem pontos ou traço), `data_nascimento`, `foto` (armazenamento privado), `email`, `aceita_comunicacao` (boolean, padrão true — opt-out de comunicações em massa por LGPD; não afeta notificações individuais obrigatórias), `sexo` (Enum), `cor_raca` (Enum - 0:Não Declarada, 1:Branca, 2:Preta, 3:Parda, 4:Amarela, 5:Indígena), `tipo_nacionalidade` (Enum - 1:Brasileira, 2:Naturalizado/Exterior, 3:Estrangeira), `nacionalidade_id` (Pais).
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
- **Turma - Campos Principais:** `nome`, `codigo`, `serie_id`, `turno_id`, `etapa_ensino_agregada_id`, `etapa_ensino_id`, `professor_conselheiro_id`, `vagas_maximas`, `carga_horaria_total` (em horas), `cor`, `tipo_avaliacao` (Enum: notas, habilidades, hibrido), `tipo_mediacao_didatico_pedagogica` (1-Presencial, 2-Semipresencial, 3-EAD), `tipo_turma` (4-Atividade complementar, 5-AEE, 6-Curricular, 9-Curricular c/ Ativ. Comp.), `local_funcionamento_diferenciado` (0-Não diferenciado, 1-Sala anexa, 2-Unidade socioeducativa, 3-Unidade prisional), `turma_educacao_especial` (boolean), `forma_organizacao` (1-Série/Ano, 2-Semestral, 3-Ciclos, 4-Grupos não seriados, 5-Módulos, 6-Alternância), `modalidade_ensino` (1-Regular, 2-Especial, 3-EJA, 4-Profissional), `tipo_lingua_ministrada` (1-Português, 2-Indígena+Português, 3-Indígena), `codigo_lingua_indigena`, `turma_educacao_bilingue_surdos` (boolean) e flags de AEE (`flag_aee_*`).
- **Relacionamentos:** BelongsTo `etapaEnsinoAgregada` (`etapa_ensino_agregada`), BelongsTo `etapaEnsino` (`etapa_ensino`), HasMany `horariosFuncionamento` (`turma_horario`).

### `matricula`
- **Representa:** Registro de matrícula acadêmica de um estudante na instituição de ensino.
- **Campos Principais:**
    - `pessoa_id`: BelongsTo `pessoa` (Aluno).
    - `turma_id`: BelongsTo `turma` (nullable, suporta matrículas aguardando alocação de sala no ensalamento).
    - `serie_id`: BelongsTo `serie` (nullable, série/ano escolar do estudante para fins de planejamento e ensalamento em lote).
    - `periodo_letivo_id`: BelongsTo `periodo_letivo`.
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
- **Representa:** Ambiente físico da unidade (sala de aula, laboratório, quadra etc.), usado para reservar espaço na grade horária. **Não confundir** com o "ensalamento" de `EnsalamentoService`, que distribui alunos entre turmas.
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
- **Relacionamentos:** BelongsTo `avaliacao`, BelongsTo `matricula` (Aluno).

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
    - `pix_copia_e_cola`: Código ou chave PIX para pagamento.
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
- **Campos Principais:** `pessoa_id`, `status_interessado_id`, `origem_interessado_id`, `campanha_marketing_id` (FK nullable, `nullOnDelete`), `utm_source`/`utm_medium`/`utm_campaign` (string nullable — atribuição de campanha, first touch), `usuario_id` (opcional/nullable), `observacoes`, `data_proximo_contato` (datetime nullable), `valor_estimado` (decimal nullable), `temperatura` (string nullable: quente/morno/frio), `motivo_perda` (string nullable), `data_primeiro_contato` (datetime nullable), `data_conversao` (datetime nullable).
- **Relacionamentos:** 
    - BelongsTo `pessoa`.
    - BelongsTo `status_interessado`.
    - BelongsTo `origem_interessado`.
    - BelongsTo `campanha_marketing` (`campanha`).
    - BelongsTo `users` (Consultor Responsável).
    - HasMany `dependentes` (InteressadoDependente).
    - HasMany `historico_contato`.
    - HasMany `visita_interessado` (`visitas`).
    - HasOne `ultimoHistorico` (Latest of Many).
    - HasOne `proximaVisita` (visita agendada futura mais próxima).
- **Auditoria:** Trilha de auditoria via `activity_log` com `log_name: crm`, rastreando mudanças em status, temperatura, consultor e valor.
- **Scopes:** `ativos()` (não finalizados), `precisaContato()` (contato atrasado), `doConsultor($id)`.
- **Métodos de Negócio:** `precisaDeContato()`, `diasNoFunil()`, `temperaturaCalculada()`, `totalContatos()`.

### `interessado_dependente` (Alunos Vinculados)
- **Representa:** Os potenciais alunos vinculados a um interessado principal.
- **Campos Principais:** `interessado_id`, `nome_crianca`, `serie_id`, `vinculo` (Pai, Mãe, Parente, Tutor), `data_nascimento`.

### `historico_contato`
- **Representa:** Registro de cada interação com o interessado (ligação, visita, etc).
- **Campos Principais:** `relato`, `data_contato`, `usuario_id` (FK `users`, nullable — quem registrou), `duracao_minutos` (integer nullable), `resultado` (string nullable: agendou_visita, retornar, sem_interesse, matriculou, outro).
- **Relacionamentos:** BelongsTo `interessado`, BelongsTo `tipo_contato_interessado`, BelongsTo `users` (usuário que registrou).

### `status_interessado`
- **Representa:** Etapas do funil de vendas.
- **Campos Principais:** `nome`, `cor`, `ordem`, `is_final` (boolean — indica status de encerramento), `is_ganho` (boolean — indica conversão/matrícula).
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
- **Índice:** (`status`, `data_hora`).
- **Relacionamentos:** BelongsTo `interessado`, BelongsTo `interessado_dependente` (`dependente`), BelongsTo `users` (`usuario`).

### `landing_leads`
- **Representa:** Pedidos de demonstração recebidos pela landing page do produto (`/`). São leads **B2B** (escolas), independentes de `interessado`.
- **Campos Principais:** `nome`, `email`, `whatsapp` (nullable), `mensagem` (nullable), `status` (`novo` padrão, `em_contato`, `descartado`).

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
- **Representa:** Campanhas anuais ou semestrais de rematrícula online para as famílias.
- **Campos Principais:**
  - `periodo_letivo_origem_id`: FK `periodo_letivo.id` (ano/semestre letivo corrente).
  - `periodo_letivo_destino_id`: FK `periodo_letivo.id` (ano/semestre letivo de destino para renovação).
  - `titulo`: Nome da campanha (ex: Rematrícula Online 2027).
  - `data_inicio`, `data_fim`: Período de vigência em que o formulário fica aberto no Portal da Família.
  - `instrucoes`: Texto explicativo e orientações exibidas no portal.
  - `permite_inadimplentes`: Booleano para trava financeira (se `false`, bloqueia rematrícula para quem tiver mensalidades pendentes).
  - `ativo`: Booleano que define a campanha em andamento.
- **Relacionamentos:** BelongsTo `PeriodoLetivo` (origem e destino), HasMany `Rematricula`.

### `rematriculas`
- **Representa:** O registro da manifestação de renovação/rematrícula de um aluno.
- **Campos Principais:**
  - `periodo_rematricula_id`: FK `periodo_rematriculas.id`.
  - `matricula_origem_id`: FK `matricula.id` (matrícula que está sendo renovada).
  - `matricula_gerada_id`: FK `matricula.id` (nova matrícula gerada no período subsequente após aprovação/efetivação).
  - `responsavel_financeiro_id`: FK `pessoa.id`.
  - `serie_pretendida_id`: FK `serie.id`.
  - `turno_pretendido_id`: FK `turno.id`.
  - `status`: Enum `StatusRematricula` (`pendente`, `confirmada_responsavel`, `aprovada_secretaria`, `rejeitada`, `efetivada`).
  - `data_confirmacao`: Data e hora em que a família enviou a confirmação pelo Portal.
  - `ip_confirmacao`: Endereço IP do responsável para rastreabilidade de assinatura.
  - `observacoes`: Observações pedagógicas ou observações da família.
  - `contrato_gerado_id`: FK `contrato.id` (novo contrato financeiro formalizado na rematrícula).
- **Relacionamentos:** BelongsTo `PeriodoRematricula`, BelongsTo `Matricula` (origem e gerada), BelongsTo `Pessoa` (responsável financeiro), BelongsTo `Serie`, BelongsTo `Turno`, BelongsTo `Contrato`.

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
