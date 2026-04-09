# Torre360 - Manual do Usuário

Bem-vindo ao **Torre360 - Sistema de Gestão Escolar**. Este manual foi criado para ajudar você a navegar e utilizar todos os recursos do sistema com eficiência e praticidade.

---

## 🔐 1. Acesso ao Sistema

1. Abra o navegador e acesse o endereço do sistema (ex: `http://localhost:8000/admin`).
2. Insira seu **E-mail** e **Senha** fornecidos pelo administrador.
3. Clique em **Entrar**. Você será direcionado ao Painel Principal (Dashboard).

> [!NOTE]
> Caso você não tenha acesso, solicite ao administrador que crie sua conta e associe o papel (role) correto ao seu perfil.

---

## 📌 2. Painel de Navegação

A barra lateral esquerda é dividida em grupos para facilitar o dia a dia:

| Grupo | O que você faz aqui |
|---|---|
| 🎯 **CRM** | Gestão de Interessados e Kanban de Matrícula |
| 👥 **Pessoas** | Cadastro unificado de Alunos, Responsáveis e Fornecedores |
| 🎓 **Acadêmico** | Cursos, Turmas, Avaliações, Notas e Frequência |
| 📝 **Secretaria** | Matrículas, Contratos e Gestão de Documentos |
| 💰 **Financeiro** | Faturas, Bancos, Conciliação, DRE e Plano de Contas |
| 🛠️ **Operações** | Ordens de Serviço e Manutenção Interna |
| 🌍 **Configurações** | Geografia (Países/Cidades), Configurações do Sistema e Segurança |

---

## 🎯 3. CRM — Gestão de Leads e Interessados

O módulo de CRM permite gerenciar o processo de captação de novos alunos antes mesmo da matrícula.

### 3.1 Kanban de Interessados
1. Vá em **CRM → Interessados**.
2. Utilize a visualização em **Cards/Kanban** para arrastar interessados entre as etapas (ex: *Novo Contato*, *Agendamento*, *Visita Realizada*, *Matrícula em Andamento*).
3. Clique em um card para ver o histórico de contatos e observações.

### 3.2 Registro de Histórico de Contato
1. Dentro do cadastro do Interessado, utilize a aba **Histórico de Contato**.
2. Registre cada ligação, e-mail ou visita, definindo o tipo de contato e o relato do que foi conversado.

---

## 👥 4. Cadastro Unificado de Pessoas

Uma **Pessoa** no sistema é a entidade central. Ela pode acumular múltiplos papéis (Aluno, Responsável Financeiro, Fornecedor).

### 4.1 Cadastro de Pessoa
1. Preencha os dados básicos (**CPF com máscara automática**, Nome, Data de Nascimento).
2. **Endereços:** Na aba de endereços, você pode vincular um ou mais endereços à pessoa (ex: Residencial, Comercial).
3. **Foto:** Use o editor integrado para ajustar a foto de perfil.

---

## 🎓 5. Acadêmico — Ensino e Avaliação

### 5.1 Lançamento de Notas
1. Vá em **Acadêmico → Avaliações**.
2. Localize a prova/trabalho e utilize a ação de **Lançar Notas**.
3. O sistema exibirá a lista de alunos matriculados na turma vinculada para preenchimento rápido.

### 5.2 Frequência Escolar
1. Em **Acadêmico → Frequência**, selecione o Cronograma de Aula do dia.
2. Marque as faltas ou presenças dos alunos. O padrão é "Presença".

### 5.3 Boletim do Aluno
1. Na visualização de **Matrículas**, use a ação **Boletim**.
2. O sistema gera uma tabela dinâmica porEtapa Avaliativa (Bimestre/Trimestre) mostrando as notas de cada disciplina e a média global.
3. Notas abaixo da média aparecem destacadas em vermelho.

---

## 📝 6. Secretaria e Documentação

### 6.1 Matrículas e Contratos
1. Ao realizar uma matrícula, o sistema permite a criação automática de um **Contrato**.
2. O contrato centraliza as obrigações financeiras e os responsáveis legais.

### 6.2 Gestão de Documentos
1. Cada Matrícula possui uma lista de documentos necessários (RG, CPF, Histórico Escolar).
2. Vá na aba **Documentos** da matrícula para fazer o upload dos arquivos.
3. O sistema permite validar se o documento foi recebido, se está pendente ou se foi recusado por algum motivo.

---

## 💰 7. Financeiro Avançado

### 7.1 Faturas e Itens
Em vez de títulos estáticos, o Torre360 trabalha com **Faturas**.
1. Uma fatura pode conter múltiplos itens (Mensalidade + Taxa de Material + Uniforme).
2. As faturas podem ser geradas em lote a partir de contratos.

### 7.2 Conciliação Bancária
1. Vá em **Financeiro → Conciliação Bancária**.
2. Faça o upload do arquivo **OFX** extraído do seu banco.
3. O sistema tentará identificar automaticamente a qual fatura ou fornecedor o lançamento pertence.
4. Para novos fornecedores detectados em débitos, o sistema abre um popup para cadastro rápido.

### 7.3 Relatório DRE (Demonstrativo de Resultados)
1. Acesse **Financeiro → Relatórios → DRE**.
2. Filtre pelo mês ou período desejado.
3. O sistema consolida todas as receitas (Faturas pagas) e despesas (Pagamentos a fornecedores) com base no **Plano de Contas**, mostrando o lucro ou prejuízo do período.

---

## 🛠️ 8. Operações — Ordens de Serviço (OS)

Utilizado para gerir a manutenção da infraestrutura da escola.
1. Crie uma **OS** descrevendo o problema (Ex: Ar condicionado quebrado).
2. Defina **Prioridade** e **Categoria**.
3. Adicione fotos do problema e anotações conforme o técnico realiza o serviço.

---

## 🆘 Dúvidas Frequentes

**Q: Como faço para um aluno aparecer no Boletim?**
> R: Ele precisa ter uma **Matrícula Ativa** em um **Período Letivo** que possua disciplinas e avaliações cadastradas.

**Q: Qual a diferença entre Fatura e Transação?**
> R: A **Fatura** é a intenção de cobrança/pagamento. A **Transação** é o movimento real de dinheiro na conta bancária (extrato). A conciliação une os dois.

**Q: Como altero as etapas avaliativas (ex: de Bimestre para Trimestre)?**
> R: Vá em **Configurações → Etapas Avaliativas** e defina as datas de início e fim. O sistema ajustará o cálculo do boletim automaticamente.

---

> **Torre360** — Gestão inteligente para instituições de ensino.
