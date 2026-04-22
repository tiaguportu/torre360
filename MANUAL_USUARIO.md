# Torre360 - Manual do Usuário

Bem-vindo ao **Torre360 - Sistema de Gestão Escolar**. Este manual foi criado para ajudar você a navegar e utilizar todos os recursos do sistema com eficiência e praticidade.

---

## 🔐 1. Acesso ao Sistema

1. Abra o navegador e acesse o endereço do sistema (ex: `http://localhost:8000/admin`).
2. Insira seu **E-mail** e **Senha** fornecidos pelo administrador.
3. Clique em **Entrar**. Você será direcionado ao Painel Principal (Dashboard).

> [!NOTE]
> O acesso ao sistema, o registro de novos usuários e a solicitação de recuperação de senha são protegidos pelo **Google reCAPTCHA v3**. O sistema analisa o comportamento de navegação de forma invisível para garantir a segurança contra acessos automatizados e ataques de robôs.
> Caso você não tenha acesso, solicite ao administrador que crie sua conta e associe o papel (role) correto ao seu perfil.

---

## 📌 2. Painel de Navegação

A barra lateral esquerda é dividida em grupos para facilitar o dia a dia:

| Grupo | O que você faz aqui |
|---|---|
| 🎯 **CRM / Comercial** | Gestão de Interessados e Kanban de Prospecção |
| 🎓 **Acadêmico** | Matrículas, Alunos, Turmas, Cursos e Séries |
| ✅ **Avaliações** | Lançamento de Notas, Avaliações e Etapas Acadêmicas |
| 📅 **Calendário e Horários** | Cronograma de Aulas, Horários e Dias não letivos |
| 💰 **Financeiro** | Faturas, Contratos, Bancos, Conciliação e Plano de Contas |
| 👥 **Pessoas** | Cadastro de Pessoas, Responsáveis e Coordenadores |
| 📄 **Documentos** | Gestão de Documentos enviários e Tipos de Documentos |
| 📖 **Currículo (BNCC)** | Áreas de Conhecimento, Campos de Experiência e Habilidades |
| 🛠️ **Operacional** | Gestão de Ordens de Serviço (Manutenção) |
| 📍 **Localização e Cadastros** | Cidades, Estados, Endereços e Dados Base |
| 🛡️ **Sistema e Segurança** | Usuários, Permissões (Shield), Logs e Configurações Gerais |

---

## 🎯 3. CRM — Gestão de Leads e Interessados

O módulo de CRM permite gerenciar o processo de captação de novos alunos antes mesmo da matrícula.

### 3.1 Kanban de Interessados (Estilo Trello)
1. Vá em **CRM → Interessados**.
2. Utilize a visualização em **Funil de Vendas (CRM)**:
   - **Interface:** O layout é inspirado no Trello, com colunas coloridas que facilitam a distinção visual entre as etapas do funil (ex: *Novo Contato*, *Agendamento*, *Matrícula*).
   - **Drag & Drop:** Arraste e solte os cards entre as colunas para atualizar o status do interessado em tempo real.
   - **Indicadores Visuais:**
     - **Cores nas Colunas:** Cada etapa possui uma barra de destaque com a cor configurada (Ex: Azul para informativo, Vermelho para crítico).
     - **Contagem:** O topo de cada coluna mostra o número total de interessados naquela etapa.
     - **Alertas de Data:** 
      - As datas de "Próximo Contato" mudam de cor automaticamente no card: **Vermelho** se estiverem atrasadas (ou se a data agendada for anterior à data atual) e **Amarelo** se forem para hoje.
      - **Acompanhamento (Follow-up):** Se a data do "Próximo Contato" estiver no passado (atraso) ou for anterior à data do último contato realizado (agendamento desatualizado), o card ganhará uma borda e fundo vermelhos de erro no Kanban, e um botão de alerta em destaque aparecerá na página de edição. O card também exibe no título (tooltip) o resumo do último contato realizado para consulta rápida.
   - **Acesso Rápido:** Clique no ícone de lápis no card para editar as informações completas ou ver o histórico de contatos.
3. Clique em um card para ver o histórico de contatos e observações.

### 3.2 Registro de Histórico de Contato
1. Dentro do cadastro do Interessado, utilize a aba **Histórico de Contato**.
2. Registre cada ligação, e-mail ou visita, definindo o tipo de contato e o relato do que foi conversado.

### 3.3 Alertas e Notificações em Tempo Real
1. **Notificação no Sininho:** Sempre que um novo interessado preenche o formulário no site, todos os usuários administrativos (exceto perfis de Professor ou Aluno/Responsável) recebem um alerta instantâneo no sininho do sistema.
2. **Badge na Barra Lateral:** O menu **CRM → Interessados / Leads** exibe um círculo verde dinâmico com a quantidade total de novos interessados que ainda não foram atendidos (status "Novo").
3. **Follow-up Pulsante:** Quando um interessado precisa de contato urgente (atraso no agendamento), um botão vermelho pulsante aparece no topo da tela de edição para que você possa alertar o consultor responsável por e-mail e sistema.

### 3.4 Registro de Vínculo
O sistema permite registrar o grau de parentesco do interessado principal com cada aluno vinculado:
- **Opções:** Pai, Mãe, Parente ou Tutor.
- **Flexibilidade:** Ao cadastrar múltiplos alunos para o mesmo interessado, você deve definir um vínculo específico para cada uma das crianças.
- **Onde Ver:** Os vínculos são exibidos dentro de cada registro na aba de **Dependentes** do cadastro do interessado.

---

## 📍 13. Gestão de Unidades e Canais Digitais

### 13.1 Redes Sociais e Contato
No cadastro de cada unidade (**Localização e Cadastros → Unidades**), é possível configurar canais de comunicação exclusivos:
- **WhatsApp, Instagram, Facebook e YouTube:** Links diretos que serão utilizados na personalização do site e nos e-mails automáticos.
- **Impacto:** Se uma unidade não possui redes sociais cadastradas, o sistema omite automaticamente essas informações nas comunicações para manter o layout limpo.

### 13.2 Representantes Legais
4. **Confirmação:** O sistema solicitará sua confirmação antes de enviar o alerta.

---

## 👥 4. Cadastro Unificado de Pessoas

Uma **Pessoa** no sistema é a entidade central. Ela pode acumular múltiplos papéis (Aluno, Responsável Financeiro, Fornecedor).

### 4.1 Cadastro de Pessoa
1. Preencha os dados básicos (**CPF com máscara automática**, Nome, Data de Nascimento, **Identidade (RG)**, **Profissão** e **Estado Civil**).
2. **Edição em Lote:** Na listagem de pessoas, você pode selecionar múltiplos registros e utilizar a ação **Editar em Lote** para atualizar rapidamente o Sexo, Raça/Cor, Nacionalidade, Estado Civil, Profissão ou Identidade de várias pessoas ao mesmo tempo. Os campos de **Sexo** e **Cor / Raça** agora utilizam indicadores visuais (badges) coloridos para facilitar a identificação rápida na tabela.
3. **Endereços e Automação via CEP:** Na aba de endereços, você pode vincular um ou mais endereços à pessoa.
   - **Agilidade no Preenchimento:** Comece digitando o **CEP**. Ao sair do campo (ou pressionar TAB), o sistema consulta automaticamente a base do **ViaCEP** e preenche para você o **Logradouro**, **Bairro** e a **Cidade/Estado**.
   - **Tipos de Endereço:** Escolha o **Tipo** (ex: Residencial ou Comercial) e complete com o **Número** e **Complemento** (apartamento, bloco, etc).
4. **Foto:** Use o editor integrado para ajustar a foto de perfil.

### 4.2 Segurança e Privacidade das Fotos
1. As fotos de perfil das pessoas são armazenadas de forma segura em um **disco privado**.
2. O sistema garante que apenas usuários autenticados possam visualizar essas imagens, protegendo a privacidade de alunos e colaboradores.
3. Caso realize o upload de uma nova foto, o sistema processará a imagem e a disponibilizará automaticamente para visualização interna segura.

### 4.3 Visibilidade Restrita
Para garantir a privacidade e segurança dos dados, a visualização da lista de pessoas é filtrada conforme o papel do usuário:
- **Responsáveis:** Visualizam seus próprios dados acadêmicos/cadastrais e de todos os seus dependentes (filhos/alunos) vinculados legal ou financeiramente (via contrato).
- **Alunos:** Visualizam seus próprios dados e os dados de seus responsáveis legais e financeiros.
- **Professores:** Visualizam seus próprios dados e os dados de pessoas vinculadas aos seus usuários.
- **Administradores/Secretaria:** Possuem visibilidade total de todas as pessoas cadastradas.

---

## 🎓 5. Acadêmico — Ensino e Avaliação

### 5.1 Filtros e Gestão de Avaliações
1. Vá em **Avaliações → Avaliações**.
2. **Filtros Avançados:** Utilize a barra de filtros para localizar registros com precisão:
   - **Múltipla Seleção:** Os filtros de Categoria, Turma, Disciplina, Etapa e Professor permitem selecionar **várias opções simultaneamente**.
   - **Filtro de Período:** Use o filtro de **Data Prevista** para definir um intervalo (Data Inicial e Final) e visualizar apenas as avaliações agendadas para aquele período.
   - **Pendência de Lançamento:** Localize rapidamente provas ou trabalhos onde ainda faltam alunos sem nota lançada.
3. **Edição em Lote:** Selecione uma ou mais avaliações na tabela e utilize a ação **Editar em Lote** para atualizar de uma só vez a Categoria, a Etapa, a Data Prevista ou a Nota Máxima de todos os registros selecionados.
4. Localize a prova/trabalho e utilize a ação de **Lançar Notas**.
5. **Padronização de Nomes:** Para facilitar a busca e identificação, as avaliações no sistema seguem o padrão de nome: `Categoria Avaliação - Turma - Disciplina - Etapa Avaliativa`.
6. O sistema exibirá a lista de alunos matriculados na turma vinculada para preenchimento rápido.

### 5.2 Frequência Escolar
1. Em **Acadêmico → Frequência**, selecione o Cronograma de Aula do dia.
2. Marque as faltas ou presenças dos alunos. O padrão é "Presença".

### 5.3 Boletim do Aluno
1. Na visualização de **Matrículas**, use a ação **Boletim**.
2. **Impressão de Boletim:** Na visualização do boletim de uma matrícula, agora é possível exportar o documento em PDF. Você pode escolher imprimir uma etapa específica (ex: 1º Bimestre) ou todas as etapas que já possuem notas registradas.
3. O sistema gera uma tabela dinâmica por Etapa Avaliativa (Bimestre/Trimestre) mostrando as notas de cada disciplina e a média global.
4. Notas abaixo da média aparecem destacadas em vermelho.
5. **Frequência:** Ao lado das médias, o boletim exibe o percentual de presenças do aluno nas aulas daquela disciplina dentro do período da etapa avaliativa.
6. **Edição de Notas:** Caso possua a permissão necessária, você visualizará o botão **Editar Notas** no topo da página do boletim. Esta tela permite o preenchimento rápido de todas as notas da etapa em um layout idêntico ao de consulta.

### 5.4 Gerenciamento de Notas
1. No menu **Avaliações → Notas**, é possível visualizar o histórico completo de notas lançadas.
2. Para facilitar a identificação, a coluna **Matrícula** segue o padrão: `Turma - Período Escolar - Nome do Aluno`.
3. A busca nesta tela permite localizar registros pesquisando por qualquer uma dessas três informações.

### 5.5 Avaliação de Habilidades (BNCC - Educação Infantil)
O sistema permite avaliar competências e habilidades específicas organizadas por **Campos de Experiência**, em total conformidade com a BNCC para a Educação Infantil.
1. **Campos de Experiência:** Vá em **Currículo (BNCC) → Campos de Experiência**. Aqui você define as categorias principais (ex: "O eu, o outro e o nós", "Corpo, gestos e movimentos"). Cada campo possui uma descrição pedagógica que orienta os professores.
2. **Cadastro de Habilidades:** Vá em **Currículo (BNCC) → Habilidades**. Cada habilidade (ex: EI01EO01) deve ser vinculada a um Campo de Experiência. Você pode cadastrar o código, o nome e a descrição da habilidade.
3. **Gerenciamento de Grade na Turma:** No cadastro de **Turmas**, você deve configurar o que será avaliado:
   - **Tipo de Avaliação:** Escolha entre `Notas` (Ensino Fundamental/Médio), `Habilidades` (Infantil) ou `Híbrido`.
   - **Disciplinas:** Na aba **Disciplinas**, anexe as matérias que a turma possui. Isso habilita o lançamento de notas para essas matérias.
   - **Habilidades:** Na aba **Habilidades**, anexe as competências que serão avaliadas. Isso habilita o lançamento de conceitos pedagógicos.
4. **Lançamento de Avaliações por Notas:**
   - Vá em **Avaliações → Avaliações por Disciplina**.
   - Crie uma nova avaliação selecionando a **Turma**, a **Disciplina** (filtrada pelas que você anexou à turma) e a **Etapa**.
   - Utilize o repetidor de notas para lançar os valores numéricos de todos os alunos de uma vez.
5. **Lançamento de Avaliações por Habilidades:**
   - Vá em **Avaliações → Avaliações por Habilidades**.
   - Selecione a **Turma**, a **Habilidade** e o **Aluno**.
   - Escolha o **Conceito** pedagógico baseado na escala: 
     - **Realiza bem** (Verde)
     - **Em desenvolvimento** (Amarelo)
     - **Não realiza** (Vermelho)
     - **Não observado** (Cinza)
   - Adicione observações pedagógicas detalhadas se necessário.

### 5.6 Configuração de Disciplinas e Ordenação no Boletim
1. Vá em **Acadêmico → Disciplinas**.
2. No cadastro da disciplina, utilize o campo **Ordem no Boletim**.
3. **Funcionamento:** O sistema utiliza este número inteiro para ordenar as disciplinas de cima para baixo na visualização do boletim. Disciplinas com números menores (ex: 1, 2, 3) aparecem primeiro.
4. Caso duas disciplinas tenham o mesmo número de ordem, elas serão exibidas por ordem alfabética de nome.

### 5.7 Situações de Matrícula (Padronização)
As situações de matrícula no Torre360 são fixas e padronizadas para garantir a consistência dos relatórios. Cada estado possui uma cor e ícone específicos na listagem:
- **Ativa (Verde):** Aluno regularmente matriculado e frequentando.
- **Reserva (Cinza):** Vaga reservada (pré-matrícula) aguardando efetivação.
- **Pendente (Amarelo):** Matrícula em processo, geralmente aguardando documentação ou pagamento.
- **Trancada (Laranja):** Matrícula suspensa temporariamente a pedido.
- **Cancelada (Vermelho):** Vínculo encerrado definitivamente.
- **Concluída (Azul):** Aluno finalizou o curso/série com sucesso.
- **Evasão (Cinza):** Aluno abandonou os estudos sem formalizar a saída.

### 5.6 Categorias de Avaliação e Ordenação das Colunas no Boletim
1. Vá em **Avaliações → Categorias de Avaliação**.
2. No cadastro de cada categoria (ex: Prova 1, Trabalho, Simulado), utilize o campo **Ordem no Boletim**.
3. **Funcionamento:** Este campo define a ordem horizontal das colunas no boletim dentro de cada etapa avaliativa. Avaliações pertencentes a categorias com ordens menores aparecerão mais à esquerda na tabela do boletim.
4. **Substituição de Notas (Recuperação):** O sistema permite que uma categoria (ex: "Recuperação Bimestral") substitua **múltiplas categorias** originais (ex: "Prova 1" e "Trabalho 1").
   - No cálculo do boletim, o sistema identificará qual das categorias vinculadas possui a **menor nota** e a substituirá pela nota da categoria substitutiva (caso esta seja maior).
   - Visualmente, a nota que foi substituída aparecerá riscada no boletim para facilitar a conferência pedagógica.

---

## 📝 6. Secretaria e Documentação

### 6.1 Matrículas e Contratos
1. Ao realizar uma matrícula, o sistema permite a criação automática de um **Contrato**.
2. O contrato centraliza as obrigações financeiras e os responsáveis legais.
3. No cadastro do contrato, preencha o **Valor Total** e a **Quantidade de Parcelas** — essas informações aparecem no texto do contrato gerado.
4. **Seleção de Alunos:** O campo de seleção de aluno permite buscar qualquer pessoa cadastrada. Para facilitar o cadastro de crianças, o sistema exibe tanto pessoas com o perfil de "aluno" quanto pessoas sem conta de usuário vinculada (sem perfil).
5. **Busca Avançada:** Você pode buscar alunos pelo **Nome** ou **CPF** diretamente no campo de seleção.
6. **Responsáveis Financeiros:** Qualquer pessoa cadastrada pode ser selecionada como Responsável Financeiro, independentemente de possuir ou não o papel (role) de "responsavel" no sistema. Isso permite que pais que já possuem outros acessos (como funcionários/professores) ou pessoas sem acesso ao painel sejam vinculadas financeiramente ao contrato.
7. **Visibilidade Restrita:** Para garantir a privacidade, a visualização das matrículas é filtrada conforme o papel do usuário:
   - **Administradores/Secretaria:** Visualizam todas as matrículas do sistema.
   - **Responsáveis:** Visualizam apenas as matrículas onde são os responsáveis financeiros (no contrato) OU onde possuem vínculo legal direto com o aluno (vínculo pai/mãe registrado no sistema).

### 6.2 Gestão de Documentos
1. Cada Matrícula possui uma lista de documentos necessários (RG, CPF, Histórico Escolar).
2. Vá na aba **Documentos** da matrícula para fazer o upload dos arquivos.
3. O sistema utiliza uma **Máquina de Estados** para gerir a situação do documento:
   - **Pendente:** Documento enviado mas ainda não revisado.
   - **Em Análise:** Documento em processo de conferência pela secretaria.
   - **Aprovado:** Documento validado e aceito.
   - **Rejeitado:** Documento com problemas (ilegível, errado, etc).
4. As transições de estado são controladas; por exemplo, um documento *Aprovado* não pode voltar para *Pendente* sem passar por uma revisão, garantindo a integridade do processo.

### 6.4 Visualização e Prévia de Documentos
1. Ao acessar a edição de um documento, o sistema exibe automaticamente uma **Prévia do Documento** (imagem ou PDF) logo abaixo do campo de upload.
2. Esta funcionalidade permite conferir o conteúdo do arquivo rapidamente sem a necessidade de downloads manuais.
3. Para PDFs, o navegador utiliza seu visualizador nativo integrado à página.

> [!NOTE]
> A prévia de documentos é protegida por segurança. Apenas usuários autenticados com as devidas permissões podem visualizar os arquivos, garantindo a privacidade dos dados dos alunos.

6.3 Assinatura Digital (Assinafy)
O sistema é integrado à plataforma **Assinafy** para assinatura digital de contratos.
1. Na lista de contratos, clique na ação **Assinar Contrato** para o documento pendente.
2. Você será direcionado para uma página de visualização. Clique em **Iniciar Assinatura Digital** para ser enviado ao portal do Assinafy.
3. **Múltiplos Signatários:** Se o contrato possuir mais de um Responsável Financeiro vinculado (com usuário cadastrado no sistema), **todos** receberão um convite de assinatura por e-mail automaticamente.
4. O texto do contrato exibe automaticamente:
   - **Todos os alunos** do contrato (nome e CPF).
   - **Todos os responsáveis financeiros** como CONTRATANTE. Seu endereço residencial será exibido prioritariamente; caso não possua, o sistema utilizará o primeiro endereço comercial vinculado.
   - O **valor total** e o **número de parcelas** com valor estimado por parcela.
5. Quando o contrato for assinado por todos os responsáveis, o status no sistema mudará automaticamente para **Assinado**.
6. Para baixar o documento com as assinaturas digitais, acesse a visualização do contrato e clique em **Baixar Contrato Assinado**.

### 6.4 Templates Dinâmicos de Contrato
O sistema permite a criação de modelos de contrato customizáveis com substituição automática de informações (macros).
1. Vá em **Financeiro → Templates de Contrato**.
2. **Criação:** Você pode criar múltiplos modelos (ex: Contrato Infantil, Contrato Fundamental, Aditivo).
3. **Editor Rico:** Utilize o editor estilo Office para formatar o texto, inserir tabelas, imagens e logotipos.
4. **Macros:** Utilize os códigos abaixo entre chaves duplas para que o sistema preencha os dados reais no momento da geração:
   - `{{CONTRATO_ID}}`: Número identificador do contrato.
   - `{{CONTRATO_VALOR}}`: Valor total do contrato formatado (R$).
   - `{{CONTRATO_DATA}}`: Cidade e Data atual por extenso.
   - `{{UNIDADE_NOME}}` e `{{UNIDADE_CNPJ}}`: Dados da unidade escolar.
   - `{{ALUNOS_TABELA}}`: Gera automaticamente uma tabela com os alunos, turmas e séries/anos.
   - `{{RESPONSAVEIS_INFO}}`: Gera o texto qualificando os responsáveis financeiros.
   - `{{FATURAS_TABELA}}`: Gera uma tabela com o cronograma de parcelas e vencimentos.
5. **Template Padrão:** Marque a opção "Template Padrão" em um dos modelos para que ele seja selecionado automaticamente ao criar novos contratos.
6. **Seleção no Contrato:** No formulário de **Contratos**, você pode escolher qual template deseja utilizar para aquele contrato específico.

---

## 💰 7. Financeiro Avançado

### 7.1 Faturas e Itens
Em vez de títulos estáticos, o Torre360 trabalha com **Faturas**.
1. Uma fatura pode conter múltiplos itens (Mensalidade + Taxa de Material + Uniforme).
2. As faturas podem ser geradas em lote a partir de contratos.

### 7.1.1 Gerar Faturas Automaticamente
Na tela de edição de um contrato (`Financeiro → Contratos → Editar`), utilize o botão **Gerar Faturas Automaticamente** para criar o parcelamento do contrato de forma rápida.

> [!WARNING]
> Ao acionar este botão, **todas as faturas existentes** do contrato serão removidas e substituídas pelas novas. Certifique-se de que o contrato possui uma **Data de Aceite** preenchida antes de prosseguir.

**Campos do formulário:**

| Campo | Descrição |
|---|---|
| **Quantidade de Parcelas** | Número de parcelas em que o valor restante (valor total menos entrada) será dividido. |
| **Dia de Vencimento** | Dia do mês (entre 1 e 28) em que cada parcela vencerá. |
| **Valor de Entrada** | Valor a ser cobrado como entrada. Informe `0` caso não haja entrada. |

**Lógica de geração:**

- **Fatura de Entrada** (se valor > 0): Criada com vencimento no dia escolhido do próprio mês da Data de Aceite.
- **Parcelas mensais:** A **1ª parcela** vence no dia escolhido do **mês seguinte** à Data de Aceite; as demais são distribuídas mensalmente a partir daí.
- **Valor de cada parcela:** `(Valor Total − Valor de Entrada) ÷ Quantidade de Parcelas`.
- O campo **Valor por Parcela (prévia)** é atualizado em tempo real para que você confirme o valor antes de gerar.



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

## 🛡️ 9. Controle de Acesso e Auditoria
Para garantir a segurança e conformidade, o Torre360 utiliza o sistema **Filament Shield** para gestão de permissões baseada em papéis (Roles).

### 9.1 Permissões Configuráveis
Os administradores podem definir precisamente quem pode ver, criar, editar ou excluir registros em módulos críticos através dos **Papéis e Permissões (Shield)**:
- **Pessoas:** Possibilidade de vincular ou desvincular endereços no cadastro.
- **Financeiro:** Relatório DRE, Transações Bancárias, Cadastro de Fornecedores, Centros de Custo, Plano de Contas, Bancos e **Templates de Contrato**.
- **Secretaria:** Matrículas, Documentos sensíveis e **Edição de Notas de Boletim** (permissão `boletim_editar_matricula`). Além disso, usuários com este perfil possuem visibilidade total de todas as matrículas cadastradas no sistema.
- **CRM:** Gestão de leads e histórico de contatos.
- **Acadêmico:** **Questionários**, **Respostas de Questionários**, **Avaliações de Habilidades** (BNCC), **Campos de Experiência** e **Habilidades**.


### 9.2 Auditoria de Ações
O sistema registra automaticamente ações críticas:
- **Módulo de Matrícula:** O sistema loga acessos à lista de matrículas e à tela de documentos.
- **Gestão de Documentos:** Uploads, substituições e exclusões de arquivos são auditados com identificação do usuário e data/hora.
- **Financeiro:** Alterações em transações e planos de contas são rastreadas para evitar inconsistências.
- **Notificações:** Tentativas de envio de mensagens Push (Firebase/FCM) e suas respectivas respostas do servidor são registradas no histórico da matrícula para depuração e acompanhamento técnico.

---

## 📱 10. Aplicativo Mobile (Android)

O **Torre360** possui um aplicativo nativo para Android que facilita o acesso rápido ao painel administrativo.

### 10.1 Instalação e Acesso
1. Obtenha o arquivo de instalação (APK) com o administrador do sistema.
2. Após instalar, abra o aplicativo **Torre 360**.
3. O aplicativo já vem pré-configurado para acessar o endereço: `https://torre360.escolatorredemarfim.com.br/admin`.
4. Entre com suas credenciais normalmente.

### 10.2 Diferenciais
- **Acesso Direto:** Não precisa digitar a URL no navegador todas as vezes.
- **Biometria (Opcional):** Dependendo da versão, permitesuporte a login rápido.
- **Navegação Fluida:** Otimizado para telas menores, mantendo todas as funcionalidades do painel web.
- **Tabelas Inteligentes:** Todas as tabelas do sistema se adaptam automaticamente a telas de celulares, transformando-se em blocos verticais (cards) para facilitar a leitura e interação em dispositivos móveis.
- **Notificações Push:** O aplicativo suporta notificações push em tempo real. Ao clicar em um aviso, como "Documentos Pendentes", o aplicativo abre automaticamente na página correta para que você possa regularizar a situação imediatamente.

---

## 🔔 11. Notificações Unificadas (Painel, E-mail e Push)

O Torre360 possui um sistema central de notificações em tempo real que garante que você nunca perca uma ação importante. Dependendo da configuração, os alertas chegam por três canais simultâneos:

1.  **Painel Administrativo (Sininho):** Alertas instantâneos no topo da tela com som e indicadores visuais.
2.  **E-mail Acadêmico:** Mensagens detalhadas enviadas para o e-mail cadastrado no seu perfil de usuário.
3.  **Notificações Push (Celular/Web):** Alertas diretos no seu dispositivo móvel ou navegador, permitindo acesso rápido à ação necessária mesmo com o sistema fechado.

### 11.1 Como Funciona
- Sempre que houver uma ação que necessite sua atenção (ex: documentos pendentes em uma matrícula), um indicador numérico aparecerá sobre o sininho.
- Clique no sininho para visualizar a lista de notificações recentes.
- Cada notificação possui um botão de ação rápida (ex: **Ver Documentos** ou **Lançar Frequência**) que leva você diretamente à tela necessária.

### 11.2 Principais Notificações
- **Documentos Pendentes:** Disparada automaticamente quando a secretaria identifica que faltam documentos obrigatórios ou que algum documento enviado foi recusado. Chega por e-mail e push para os alunos e seus responsáveis financeiros.
- **Lançamento de Frequência Pendente:** Alerta enviado aos professores quando uma aula em seu cronograma ainda não teve a frequência lançada. Agora também disponível via Push e Sininho.
- **Auditoria de Documentos (ADM):** Usuários com papel de 'super_admin' ou 'secretaria' recebem notificações sempre que um novo documento é inserido ou removido.
- **Avisos do Sistema:** Notificações genéricas enviadas pela administração sobre manutenções, comunicados e atualizações.

### 11.3 Configuração de Push
Para receber notificações Push no seu celular:
1. Acesse o sistema através do aplicativo oficial **Torre 360** para Android.
2. Ao fazer o primeiro login, o sistema registrará seu dispositivo automaticamente.
3. Certifique-se de que a permissão de notificações está ativada nas configurações do seu celular.

---

## 📅 12. Calendário e Cronograma de Aulas

O módulo de Cronograma permite a visualização e gestão das aulas planejadas para cada turma.

### 12.1 Filtro por Período
1. Vá em **Calendário e Horários → Cronogramas de Aulas**.
2. No menu de filtros (ícone de funil), localize o filtro **Período**.
3. Defina uma **Data Início** e/ou uma **Data Fim**.
4. O sistema filtrará automaticamente todas as aulas cuja data esteja compreendida entre o intervalo selecionado, facilitando o planejamento semanal ou mensal.

### 12.2 Visibilidade para Responsáveis
1. Usuários com o papel de **Responsável** possuem acesso restrito ao módulo de cronogramas.
2. Eles visualizam apenas as aulas das turmas onde seus alunos (dependentes) possuem matrícula ativa ou contrato vinculado sob sua responsabilidade financeira.
3. Isso garante a privacidade das informações e foca apenas nos horários de interesse da família.

---

## 📍 13. Gestão de Unidades e Representantes Legais

As **Unidades** representam os locais físicos da instituição.

### 13.1 Representantes Legais da Unidade
1. Vá em **Localização e Cadastros → Unidades**.
2. Na edição de uma Unidade, utilize a aba/relação de **Representantes Legais**.
3. Aqui você pode vincular pessoas cadastradas no sistema que respondem legalmente por aquela unidade específica.
4. Esta informação é vital para emissão de documentos oficiais e contratos que exigem a identificação da autoridade local.

---

## 🛠️ 14. Supervisor de Fila (Queue)

Para garantir que notificações e processos em segundo plano funcionem corretamente, o sistema possui um supervisor no Dashboard principal.

### 14.1 Monitoramento de Status
- **Worker Ativo (Verde):** Indica que a fila foi processada recentemente (nos últimos 5 minutos).
- **Worker Parado (Vermelho):** Indica que não houve atividade de fila ultimamente. Se houver "Jobs Pendentes", as notificações do sininho e e-mails podem estar atrasados.

### 14.2 Ações Manuais
- **Processar Fila Agora:** Caso o worker automático esteja parado, você pode clicar neste botão para forçar o processamento de todos os itens pendentes na hora.
- **Limpar Fila:** Remove todos os itens pendentes (use apenas se houver erros persistentes ou acúmulo desnecessário).

---

## 📝 15. Editor de Texto (Estilo Office)

O sistema agora conta com um editor de texto avançado em campos de observações e conteúdos longos, oferecendo uma experiência similar a processadores de texto como o **LibreOffice** ou **Word**.

### 15.1 Recursos Disponíveis
- **Barra de Menus:** No topo do editor, você encontra menus familiares (Arquivo, Editar, Inserir, Formatar, Tabela, Ferramentas).
- **Formatação Completa:** Alteração de fontes (ex: Arial, Tahoma), tamanhos de letra, cores de texto e de fundo.
- **Tabelas:** Inserção e edição detalhada de tabelas, bordas e células.
- **Mídia:** Suporte para inserção de links, âncoras e imagens.
- **Visualização:** Opções de tela cheia (Fullscreen) e pré-visualização do conteúdo.

### 15.2 Dicas de Uso
- Para liberar mais espaço, utilize o ícone de **Tela Cheia** na barra de ferramentas.
- O editor salva o conteúdo automaticamente no formulário do sistema ao clicar em "Salvar" ou "Criar".

---

## 📊 16. Questionários e Avaliação Institucional

O módulo de **Questionários** permite criar formulários personalizados para coletar feedbacks de alunos, professores e colaboradores, funcionando de forma similar ao *Google Forms*, mas integrado ao ecossistema da escola.

### 16.1 Criando um Questionário
1. Vá em **Acadêmico → Questionários**.
2. **Geral:** Defina o título, descrição e o período em que o questionário ficará disponível para preenchimento.
3. **Privacidade:** Marque a opção **Respostas Anônimas** caso deseje que a identidade do respondente seja preservada nos relatórios.
4. **Público-Alvo:** Utilize a aba de público para restringir quem deve responder. Você pode filtrar por:
   - Uma **Unidade** específica.
   - Um **Curso**, **Série** ou **Turma**.
   - Por **Perfil/Role** (ex: apenas Professores ou apenas Alunos).
   - Por **Usuário** individual.
4. **Visibilidade Inteligente:** O sistema gerencia automaticamente quem pode visualizar e responder cada formulário:
   - Se você definir um **Perfil/Role**, todos os usuários com esse papel terão o questionário habilitado.
   - Se definir um **Usuário Específico**, apenas esse indivíduo poderá ver e responder, garantindo privacidade para avaliações individuais ou feedbacks direcionados.
   - O questionário respeita as datas de início e fim da aplicação, ocultando-se automaticamente fora do período configurado.

### 16.2 Estrutura de Perguntas
Os questionários são organizados em **Blocos Temáticos** (ex: Infraestrutura, Qualidade de Ensino, Gestão).
1. Adicione um Bloco e, dentro dele, adicione as **Perguntas**.
2. **Tipos de Perguntas:**
   - **Discursiva:** Campo de texto livre.
   - **Objetiva:** Seleção de uma única opção.
   - **Múltipla Escolha:** Permite marcar várias opções.
   - **Escala Likert:** Escala de satisfação de 1 a 5 (ou conforme configurado).

### 16.3 Acompanhamento de Resultados
1. Na lista de questionários, você verá a contagem de **Respostas** em tempo real.
2. Ao clicar em **Visualizar** um questionário, o sistema exibe um **Dashboard de Estatísticas** com gráficos de pizza/donuts mostrando o status das respostas e o engajamento do público.
3. No menu **Respostas de Questionários**, você pode consultar individualmente cada envio realizado, o tempo de preenchimento e o perfil institucional do respondente.

---

## 🚀 17. Captação Pública de Interessados

O Torre360 disponibiliza um formulário público que pode ser integrado ou divulgado no site da sua escola para captar o interesse de novos alunos de forma automática e integrada ao funil do CRM.

### 17.1 Como Funciona
- O formulário público é acessado através do endereço: `https://seu-dominio.com/quero-uma-vaga`.
- Ele possui um design moderno, dividido em etapas guiadas para preenchimento ágil.
- O preenchimento está disponível para duas situações: o próprio aluno interessado, ou um responsável (pai/mãe/tutor) interessado em inscrever um aluno menor.

### 17.2 Preenchimento Ágil e Múltiplos Alunos
1. **Dados de Contato:** Coleta dos dados primários (Nome, E-mail, WhatsApp, CPF) de quem está preenchendo o formulário.
2. **Repetidor de Alunos:** O formulário permite adicionar **vários alunos** na mesma inscrição. Basta clicar em **"+ Adicionar outro aluno"**. Isso é ideal para famílias com mais de um filho.
3. **Dados Individuais:** Para cada aluno, é possível informar nome completo, data de nascimento, série/turma de interesse e unidade de preferência individualmente.
4. **Vínculo por Aluno:** Informe se o interessado é pai, mãe, parente ou tutor de cada criança cadastrada diretamente na lista de alunos.
5. **Origem:** O sistema registra automaticamente como o usuário conheceu a escola com base na opção selecionada.

### 17.3 Automação de E-mail de Agradecimento
Após a finalização bem-sucedida, o sistema dispara um e-mail automático para o interessado:
- **Personalização:** O assunto e o texto focam na **Unidade Escolhida** (ex: "Recebemos seu interesse - Unidade Centro").
- **Canais Digitais:** O e-mail inclui links diretos para as redes sociais (Instagram, Facebook, YouTube) configuradas para aquela unidade.
- **Auditória:** Cada e-mail enviado é registrado no sistema (**Sistema → E-mails Enviados**) para fins de conferência e auditoria.

### 17.4 Integração com CRM
- As informações submetidas alimentam automaticamente a tela **CRM → Interessados / Leads** já entrando com o status `Novo`.
- O histórico e preferências ficam salvos no cadastro para facilitar a abordagem consultiva pela equipe de vendas.
- Uma notificação via "Sininho" é disparada para todos os colaboradores administrativos do sistema informando a chegada do novo lead.

### 17.4 Proteção Contra Bot (reCAPTCHA)
Para garantir que seu e-mail e painel não sejam inundados de SPAM, a página utiliza proteção invisível **Google reCAPTCHA v3**.
- Ao longo da inscrição, o sistema analisa o comportamento de navegação. Sem pedir cliques adicionais em "Semáforos", ele julga se é um preenchimento humano válido.
- Se configurado pelos administradores, scripts automatizados que tentarem disparar cadastros serão prontamente bloqueados pelo sistema de forma invisível.

---

## 🧑‍🏫 18. Módulo de Preceptoria

O módulo de **Preceptoria** permite agendar e registrar reuniões pedagógicas individuais entre um professor e um aluno (matriculado), além de manter relatórios dessas sessões com um editor de texto rico e suporte a modelos reutilizáveis.

### 18.1 Conceitos

| Conceito | Descrição |
|---|---|
| **Preceptoria** | O agendamento em si: data, hora início, hora fim (opcional), professor e matrícula do aluno. |
| **Relatório de Preceptoria** | Documento gerado após a sessão, contendo observações e registros. Um relatório está vinculado a exatamente uma Preceptoria. |
| **Template de Relatório** | Modelo de texto reutilizável que pode ser carregado em qualquer relatório como ponto de partida. |

---

### 18.2 Gerenciar Preceptorias

1. Vá em **Preceptoria → Preceptorias**.
2. Clique em **Nova Preceptoria** para criar um agendamento.
3. Preencha:
   - **Data** (obrigatório).
   - **Hora Início** (obrigatório).
   - **Hora Fim** (opcional).
   - **Professor(a)** — qualquer Pessoa cadastrada no sistema (obrigatório). Para usuários com o papel de **Professor**, este campo exibirá apenas as pessoas associadas ao seu usuário. Caso possua **apenas um vínculo**, o campo será pré-preenchido e bloqueado; caso possua múltiplos, permitirá a escolha entre eles.
   - **Matrícula (Aluno)** — busca pela matrícula com formato `Período - Turma - Aluno` (opcional).
4. Na listagem, a coluna **Relatório** indica (com ícone verde) se já existe um relatório associado àquela preceptoria.
5. O filtro **Sem Relatório** permite localizar rapidamente preceptorias que ainda não têm relatório criado.
6. **Ações em Lote:** Para facilitar a gestão de múltiplos horários, você pode selecionar várias preceptorias na tabela e utilizar:
   - **Clonar em Lote:** Cria cópias exatas dos horários selecionados (data, hora e professor), mas **remove o vínculo com o aluno**. Útil para replicar slots de atendimento para outros dias.
   - **Editar em Lote:** Permite alterar a data, o horário ou o professor de todos os registros selecionados de uma só vez. Campos deixados em branco no formulário de edição em lote não serão alterados nos registros originais.

---

### 18.3 Criar Templates de Relatório

1. Vá em **Preceptoria → Templates de Relatório**.
2. Crie um template com **Nome** e **Corpo** (editor de texto completo estilo Office).
3. Os templates são reutilizáveis em qualquer Relatório de Preceptoria.

---

### 18.4 Criar e Editar Relatórios de Preceptoria

1. Vá em **Preceptoria → Relatórios de Preceptoria**.
2. Clique em **Novo Relatório**.
3. Selecione a **Preceptoria** à qual este relatório pertence (cada preceptoria pode ter no máximo 1 relatório).
4. Para iniciar com um modelo pronto:
   - No campo **Carregar Template**, selecione um template da lista.
   - Clique no botão **Aplicar Template** (ícone de seta).
   - Confirme na janela de diálogo — o conteúdo do template preencherá o campo **Corpo** automaticamente.
5. Edite o **Corpo** com o editor de texto rico conforme as observações da sessão.
6. **Visibilidade:** Utilize a opção **Visível para Aluno/Responsável** para definir se este relatório poderá ser consultado pela família. Se estiver desativado (padrão), apenas professores e secretaria visualizam o conteúdo.
7. Clique em **Salvar**.

> [!NOTE]
> Ao aplicar um template, o conteúdo atual do campo Corpo é **substituído** pelo conteúdo do template selecionado. Certifique-se de não ter texto importante não salvo antes de confirmar a ação.

---

### 18.5 Agendar Preceptoria (Responsáveis e Alunos)

Responsáveis e alunos podem agendar suas próprias preceptorias diretamente pelo painel, escolhendo entre os horários disponibilizados pelos professores.

1. Vá em **Preceptoria → Agendar Preceptoria**.
2. **Seleção da Matrícula:**
   - Se você for um **Aluno**, suas matrículas serão exibidas automaticamente.
   - Se você for um **Responsável**, verá as matrículas de todos os alunos aos quais está vinculado.
3. **Seleção do Professor:** Após escolher a matrícula, o campo de professor é preenchido com os professores vinculados àquela turma.
4. **Horário Disponível:** O sistema exibirá uma lista de horários (dia e hora) que o professor selecionado cadastrou e que ainda estão vagos (sem aluno).
5. Selecione o horário desejado e clique em **Confirmar Agendamento**.
6. **Notificação Automática:** Assim que o agendamento é confirmado (ou cancelado através de uma liberação de horário), o Professor vinculado recebe automaticamente uma notificação detalhada por **E-mail**, no **Sininho do Painel** e via **Push no Celular**, informando o nome do aluno, a data e o horário.

> [!TIP]
> Caso o professor desejado não apareça com horários disponíveis, entre em contato com a secretaria para que novos "slots" de preceptoria sejam criados no sistema.

---

### 18.6 Visibilidade e Acesso Restrito
Para garantir a privacidade e o foco pedagógico, a visualização das preceptorias é filtrada conforme o papel do usuário:
- **Professores:** Cada professor visualiza apenas as preceptorias (agendamentos e registros) associadas à sua própria Pessoa. Eles não têm visibilidade sobre a agenda de outros colegas.
- **Responsáveis e Alunos:** Visualizam apenas os agendamentos já realizados vinculados à sua própria matrícula ou de seus dependentes. Além disso, na tela de agendamento, podem visualizar slots vagos de professores para escolha.
- **Administradores/Secretaria:** Possuem visibilidade total de todos os agendamentos e registros do sistema.

---

> **Torre360** — Gestão inteligente para instituições de ensino.
