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
- **Representa:** Alunos, Responsáveis, Professores e Coordenadores.
- **Relacionamentos:** 
    - BelongsToMany `endereco` (via `endereco_pessoa`).
    - BelongsTo `cidade` (naturalidade), `pais` (nacionalidade).
    - HasMany `matriculas`.

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
- Vínculo do aluno com uma turma em um período letivo.

---

## 4. Avaliação e Frequência
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
- Leads para novos alunos.

---

## 7. Documentação
### `tipo_documento` e `documento_inserido`
- Gestão de documentos obrigatórios para matrícula.
