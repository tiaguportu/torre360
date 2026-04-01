# Torre360 - Sistema de Gestão Escolar

> **Torre360** é uma plataforma avançada de Gestão Escolar projetada para oferecer uma experiência administrativa moderna, rápida e completa para instituições de ensino.

## 🚀 Tecnologias e Arquitetura

O sistema é construído sobre uma pilha tecnológica robusta de última geração:

| Camada | Tecnologia |
|---|---|
| Linguagem | PHP 8.2+ |
| Framework Core | Laravel 12.0 |
| Painel Admin | Filament v5 |
| Banco de Dados | SQLite / MySQL (Eloquent ORM) |
| Frontend | TailwindCSS, Alpine.js, Livewire 3+ |
| Permissões (RBAC) | Spatie Laravel Permission + Filament Shield |

---

## 📐 Convenções de Banco de Dados

- **Nomenclatura:** Todas as tabelas usam o **singular** em português (`pessoa`, `turma`, `matricula`).
- **Chaves estrangeiras:** Padrão Laravel (`pessoa_id`, `turma_id`).
- **Pivôs:** Formato `entidade_a_entidade_b` (`pessoa_perfil`).

---

## 📚 Módulos do Sistema

### 1. 🌍 Geografia
Configuração das divisões territoriais usadas em endereços e naturalidade.

| Recurso | Descrição |
|---|---|
| `pais` | Nações para nacionalidade e endereços |
| `estado` | Estados/províncias vinculados a um país |
| `cidade` | Municípios para naturalidade e endereços |
| `endereco` | Endereço centralizado, reutilizado por Pessoa e Unidade |

---

### 2. 📋 Cadastros
Núcleo de entidades e parâmetros essenciais do sistema.

| Recurso | Descrição |
|---|---|
| `Pessoa` | Cadastro unificado — alunos, professores, responsáveis e coordenadores. Suporta foto de perfil (recorte 3:4), CPF, e-mail, telefone, naturalidade, nacionalidade, sexo e cor/raça. |
| `Sexo` | Valores fixos: Feminino, Masculino, Não declarado |
| `CorRaca` | Valores IBGE: Branca, Preta, Parda, Amarela, Indígena, Não declarado |
| `Unidade` | Unidades/polos físicos da instituição |
| `Perfil` | Papéis institucionais de uma Pessoa (Aluno, Professor, etc.) via tabela pivô `pessoa_perfil` |

> **Multi-perfil:** Uma mesma `Pessoa` pode ser ao mesmo tempo Aluno e Responsável Financeiro. O vínculo é feito pela tabela `pessoa_perfil`.

---

### 3. 🎓 Acadêmico
Estrutura curricular e planejamento pedagógico.

| Recurso | Descrição |
|---|---|
| `AreaConhecimento` | Agrupamento curricular (Linguagens, Exatas, Humanas...) |
| `Curso` | Unidade curricular macro (Ensino Fundamental, Médio...) |
| `Serie` | Anos/séries vinculadas a um curso |
| `Disciplina` | Matérias vinculadas a uma Área de Conhecimento |
| `Habilidade` | Competências por disciplina e série |
| `PeriodoLetivo` | **Eixo temporal central.** Ex: "1º Semestre 2025". Possui várias Turmas, Etapas e Dias Não Letivos. |
| `DiaNaoLetivo` | Feriados/recessos vinculados a um PeriodoLetivo |
| `Turma` | Classe de alunos — pertence a uma Série, Turno e PeriodoLetivo |
| `EtapaAvaliativa` | Bimestre/trimestre — pertence a um PeriodoLetivo |
| `Avaliacao` | Prova/trabalho — vinculada a uma EtapaAvaliativa, Disciplina e Turma. Possui `data_prevista`, `nota_maxima`, `peso_etapa_avaliativa`. |
| `Nota` | Nota individual — vincula uma Avaliacao a uma Matricula |
| `CronogramaAula` | Horário semanal de aulas por Turma, Disciplina e Professor |
| `Coordenador` | Vincula uma Pessoa como coordenadora de um Curso |

**Hierarquia pedagógica:**
```
PeriodoLetivo
 ├── DiaNaoLetivo
 ├── EtapaAvaliativa
 │    └── Avaliacao (+ Disciplina + Turma)
 │         └── Nota (por Matricula)
 └── Turma
      ├── CronogramaAula
      └── Matricula
```

---

### 4. 📝 Secretaria
Operação de secretaria virtual e vínculo aluno–escola.

| Recurso | Descrição |
|---|---|
| `SituacaoMatricula` | Status da matrícula (Ativo, Trancado, Evadido...) |
| `Matricula` | Vínculo Aluno ↔ Turma. Permite criação rápida de Pessoa diretamente no formulário. Exibe alunos como "Nome - CPF". |
| `Contrato` | Geração de contrato derivado de uma Matricula |
| `DocumentoObrigatorio` | Documentos exigidos por Curso |

---

### 5. 💰 Financeiro
Controle de tesouraria e cobrança.

| Recurso | Descrição |
|---|---|
| `ResponsavelFinanceiro` | Pessoa responsável pelos pagamentos de um contrato |
| `Titulo` | Parcelas/cobranças vinculadas a um contrato |
| `TributacaoCurso` | Natureza fiscal do curso para NFS-e |

---

### 6. ⚙️ Configurações (RBAC)
Controle de acesso baseado em papéis (RBAC).

| Recurso | Descrição |
|---|---|
| `User` | Usuário de sistema com e-mail/senha. Pode ser vinculado a uma `Pessoa`. |
| `Roles` | Papéis de acesso via **Filament Shield** (UI visual de permissões) |
| `Permissões` | Geradas automaticamente por recurso (`view`, `create`, `update`, `delete`) |

**Papéis padrão do sistema:**

| Role | Descrição |
|---|---|
| `super_admin` | Acesso irrestrito total |
| `admin` | Administrador do sistema |
| `secretaria` | Gestão de matrículas e alunos |
| `professor` | Cronograma e avaliações |
| `coordenador` | Visão de cursos e turmas |
| `responsavel` | Acesso financeiro |
| `aluno` | Perfil aluno (uso futuro) |

> **Atribuição:** Para dar acesso total a um usuário, atribua o papel `super_admin` em **Configurações → Usuários**.

---

## ⚙️ Instalação e Execução (Ambiente Local)

### Pré-requisitos
- PHP 8.2+
- Composer
- Node.js & NPM (v18+)
- XAMPP ou ambiente equivalente

### Passo a Passo

1. **Dependências PHP:**
   ```bash
   composer install
   ```

2. **Configuração do Ambiente:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   - Configure o banco de dados no `.env` (`DB_CONNECTION=sqlite` ou MySQL).

3. **Banco de Dados:**
   ```bash
   php artisan migrate
   ```

4. **Dados Iniciais (Seeds):**
   ```bash
   php artisan db:seed --class=CorRacaSeeder
   php artisan db:seed --class=SexoSeeder
   php artisan db:seed --class=RolesSeeder
   ```

5. **Armazenamento:**
   ```bash
   php artisan storage:link
   ```

6. **Frontend:**
   ```bash
   npm install
   npm run build
   ```

7. **Servidor de Desenvolvimento:**
   ```bash
   php artisan serve
   ```
   Acesse em: `http://127.0.0.1:8000/admin`

8. **Permissões RBAC (Shield):**
   ```bash
   php artisan shield:generate --all
   php artisan shield:super-admin
   ```

---

## 🗄️ Estrutura de Tabelas (Matriz)

| Tabela | Relacionamentos principais |
|---|---|
| `pais` | → `estado`, → `pessoa` (nacionalidade) |
| `estado` | ← `pais`, → `cidade` |
| `cidade` | ← `estado`, → `endereco`, → `pessoa` (naturalidade) |
| `endereco` | ← `cidade`, → `pessoa`, → `unidade` |
| `sexo` | → `pessoa` |
| `cor_raca` | → `pessoa` |
| `perfil` | ↔ `pessoa` (via `pessoa_perfil`) |
| `pessoa` | ← `endereco`, ← `sexo`, ← `cor_raca`, ← `user`, → `matricula`, → `coordenador` |
| `user` | → `pessoa` (opcional), ↔ `roles` |
| `unidade` | ← `endereco`, → `curso` |
| `curso` | ← `unidade`, → `serie`, → `coordenador`, → `documento_obrigatorio` |
| `serie` | ← `curso`, → `turma`, → `habilidade` |
| `area_conhecimento` | → `disciplina` |
| `disciplina` | ← `area_conhecimento`, → `habilidade`, → `avaliacao`, → `cronograma_aula` |
| `habilidade` | ← `serie`, ← `disciplina` |
| `turno` | → `turma` |
| `periodo_letivo` | → `turma`, → `etapa_avaliativa`, → `dia_nao_letivo` |
| `dia_nao_letivo` | ← `periodo_letivo` |
| `turma` | ← `serie`, ← `turno`, ← `periodo_letivo`, → `matricula`, → `avaliacao`, → `cronograma_aula` |
| `etapa_avaliativa` | ← `periodo_letivo`, → `avaliacao` |
| `avaliacao` | ← `etapa_avaliativa`, ← `disciplina`, ← `turma`, → `nota` |
| `situacao_matricula` | → `matricula` |
| `matricula` | ← `pessoa`, ← `turma`, ← `situacao_matricula`, → `contrato`, → `nota` |
| `nota` | ← `avaliacao`, ← `matricula` |
| `contrato` | ← `matricula`, → `responsavel_financeiro`, → `titulo` |
| `responsavel_financeiro` | ← `contrato`, ← `pessoa` |
| `titulo` | ← `contrato` |
| `tributacao_curso` | ← `curso` |
| `cronograma_aula` | ← `turma`, ← `disciplina`, ← `pessoa` (professor) |
| `coordenador` | ← `curso`, ← `pessoa` |

---

## 🎨 Identidade Visual

- **Logo e Favicon:** `public/images/logo.png` e `public/images/favicon.png` — injetados globalmente via `AdminPanelProvider.php`.
- **Cor primária:** Amber — configurável no `AdminPanelProvider`.
- **Dark Mode:** Suportado nativamente pelo Filament v5.

## 🔒 Segurança

- Autenticação via guard nativo do Laravel (`config/auth.php`).
- Autorização por recurso via **Filament Shield** (Spatie Permission).
- Proteção contra mass assignment via `$guarded = []` em cada Model.
- Senhas armazenadas com **bcrypt** (`password` cast `hashed`).
- Validação de campos críticos (CPF único, e-mail único) nos Schemas dos formulários.
