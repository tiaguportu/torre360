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
- **Representa:** Sistema de notificações internas do Laravel/Filament (Sininho).
- **Propósito:** Armazena mensagens destinadas aos usuários que são exibidas no painel administrativo.
- **Campos Principais:** `id` (UUID), `type`, `notifiable_type`, `notifiable_id`, `data` (JSON), `read_at`.

### `activity_log` (Spatie)
- **Representa:** Registro de atividades de negócio e trilha de auditoria detalhada.
- **Campos Principais:** 
    - `log_name`: Canal do log (ex: `default`, `auth`).
    - `description`: Descrição amigável do evento.
    - `subject_type`, `subject_id`: Registro afetado.
    - `causer_type`, `causer_id`: Usuário/Sistema que causou a ação.
- `properties`: JSON com metadados e alterações. Registra também tentativas e respostas de disparos de notificações (e-mail/push).
+
+### `roles`, `permissions`, `model_has_roles` (Spatie/Shield)
+- **Representa:** Sistema de controle de acesso baseado em papéis.
+- **Propósito:** Define permissões granulares para os recursos do painel administrativo (Resources, Pages e Widgets).
+- **Configuração:** Gerenciado via `filament-shield`.

---

## 2. Pessoas e Geografia
Base cadastral de qualquer indivíduo ou entidade no sistema.

### `pessoa`
- **Principais Campos:** `nome`, `cpf`, `data_nascimento`, `foto` (armazenamento privado), `email`.
- **Relacionamentos:** 
    - BelongsToMany `endereco` (via `endereco_pessoa`).
    - BelongsTo `cidade` (naturalidade), `pais` (nacionalidade).
    - HasMany `matriculas`.
    - BelongsToMany `unidade` (via `representante_unidade`) como Representante Legal.

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

### `unidade`
- **Representa:** Unidades físicas da instituição.
- **Relacionamentos:**
    - BelongsTo `endereco`.
    - HasMany `curso`.
    - BelongsToMany `pessoa` (via `representante_unidade`) para Representantes Legais.

### `representante_unidade`
- **Representa:** Tabela pivô entre unidades e seus representantes legais (pessoas).
- **Campos:** `unidade_id`, `pessoa_id`.

---

## 3. Gestão Acadêmica
Estrutura de ensino e turmas.

### `curso`, `serie`, `turma`
- Estrutura hierárquica de ensino. Cursos possuem Séries, que possuem Turmas.

### `disciplina`
- **Representa:** Matérias ou componentes curriculares.
- **Campos Principais:** `nome`, `slug`, `cor`, `ordem_boletim`.
- **Relacionamentos:** HasMany `cronograma_aula`, HasMany `habilidades`.
- **Propósito da `ordem_boletim`:** Define a sequência numérica para ordenação das disciplinas na visualização e impressão de boletins.

### `matricula`
- **Representa:** Vínculo do aluno com uma turma em um período letivo.
- **Campos Principais:**
    - `situacao`: Enum (`App\Enums\SituacaoMatricula`). Estados: `ativa`, `pendente`, `trancada`, `cancelada`, `concluido`, `reserva`, `evasao`.
    - `periodo_letivo_id`: BelongsTo `periodo_letivo`.
    - `turma_id`: BelongsTo `turma`.
    - `pessoa_id`: BelongsTo `pessoa` (Aluno).

---

## 4. Avaliação e Frequência
### `categoria_avaliacao`
- **Representa:** Categorias de avaliações (ex: Prova, Trabalho, Simulado).
- **Campos Principais:** `nome`, `descricao`, `ordem_boletim`.
- **Propósito da `ordem_boletim`:** Define a sequência numérica para ordenação das categorias de avaliação no boletim.

### `avaliacao` e `nota`
- Registro acadêmico de desempenho.

### `cronograma_aula` e `frequencia_escolar`
- Registro de aulas e presença.

---

## 5. Gestão Financeira
### `contrato`
- Acordo comercial de prestação de serviço.
- **Principais Campos:** `valor_total`, `quantidade_parcelas` (nova), `data_aceite`.
- **Campos de Assinatura (Assinafy):** `assinafy_id`, `assinafy_status`, `assinafy_request_log`.
- **Relacionamentos:** HasMany `matriculas`, HasMany `responsavel_financeiro`, HasMany `faturas`.

### `faturas` e `item_faturas`
- Cobranças geradas a partir de contratos.

### `transacao_bancarias`
- Fluxo de caixa e conciliação bancária.

---

## 6. CRM e Prospecção
### `interessado`
- **Representa:** Leads para novos alunos.
- **Relacionamentos:** 
    - BelongsTo `pessoa`.
    - BelongsTo `status_interessado`.
    - BelongsTo `origem_interessado`.
    - HasMany `historico_contato`.
    - HasOne `ultimoHistorico` (Latest of Many).

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
