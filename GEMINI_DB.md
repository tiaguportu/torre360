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
- **Representa:** Registro de atividades realizadas no sistema.
- **Relacionamentos:** BelongsTo `users`, MorphTo `auditable`.

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
- **Campos de Assinatura (Assinafy):** `assinafy_id`, `assinafy_status`, `assinafy_pdf_url`, `assinafy_request_log`.

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
