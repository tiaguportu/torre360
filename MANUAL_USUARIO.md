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
| 🎯 **CRM / Comercial** | Gestão de Interessados e Kanban de Prospecção |
| 🎓 **Acadêmico** | Matrículas, Alunos, Turmas, Cursos e Séries |
| ✅ **Avaliações** | Lançamento de Notas, Avaliações e Etapas Acadêmicas |
| 📅 **Calendário e Horários** | Cronograma de Aulas, Horários e Dias não letivos |
| 💰 **Financeiro** | Faturas, Contratos, Bancos, Conciliação e Plano de Contas |
| 👥 **Pessoas** | Cadastro de Pessoas, Responsáveis e Coordenadores |
| 📄 **Documentos** | Gestão de Documentos enviários e Tipos de Documentos |
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

### 3.3 Alertas de Acompanhamento (Follow-up)
1. **Identificação Visual:** Quando um interessado precisa de contato urgente (atraso ou agendamento desatualizado), um botão vermelho e pulsante aparecerá no topo do formulário de edição.
2. **Notificar Consultor:** Clique neste botão de alerta para disparar notificações automáticas.
3. **Canais de Alerta:** O sistema enviará simultaneamente:
   - Um **E-mail** detalhado para o Consultor Responsável com o link direto para o interessado.
   - Uma **Notificação no Sininho** do sistema, que fica registrada no histórico do usuário.
4. **Confirmação:** O sistema solicitará sua confirmação antes de enviar o alerta.

---

## 👥 4. Cadastro Unificado de Pessoas

Uma **Pessoa** no sistema é a entidade central. Ela pode acumular múltiplos papéis (Aluno, Responsável Financeiro, Fornecedor).

### 4.1 Cadastro de Pessoa
1. Preencha os dados básicos (**CPF com máscara automática**, Nome, Data de Nascimento, **Identidade (RG)**, **Profissão** e **Estado Civil**).
2. **Edição em Lote:** Na listagem de pessoas, você pode selecionar múltiplos registros e utilizar a ação **Editar em Lote** para atualizar rapidamente o Sexo, Raça/Cor, Nacionalidade, Estado Civil, Profissão ou Identidade de várias pessoas ao mesmo tempo.
3. **Endereços e Automação via CEP:** Na aba de endereços, você pode vincular um ou mais endereços à pessoa.
   - **Busca Automática:** Ao digitar um **CEP** válido e sair do campo, o sistema consulta automaticamente a base do **ViaCEP** e preenche os campos de **Logradouro**, **Bairro** e **Cidade/Estado**.
   - **Tipos de Endereço:** Defina obrigatoriamente o **Tipo** (ex: Residencial ou Comercial). 
   - **Complemento:** O sistema permite o preenchimento detalhado incluindo o campo **Complemento** para apartamentos, blocos ou referências.
4. **Foto:** Use o editor integrado para ajustar a foto de perfil.

### 4.2 Segurança e Privacidade das Fotos
1. As fotos de perfil das pessoas são armazenadas de forma segura em um **disco privado**.
2. O sistema garante que apenas usuários autenticados possam visualizar essas imagens, protegendo a privacidade de alunos e colaboradores.
3. Caso realize o upload de uma nova foto, o sistema processará a imagem e a disponibilizará automaticamente para visualização interna segura.

---

## 🎓 5. Acadêmico — Ensino e Avaliação

### 5.1 Lançamento de Notas
1. Vá em **Acadêmico → Avaliações**.
2. **Filtro de Pendências:** Utilize o filtro "Pendência de Lançamento" para localizar rapidamente provas ou trabalhos onde ainda faltam alunos sem nota lançada.
3. Localize a prova/trabalho e utilize a ação de **Lançar Notas**.
4. **Padronização de Nomes:** Para facilitar a busca e identificação, as avaliações no sistema seguem o padrão de nome: `Categoria Avaliação - Turma - Disciplina - Etapa Avaliativa`.
5. O sistema exibirá a lista de alunos matriculados na turma vinculada para preenchimento rápido.

### 5.2 Frequência Escolar
1. Em **Acadêmico → Frequência**, selecione o Cronograma de Aula do dia.
2. Marque as faltas ou presenças dos alunos. O padrão é "Presença".

### 5.3 Boletim do Aluno
1. Na visualização de **Matrículas**, use a ação **Boletim**.
2. O sistema gera uma tabela dinâmica por Etapa Avaliativa (Bimestre/Trimestre) mostrando as notas de cada disciplina e a média global.
3. Notas abaixo da média aparecem destacadas em vermelho.
4. **Frequência:** Ao lado das médias, o boletim exibe o percentual de presenças do aluno nas aulas daquela disciplina dentro do período da etapa avaliativa.
5. **Edição de Notas:** Caso possua a permissão necessária, você visualizará o botão **Editar Notas** no topo da página do boletim. Esta tela permite o preenchimento rápido de todas as notas da etapa em um layout idêntico ao de consulta.

### 5.4 Gerenciamento de Notas
1. No menu **Avaliações → Notas**, é possível visualizar o histórico completo de notas lançadas.
2. Para facilitar a identificação, a coluna **Matrícula** segue o padrão: `Turma - Período Escolar - Nome do Aluno`.
3. A busca nesta tela permite localizar registros pesquisando por qualquer uma dessas três informações.

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
4. Se uma categoria possui uma **Substituta** configurada (como uma "Prova Substitutiva"), o sistema realizará o cálculo automático substituindo a nota da categoria original se a nota da substituta for maior ou conforme a regra configurada.

---

## 📝 6. Secretaria e Documentação

### 6.1 Matrículas e Contratos
1. Ao realizar uma matrícula, o sistema permite a criação automática de um **Contrato**.
2. O contrato centraliza as obrigações financeiras e os responsáveis legais.
3. No cadastro do contrato, preencha o **Valor Total** e a **Quantidade de Parcelas** — essas informações aparecem no texto do contrato gerado.
4. **Seleção de Alunos:** O campo de seleção de aluno permite buscar qualquer pessoa cadastrada. Para facilitar o cadastro de crianças, o sistema exibe tanto pessoas com o perfil de "aluno" quanto pessoas sem conta de usuário vinculada (sem perfil).
5. **Busca Avançada:** Você pode buscar alunos pelo **Nome** ou **CPF** diretamente no campo de seleção.
6. **Responsáveis Financeiros:** Qualquer pessoa cadastrada pode ser selecionada como Responsável Financeiro, independentemente de possuir ou não o papel (role) de "responsavel" no sistema. Isso permite que pais que já possuem outros acessos (como funcionários/professores) ou pessoas sem acesso ao painel sejam vinculadas financeiramente ao contrato.


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

### 6.3 Assinatura Digital (Assinafy)
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
- **Financeiro:** Relatório DRE, Transações Bancárias, Cadastro de Fornecedores, Centros de Custo, Plano de Contas e Bancos.
- **Secretaria:** Matrículas, Documentos sensíveis e **Edição de Notas de Boletim** (permissão `boletim_editar_matricula`). Além disso, usuários com este perfil possuem visibilidade total de todas as matrículas cadastradas no sistema.
- **CRM:** Gestão de leads e histórico de contatos.

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

## 🔔 11. Notificações Internas (Sininho)

O Torre360 possui um sistema central de notificações em tempo real, representado pelo ícone de **Sininho** no topo do painel administrativo.

### 11.1 Como Funciona
- Sempre que houver uma ação que necessite sua atenção (ex: documentos pendentes em uma matrícula), um indicador numérico aparecerá sobre o sininho.
- Clique no sininho para visualizar a lista de notificações recentes.
- Cada notificação possui um botão de ação rápida (ex: **Ver Documentos**) que leva você diretamente à tela necessária.

### 11.2 Notificações Importantes
- **Documentos Pendentes:** Disparada automaticamente quando a secretaria identifica que faltam documentos obrigatórios ou que algum documento enviado foi recusado.
- **Auditoria de Documentos (ADM):** Usuários com papel de 'super_admin', 'admin' ou 'secretaria' recebem notificações em tempo real sempre que um novo documento é inserido ou removido de uma matrícula, permitindo o acompanhamento imediato das alterações documentais.
- **Feedback de Envio de Avisos:** Quando um administrador dispara avisos de pendência (individual ou em lote), uma notificação de confirmação é enviada para o seu próprio sininho, servindo como registro persistente da ação.

---

## 📅 12. Calendário e Cronograma de Aulas

O módulo de Cronograma permite a visualização e gestão das aulas planejadas para cada turma.

### 12.1 Filtro por Período
1. Vá em **Calendário e Horários → Cronogramas de Aulas**.
2. No menu de filtros (ícone de funil), localize o filtro **Período**.
3. Defina uma **Data Início** e/ou uma **Data Fim**.
4. O sistema filtrará automaticamente todas as aulas cuja data esteja compreendida entre o intervalo selecionado, facilitando o planejamento semanal ou mensal.

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

> **Torre360** — Gestão inteligente para instituições de ensino.
