# Torre360 - Manual do Usuário

Bem-vindo ao **Torre360 - Sistema de Gestão Escolar**. Este manual foi criado para ajudar você a navegar e utilizar todos os recursos do sistema com eficiência e praticidade.

---

## 🔐 1. Acesso ao Sistema

1. Abra o navegador e acesse o endereço do sistema (ex: `http://localhost:8000/admin`).
2. Insira seu **E-mail** e **Senha** fornecidos pelo administrador.
3. Clique em **Entrar**. Você será direcionado ao Painel Principal (Dashboard).

> **Atenção:** Caso você não tenha acesso, solicite ao administrador que crie sua conta e associe o papel (role) correto ao seu perfil.

---

## 📌 2. Painel de Navegação

A barra lateral esquerda é dividida em grupos para facilitar o dia a dia:

| Grupo | O que você faz aqui |
|---|---|
| 🌍 **Geografia** | Configuração de Países, Estados e Cidades |
| 📋 **Cadastros** | Pessoas, Unidades, Sexo, Cor/Raça e Perfis |
| 🎓 **Acadêmico** | Cursos, Turmas, Períodos, Avaliações e Notas |
| 📝 **Secretaria** | Matrículas, Contratos e Documentos |
| 💰 **Financeiro** | Responsáveis financeiros, Títulos e Tributações |
| ⚙️ **Configurações** | Usuários e Controle de Permissões (RBAC) |

> **Dica:** O sistema suporta **Modo Escuro**. Ative-o clicando no ícone ☀️/🌙 no canto superior direito da tela.

---

## 👥 3. Cadastros — O Ponto de Partida

Antes de criar matrículas, configure os dados básicos de Pessoas e estruturas.

### 3.1 Cadastrando uma Pessoa

1. Vá em **Cadastros → Pessoas** e clique em **+ Nova Pessoa**.
2. Preencha os dados obrigatórios:
   - **Nome** e **CPF** (o CPF precisa ser único no sistema).
   - **E-mail** e **Telefone**.
   - **Sexo** e **Cor/Raça** — os valores vêm de listas pré-definidas: *Feminino, Masculino, Não declarado* e *Branca, Preta, Parda, Amarela, Indígena, Não declarado*.
3. **Nacionalidade:** preenchida automaticamente como "Brasil". Se a Pessoa for estrangeira, troque o país.
4. **Naturalidade:** aparece automaticamente ao selecionar "Brasil" como Nacionalidade, listando apenas cidades brasileiras.
5. **Foto:** clique no campo Foto, selecione a imagem e use o editor para recortar no formato 3:4 (padrão para documentos).
6. Clique em **Salvar**.

> **Multi-perfil:** Uma Pessoa pode ser aluno, professor e responsável ao mesmo tempo. Os papéis são atribuídos na aba **Perfil** da pessoa.

### 3.2 Cadastrando uma Unidade

1. Vá em **Cadastros → Unidades** e clique em **+ Nova Unidade**.
2. Preencha o nome e vincule um Endereço (crie o endereço previamente se necessário).

---

## 🎓 4. Acadêmico — Estrutura de Ensino

Siga esta ordem ao configurar o sistema pela primeira vez:

### 4.1 Hierarquia Acadêmica

```
Período Letivo (ex: "1º Semestre 2025")
 ├── Dias Não Letivos (feriados/recessos)
 ├── Etapas Avaliativas (Bimestres/Trimestres)
 │    └── Avaliações (provas e trabalhos com nota máxima e peso)
 └── Turmas
      ├── Cronograma de Aulas
      └── Matrículas de Alunos
```

### 4.2 Configuração Passo a Passo

1. **Área de Conhecimento** → Crie as grandes áreas (Linguagens, Exatas, Humanas...).
2. **Cursos** → Ensino Fundamental, Ensino Médio, etc. Vincule a uma Unidade.
3. **Séries** → Vincule ao curso (1º Ano, 2º Ano...).
4. **Disciplinas** → Vinculadas a uma Área de Conhecimento.
5. **Período Letivo** → Crie o ano/semestre letivo com data de início e fim.
6. **Turmas** → Vincule a uma Série, Turno e Período Letivo.
7. **Etapas Avaliativas** → Bimestres/trimestres dentro de um Período Letivo.
8. **Avaliações** → Provas e trabalhos — defina Disciplina, Turma, Etapa, data prevista, nota máxima e peso.
9. **Cronograma de Aulas** → Horários semanais de cada Turma com o Professor responsável.

### 4.3 Dias Não Letivos

1. Vá em **Acadêmico → Dias Não Letivos**.
2. Vincule ao **Período Letivo** e informe a data e descrição do feriado ou recesso.

---

## 📝 5. Secretaria — Matrículas

### 5.1 Criando uma Matrícula

1. Vá em **Secretaria → Matrículas** e clique em **+ Nova Matrícula**.
2. **Selecione o Aluno:** O campo exibe a lista de Pessoas cadastradas no formato **"Nome - CPF"**. Você pode buscar pelo nome ou CPF.
   - Se o aluno **ainda não foi cadastrado**, clique no botão **＋** ao lado do campo. Um formulário rápido abrirá dentro da própria janela para cadastrar nome, CPF, e-mail, sexo e cor/raça sem sair da tela.
3. **Selecione a Turma**.
4. **Situação da Matrícula:** defina como *Ativo*, *Trancado*, *Evadido*, etc.
5. **Data de Matrícula.**
6. Clique em **Salvar**.

### 5.2 Contratos

1. Com a Matrícula criada, vá em **Secretaria → Contratos** e crie um contrato vinculado à matrícula.
2. O Contrato é o vínculo formal e jurídico entre o aluno e a instituição para o período letivo.

---

## 💰 6. Financeiro

### 6.1 Responsável Financeiro

1. Vá em **Financeiro → Responsáveis Financeiros**.
2. Vincule uma **Pessoa** e um **Contrato** — essa pessoa será a responsável pelos pagamentos.

### 6.2 Títulos (Cobranças)

1. Acesse **Financeiro → Títulos**.
2. Crie os títulos de cobrança vinculados ao contrato (parcelas mensais, taxas avulsas, etc.).

### 6.3 Tributação de Cursos

Registre a natureza fiscal de cada curso para fins de emissão de NFS-e junto à Secretaria de Finanças municipal.

---

## ⚙️ 7. Configurações — Usuários e Permissões

### 7.1 Criando um Usuário

1. Vá em **Configurações → Usuários** e clique em **+ Novo Usuário**.
2. Preencha **Nome**, **E-mail** e **Senha** (mínimo 8 caracteres com confirmação).
3. **Papéis (Roles):** selecione um ou mais papéis de acesso para o usuário.
4. **Pessoa Vinculada (opcional):** vincule o usuário a uma Pessoa já cadastrada no sistema (exibido como "Nome - CPF").
5. Clique em **Salvar**.

### 7.2 Papéis de Acesso (RBAC)

O sistema usa um controle de permissões baseado em papéis. Cada papel define quais telas e ações o usuário pode executar:

| Papel | Nível de Acesso |
|---|---|
| `super_admin` | Acesso irrestrito total |
| `admin` | Administrador — acesso geral |
| `secretaria` | Matrículas, alunos e contratos |
| `professor` | Cronograma e avaliações |
| `coordenador` | Cursos e turmas |
| `responsavel` | Área financeira |
| `aluno` | Perfil básico (uso futuro) |

### 7.3 Gestor de Permissões (Shield)

1. Vá em **Configurações → Roles** (gerenciado pelo Filament Shield).
2. Clique em um papel para editar quais recursos ele pode visualizar, criar, editar ou excluir.
3. As permissões são geradas automaticamente para cada recurso do sistema (view, create, update, delete).

---

## 🆘 Dúvidas Frequentes

**Q: O campo "Aluno" não encontra a pessoa que eu quero. O que fazer?**
> A busca funciona tanto pelo **nome** quanto pelo **CPF**. Tente digitar o CPF do aluno. Se a pessoa não existir, clique em **＋** para cadastrá-la rapidamente.

**Q: Ao salvar, aparece um erro de validação. Por que?**
> Campos marcados com **\*** são obrigatórios. Verifique todos e tente novamente. Erros de CPF duplicado indicam que a pessoa já está cadastrada — busque pelo CPF antes de criar.

**Q: Não consigo acessar determinada tela. Por quê?**
> Seu usuário pode não ter o papel necessário. Fale com o administrador do sistema para verificar suas permissões em **Configurações → Usuários**.

**Q: Como altero minha senha?**
> O administrador pode alterar a sua senha em **Configurações → Usuários → Editar**. Por segurança, deixe o campo vazio caso não queira alterá-la.

---

> **Torre360** — Desenvolvido para simplificar a gestão escolar com tecnologia de ponta. Para suporte técnico, acione o responsável pelo sistema da sua instituição.
