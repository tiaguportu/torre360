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
- **Campos Principais:** `nome`, `cpf`, `data_nascimento`, `foto` (armazenamento privado), `email`, `sexo` (Enum), `cor_raca` (Enum).
- **Relacionamentos:** 
    - BelongsToMany `endereco` (via `endereco_pessoa`).
    - BelongsTo `cidade` (naturalidade), `pais` (nacionalidade).
    - HasMany `matriculas`.
    - BelongsToMany `unidade` (via `representante_unidade`) como Representante Legal.
    - HasMany `necessidadesEducacaoEspecial`.
    - HasMany `transtornosAprendizagem`.
    - HasMany `recursosAcessibilidade`.

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
- **Relacionamentos:** HasMany `cronograma_aula`, HasMany `habilidades`, BelongsToMany `turma` (via `turma_disciplina`).
- **Propósito da `ordem_boletim`:** Define a sequência numérica para ordenação das disciplinas na visualização e impressão de boletins.

### `turma_disciplina`
- **Representa:** Tabela pivô que define a grade curricular (disciplinas) de uma turma específica.
- **Campos:** `turma_id`, `disciplina_id`.

### `matricula`
- **Representa:** Vínculo do aluno com uma turma em um período letivo.
- **Campos Principais:**
    - `situacao`: Enum (`App\Enums\SituacaoMatricula`). Estados: `ativa`, `pendente`, `trancada`, `cancelada`, `concluido`, `reserva`, `evasao`.
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

### `frequencia_escolar`
- **Representa:** Presença ou falta dos alunos em uma aula do cronograma.
- **Relacionamentos:** BelongsTo `matricula`, BelongsTo `cronograma_aula`.
- **Campos Principais:** `matricula_id`, `cronograma_aula_id`, `situacao` (Enum/String: 'presente', 'ausente').
- **Auditoria:** Mapeado para o log de atividades (`activity_log` com `log_name: frequencia_escolar`), gravando ações de criação, alteração e exclusão das frequências com a identificação do aluno, aula e situação atribuída.

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
- Cobranças geradas a partir de contratos.

### `transacao_bancarias`
- Fluxo de caixa e conciliação bancária.

---

## 6. CRM e Prospecção
### `interessado`
- **Representa:** Leads para novos alunos.
- **Campos Principais:** `pessoa_id`, `status_interessado_id`, `origem_interessado_id`, `usuario_id` (opcional/nullable), `observacoes`.
- **Relacionamentos:** 
    - BelongsTo `pessoa`.
    - BelongsTo `status_interessado`.
    - BelongsTo `origem_interessado`.
    - BelongsTo `users` (Consultor Responsável).
    - HasMany `dependentes` (InteressadoDependente).
    - HasMany `historico_contato`.
    - HasOne `ultimoHistorico` (Latest of Many).

### `interessado_dependente` (Alunos Vinculados)
- **Representa:** Os potenciais alunos vinculados a um interessado principal.
- **Campos Principais:** `interessado_id`, `nome_crianca`, `serie_id`, `vinculo` (Pai, Mãe, Parente, Tutor), `data_nascimento`.

### `historico_contato`
- **Representa:** Registro de cada interação com o interessado (ligação, visita, etc).
- **Campos Principais:** `relato`, `data_contato`.
- **Relacionamentos:** BelongsTo `interessado`, BelongsTo `tipo_contato_interessado`.

### `status_interessado` e `origem_interessado`
- Tabelas de configuração para as etapas do funil e fontes de captação.

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

