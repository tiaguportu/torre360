# Torre360 - Manual do Usuário

Bem-vindo ao **Torre360 - Sistema de Gestão Escolar**. Este manual foi criado para ajudar você a navegar e utilizar todos os recursos do sistema com eficiência e praticidade.

---

## 🔐 1. Acesso ao Sistema

1. Abra o navegador e acesse o endereço do sistema (ex: `http://localhost:8000/admin`).
2. Insira seu **E-mail** e **Senha** fornecidos pelo administrador.
3. Clique em **Entrar**. Você será direcionado ao Painel Principal (Dashboard).
4. No **Dashboard**, você poderá visualizar widgets de atalho, como o de **Agendamento de Preceptoria**, **Questionários Pendentes**, **Pendências de Lançamento de Frequência** (com agrupamento por dia e lançamento em lote de chamada) e o de **Matrículas com Pendências** (que exibe a contagem em tempo real de matrículas com pendência de responsáveis, documentos obrigatórios faltantes ou pendência de cadastro/endereço no Aluno, Responsáveis e Responsável Financeiro); cada cartão abre a lista de Matrículas já filtrada pelo tipo de pendência.

### 🔑 Recuperação de Senha
Caso tenha esquecido sua senha:
1. Na tela de login, clique no link **Esqueceu sua senha?**.
2. Informe o **E-mail** cadastrado e confirme a verificação de segurança.
3. Clique em **Enviar email**.
4. Você receberá um e-mail contendo um **link seguro e temporário** de redefinição. Sua senha atual permanecerá funcionando até que a troca seja efetivamente confirmada por você.
5. Acesse o link recebido no e-mail, informe e confirme sua nova senha para concluir o acesso.

> [!NOTE]
> O acesso ao sistema, o registro de novos usuários e a solicitação de recuperação de senha são protegidos pelo **Google reCAPTCHA v3**. O sistema analisa o comportamento de navegação de forma invisível para garantir a segurança contra acessos automatizados e ataques de robôs.
> Caso você não tenha acesso, solicite ao administrador que crie sua conta e associe o papel (role) correto ao seu perfil.

---

## 📌 2. Painel de Navegação

A barra lateral esquerda é dividida em grupos para facilitar o dia a dia:

| Grupo | O que você faz aqui |
|---|---|
| 🏠 **Início** | Painel com indicadores, calendários e pendências |
| 🎯 **CRM / Comercial** | Interessados (lista e Kanban), Campanhas de Marketing, Comunicação em Massa, Leads da Landing Page e Modelos de WhatsApp |
| 🗂️ **Secretaria** | Matrículas, Pessoas, Coordenadores, Documentos (modelos, emitidos com QR e inseridos), Crachás |
| 🎓 **Acadêmico** | Cursos, Séries, Turmas, Ensalamento, Disciplinas, Salas, Rematrículas, Planos de Aula, Nova Matrícula (Wizard), Frequências, Fechamento do Ciclo Letivo e Questionários |
| ✅ **Avaliações** | Avaliações, Notas, Avaliação e Notas de Habilidades e Lançamento de Notas em Grade |
| 📖 **Currículo (BNCC)** | Habilidades vinculadas à BNCC |
| 🤝 **Preceptoria** | Ciclos, Preceptorias (e agendamento), Relatórios e Templates de Relatório |
| 📅 **Calendário e Horários** | Cronograma de Aulas, Dias não letivos e Períodos Letivos |
| 📢 **Comunicação Escolar** | Eventos Escolares (RSVP) e Central de Atendimento |
| 🚨 **Convivência e Disciplina** | Ocorrências da Rotina Escolar e Notificações aos Pais |
| 🏥 **Saúde Escolar** | Fichas Médicas, Restrições Alimentares, Medicamentos e Ambulatório |
| 💰 **Financeiro** | Contratos, Faturas, Régua de Cobrança, Transações Bancárias, Fornecedores, Templates de Contrato e DRE |
| 🛠️ **Operacional** | Gestão de Ordens de Serviço (Manutenção) |
| 📍 **Localização e Cadastros** | Endereços, Instituições de Ensino e Unidades |
| ⚙️ **Configurações** | Tabelas auxiliares e parametrizações: Países, Estados, Cidades, Bancos, Código BACEN, Centro de Custo, Plano de Contas, Tributação do Curso, Tipos e Categorias diversas, Áreas de Conhecimento, Campos de Experiência, Turnos e Configuração Inicial da escola |
| 🛡️ **Sistema e Segurança** | Usuários, Logs de Atividade e E-mails Enviados |
| ❓ **Ajuda** | Central de Ajuda — Vídeos Tutoriais |
| 🔐 **Filament Shield** | Roles e permissões (Super Administrador) |

### 2.1 Barra Lateral Dinâmica (Perfil Responsável)

Para facilitar a navegação de pais e responsáveis, o Torre360 adapta automaticamente a barra lateral quando detecta que você possui este perfil:

- **Grupos por Aluno:** Em vez de uma lista genérica de recursos, o sistema cria grupos específicos com o nome de cada um de seus filhos vinculados (ex: *Aluno: João Silva*).
- **Acesso Direto:** Dentro do grupo de cada aluno, você encontrará atalhos diretos para as informações dele:
    - **Dados Cadastrais:** Atalho para consultar e atualizar as informações básicas do aluno (pessoa).
    - **Boletim Escolar:** Acesso rápido às notas, médias e frequências do aluno.
    - **Minhas Preceptorias:** Visualização e agendamento de reuniões pedagógicas (já com filtros aplicados).
    - **Documentos:** Consulta e envio de documentos obrigatórios, exibindo um **indicador numérico (badge)** em vermelho sempre que houver documentos pendentes de envio ou regularização.
- **Grupo Principal:** O Dashboard e as funções gerais do sistema continuam disponíveis no grupo "Principal".

---

## 🎯 3. CRM — Gestão de Leads e Interessados

O módulo de CRM permite gerenciar o processo de captação de novos alunos antes mesmo da matrícula. Ele oferece um funil de vendas visual (Kanban), qualificação de leads, rastreamento de contatos e automações de follow-up.

### 3.1 Kanban de Interessados (Funil de Vendas)
1. Vá em **CRM → Interessados**.
2. Utilize a visualização em **Funil de Vendas (CRM)**:
   - **Interface:** O layout é inspirado no Trello, com colunas coloridas que facilitam a distinção visual entre as etapas do funil (ex: *Novo Contato*, *Agendamento*, *Matrícula*).
   - **Drag & Drop:** Arraste e solte os cards entre as colunas para atualizar o status do interessado em tempo real. Ao mover para um status de "Matriculado", a data de conversão é registrada automaticamente.
   - **Filtro por Consultor:** Use o botão "Filtrar Consultor" no topo para visualizar apenas os leads de um consultor específico.
   - **Indicadores Visuais nos Cards:**
     - **Temperatura:** Cada card exibe um indicador visual: 🔥 Quente, 🟡 Morno, 🔵 Frio. Se nenhuma temperatura foi definida manualmente, o sistema calcula automaticamente baseado na atividade recente.
     - **Valor Estimado:** Quando preenchido, o valor potencial da matrícula é exibido no card em verde.
     - **Total por Coluna:** O cabeçalho de cada coluna mostra o valor total estimado dos leads daquela etapa.
     - **Dias no Funil:** Leads com mais de 30 dias no funil exibem um alerta vermelho no card.
     - **Contagem:** O topo de cada coluna mostra o número total de interessados naquela etapa.
     - **Alertas de Data:** As datas de "Próximo Contato" mudam de cor automaticamente: **Vermelho** se estiverem atrasadas, **Amarelo** se forem para hoje.
     - **Cards em Vermelho:** Se a data do "Próximo Contato" estiver no passado, o card ganha borda e fundo vermelhos de alerta. O tooltip mostra o resumo do último contato.
   - **Acesso Rápido:** Clique no ícone de lápis no card para editar as informações completas ou ver o histórico de contatos.

### 3.2 Listagem de Interessados (Tabela)
1. Na página de listagem, você encontra uma tabela completa com:
   - **Abas de situação (topo da tabela), com contador:** *Todos*, *Precisa de contato* (contato atrasado), *Estagnados* (7+ dias sem interação), *Quentes* (temperatura quente ou score alto), *Em andamento* e *Finalizados* (matriculados/perdidos).
   - **Colunas visíveis:** **Interessado** (nome e telefone logo abaixo), **Status / Consultor** (status em badge e o consultor abaixo), **Qualificação** (Score em badge e a temperatura do consultor abaixo), **Próximo contato** (data e "em/há X dias") e **Origem**. Leads com contato atrasado aparecem com uma faixa vermelha na linha.
   - **Colunas opcionais (seletor de colunas):** Telefone (com cópia rápida), Consultor, Campanha, Temperatura, Dias no Funil, Valor Estimado, Total de Contatos, Sem Interação, Distância, Transporte, Redes Sociais e Criado em.
   - **Ordenação Padrão:** Os leads mais urgentes aparecem primeiro (ordenado por data de próximo contato).
2. **Filtros Avançados:**
   - **Status:** Filtre por múltiplos status simultaneamente.
   - **Origem:** Filtre por fonte de captação.
   - **Campanha:** Filtre pela campanha de marketing que originou o lead (a coluna **Campanha** pode ser exibida pelo seletor de colunas).
   - **Consultor:** Filtre por consultor responsável.
   - **Precisa de Contato:** Filtre leads com contato atrasado.
   - **Temperatura:** Filtre por classificação (Quente/Morno/Frio).
   - Os filtros ficam recolhidos acima da tabela e são lembrados durante a sessão.
3. **Ações Rápidas na Tabela:** *Atendimento*, *WhatsApp*, *Enviar ao consultor* e *Editar* aparecem como ícones em cada linha; as demais ficam no menu **⋮ Mais ações**.
   - **Registrar Atendimento:** Registre um contato diretamente da tabela, informando tipo, relato, duração, resultado e próximo contato. O sistema identifica automaticamente quem registrou e marca o primeiro contato efetivo.
   - **Enviar ao consultor (ícone de compartilhar):** Abre o WhatsApp do consultor responsável pelo lead com a mensagem pronta: link direto para falar com o interessado (o consultor só clica), alunos, status, origem, temperatura, próximo contato, visita agendada e um resumo dos **3 últimos contatos** registrados. O ícone fica **verde** quando o consultor tem telefone cadastrado e **amarelo** quando não tem — nesse caso o WhatsApp abre com a mensagem pronta, sem destinatário, e você escolhe o contato. Só aparece para leads que têm consultor. Para o envio ir direto ao consultor, o telefone dele precisa estar preenchido no cadastro da **Pessoa** vinculada ao usuário.
   - **Agendar Visita:** Marque uma visita do lead à escola (data e hora, aluno e observações). Veja a seção 3.10.
   - **Matricular:** Abre o **Assistente de Matrícula** já preenchido com os dados do lead (veja a seção 3.11). Quem não tem acesso ao Assistente vê, no lugar, **Marcar matriculado**, que apenas move o lead para o status de matrícula.
   - **Marcar como Perdido:** Registre o motivo da perda (Preço, Concorrência, Distância, Mudança, Desistência, Sem retorno, Outro).
4. **Ações em Lote:**
   - **Atribuir Consultor:** Selecione múltiplos leads e atribua um consultor responsável de uma vez.
   - **Enviar aos consultores (WhatsApp):** Selecione vários leads e veja um botão do WhatsApp para cada consultor, já com a lista dos leads dele (nome, link direto e status) numa única mensagem. Leads sem consultor aparecem num aviso e ficam de fora — use *Atribuir Consultor* para defini-lo. Se a lista for muito grande, a mensagem é encurtada e indica quantos leads ficaram de fora.
   - **Excluir:** Exclua múltiplos leads selecionados.

5. **Botão Ajuda:** No topo da listagem, o botão **Ajuda** abre um guia da tela: para que serve cada aba, como ler cada coluna (inclusive o Score e a faixa vermelha), as ações por linha e em lote, os filtros, como entram novos leads, uma rotina diária sugerida e como o Score é calculado. Os itens variam conforme as suas permissões.
### 3.3 Qualificação de Leads
O formulário de edição do interessado oferece ferramentas de qualificação:
- **Resumo do Lead:** Painel no topo da edição com o **Lead Score** em anel (verde ≥ 70, âmbar 40–69, vermelho < 40), cartões de *dias no funil*, *total de contatos* e *dias sem interação* (destacado em vermelho quando o lead está estagnado, 7 dias ou mais) e o detalhamento do score em barras de progresso por fator.
- **Temperatura:** Defina manualmente a sua percepção (Quente/Morno/Frio). Ela não é calculada pelo sistema, mas **entra no Lead Score com o maior peso** (padrão: 20 de 100: quente 20, morno 10, frio 0).
- **Valor Estimado:** Registre o valor potencial da matrícula para projeções de receita.
- **Redes Sociais:** Na aba *Dados do Negócio*, em **Redes Sociais**, clique em *Adicionar rede social*, escolha a rede (Instagram, Facebook, LinkedIn, TikTok, X, YouTube ou Outra) e cole o link do perfil (precisa começar com `https://`). É possível cadastrar vários perfis.
- **Motivo de Perda:** Quando o status muda para "Perdido", o campo de motivo aparece automaticamente.
- **Telefone:** O campo tem máscara no padrão Brasil com DDD, aceitando fixo `(11) 3333-4444` ou celular `(11) 98888-7777`. A máscara se ajusta sozinha conforme a quantidade de dígitos digitada.

### 3.4 Registro de Histórico de Contato
1. Dentro do cadastro do Interessado, utilize a aba **Histórico de Contatos**.
2. Registre cada ligação, e-mail ou visita, informando:
   - **Tipo de Contato:** Telefone, WhatsApp, Presencial, etc.
   - **Relato:** Descrição do que foi conversado.
   - **Duração:** Tempo em minutos do atendimento.
   - **Resultado:** Agendou Visita, Retornar, Sem Interesse, Efetuou Matrícula, Outro.
3. O sistema registra automaticamente quem realizou o contato e a data.

### 3.5 Alertas e Notificações
1. **Notificação no Sininho:** Sempre que um novo interessado preenche o formulário no site, todos os usuários administrativos recebem um alerta instantâneo.
2. **Badge na Barra Lateral:** O menu **CRM → Interessados / Leads** exibe um círculo verde com a quantidade de leads com status "Novo".
3. **Follow-up Pulsante:** Quando um interessado precisa de contato urgente (atraso no agendamento), um botão vermelho pulsante aparece no topo da tela de edição para alertar o consultor por e-mail e sistema. Antes de enviar, o modal de confirmação ("Enviar Alerta de Acompanhamento?") mostra o **e-mail para o qual a mensagem será enviada** (o do consultor responsável). Se o consultor não tiver e-mail cadastrado, o modal avisa que só a notificação no sistema será enviada; se o lead não tiver consultor, avisa que não há para quem enviar.
4. **Notificação Automática Diária:** O sistema envia automaticamente (às 8h) notificações por e-mail e sininho para consultores com leads pendentes de contato.
5. **Lembrete de Visita:** No mesmo disparo das 8h, o consultor recebe um aviso no sininho das visitas agendadas para as próximas 24 horas (um aviso por visita).

### 3.6 Dependentes (Alunos Vinculados)
O sistema permite registrar os potenciais alunos vinculados ao interessado:
- **Dados:** Nome da criança, Série de interesse, Data de nascimento.
- **Vínculo:** Pai, Mãe, Parente ou Tutor.
- **Flexibilidade:** Cadastre múltiplos alunos para o mesmo interessado.

### 3.7 Formulário Público de Captação
Interessados podem se cadastrar diretamente pelo site (**quero-matricular**):
- O formulário permite cadastro como responsável ou próprio aluno.
- Suporta múltiplos alunos por cadastro.
- O interessado recebe um e-mail de agradecimento personalizado com os dados da unidade.
- A equipe interna é notificada automaticamente via sininho.

### 3.7.1 Configurar os Pesos do Lead Score
Acesse **CRM / Comercial → Pesos do Lead Score** (disponível para Administradores):
1. **Pesos e Cores:** defina o peso máximo de cada um dos 12 fatores (a soma precisa ser **100**, conferida em tempo real; a **Percepção do consultor**, isto é, a Temperatura, é o fator de maior peso por padrão: 20) e os cortes de cor do score (quente/morno).
2. **Perfil / Fit** e **Engajamento:** ajuste os pontos por temperatura (percepção do consultor), faixa de filhos, distância, transporte, profissão (palavras-chave), valor estimado, interações e recência do contato.
3. **Origem:** defina os pontos por origem do lead e o valor para as demais.
4. Clique em **Salvar configuração**. Para aplicar aos leads já cadastrados, clique em **Recalcular todos os leads**.
5. **Restaurar padrão** descarta as personalizações e volta aos valores originais do sistema.

> Os pontos de cada faixa não podem passar do peso do fator; o sistema avisa se algo estiver inconsistente.

### 3.8 Importação e Extração Inteligente de Leads com IA (Google Gemini)
Para agilizar a prospecção e evitar a digitação manual de formulários, o Torre360 possui integração nativa com a **IA da Google (Gemini 2.5 Flash)** com suporte multimodal (texto e imagens):
1. **Onde Acessar:** Clique no botão de destaque **Importar Lead com IA** (ícone ✨ `sparkles`) disponível no topo da Listagem de Interessados, no Funil Kanban e na tela de Cadastro de Novo Lead.
2. **Como Usar:** 
   - **Prints / Capturas de Tela:** Você pode anexar diretamente uma imagem com o print da conversa (WhatsApp, Direct do Instagram, print de e-mail ou foto de anotação). O sistema analisa a imagem e extrai os dados visuais.
   - **Mensagem Bruta / Texto:** Você pode colar qualquer mensagem bruta recebida de clientes ou adicionar observações textuais complementares à imagem.
   - Selecione o consultor responsável e a origem fallback (se a IA não inferir).
   - Clique em **Analisar e Criar Lead**.
> **Alta demanda:** se aparecer a mensagem "servidores de IA do Gemini estão temporariamente com alta demanda", o sistema já tentou automaticamente vários modelos em duas rodadas. Aguarde alguns instantes e tente novamente.
3. **O que a IA faz automaticamente:**
   - **Histórico de Contato:** registra automaticamente um contato com o relato da conversa, o canal (WhatsApp, Ligação, E-mail ou Presencial) e a data/hora em que ocorreu (quando identificada no texto/print; caso contrário, usa o momento da importação).
   - **Observações com datas:** o resumo em Observações cita as datas disponíveis (conversa, visitas, prazos, previsão de matrícula) no formato DD/MM/AAAA (o sistema converte automaticamente qualquer data fora desse padrão). O histórico também começa com a linha "Contato em DD/MM/AAAA HH:MM via <canal>".
   - Lê e interpreta o print ou o texto recebido com alta precisão.
   - Extrai o Nome completo, E-mail, Telefone e CPF do responsável.
   - Identifica **perfis de redes sociais** citados na mensagem ou no print e os cadastra em Redes Sociais (só links válidos; o `https://` é acrescentado quando faltar).
   - Extrai o Nome da criança/aluno, calcula a Data de Nascimento se uma idade for informada, e identifica o Vínculo (Pai, Mãe, Tutor).
   - Mapeia automaticamente a **Série de Interesse** do aluno relacionando com os cursos cadastrados no sistema.
   - Infere a **Origem do Lead** (ex: WhatsApp, Instagram, Indicação).
   - Classifica a **Temperatura** do Lead (🔥 Quente, 🟡 Morno, 🔵 Frio) analisando o tom e urgência da mensagem.
   - Cadastra/vincula a `Pessoa`, o `Interessado` e os `Dependentes` no banco de dados com um único clique!

### 3.9 Campanhas de Marketing e Rastreamento (UTM)
Descubra quais campanhas trazem leads que realmente viram matrícula.
1. Vá em **CRM / Comercial → Campanhas de Marketing** e clique em **Novo**.
2. Informe o **nome**, o **canal** (Google Ads, Meta Ads, Instagram, e-mail, indicação, evento etc.), o período, o **investimento (R$)** e o **Código UTM** (letras, números, hífen e sublinhado; o sistema grava sempre em minúsculas).
3. Use o código no link da campanha, por exemplo:
   `https://seusite.com.br/quero-matricular?utm_source=instagram&utm_medium=cpc&utm_campaign=CODIGO`
4. Quem preencher o formulário depois de clicar nesse link entra no CRM já atribuído à campanha, e o UTM fica visível na ficha do lead (aba **Dados do Negócio**). Se a campanha estiver **inativa** ou o código não existir, o lead é criado normalmente, apenas sem campanha.
5. A atribuição vale para o **primeiro** contato: se a mesma pessoa preencher o formulário de novo por outro link, a campanha original é mantida. Você também pode escolher a campanha manualmente na ficha do lead.
6. A lista de campanhas mostra **Leads**, **Matrículas**, **Conversão** e **Investimento**. Na tela inicial, os widgets **Conversão por Campanha de Marketing** (com custo por lead e por matrícula) e **Conversão por Origem do Lead** comparam o desempenho. Só contam como matrícula os leads convertidos.

### 3.10 Agendamento de Visitas
1. Na tabela de Interessados, clique em **Agendar Visita**, ou abra o lead e use a aba **Visitas à Escola → Agendar Visita**.
2. Informe data e hora (no agendamento rápido da tabela a data não pode estar no passado), o aluno (quando há mais de um) e observações. O consultor responsável pelo lead passa a ser o responsável pela visita.
3. O **Próximo Contato** do lead é ajustado para a data da visita, sem adiar um retorno que já estivesse marcado para antes.
4. As visitas aparecem no **calendário de follow-up** em roxo (vermelho se estiverem atrasadas), como `Visita: nome`. Cada consultor vê as suas; administradores veem todas.
5. Depois da visita, na aba **Visitas à Escola**, marque **Realizada** ou **Não compareceu**.
6. A variável `[Horário de Visita Agendada]` dos modelos de WhatsApp passa a usar a próxima visita agendada.

### 3.11 Matricular a partir do Lead
1. Na tabela de Interessados, clique em **Matricular** no lead (não aparece em leads já matriculados).
2. O **Assistente de Matrícula** abre com um aviso "Dados do lead carregados" e já traz:
   - os **alunos** cadastrados como dependentes (nome e data de nascimento);
   - o **responsável financeiro** com os dados de contato do lead. Se o contato tiver o mesmo nome de um dos alunos (o próprio aluno preencheu o formulário), os dados de contato vão para o aluno e nenhum responsável é presumido;
   - o **curso** e a **unidade**, a partir da série de interesse.
3. Revise, escolha o vínculo do responsável, a turma e demais dados, e conclua em **Finalizar Matrícula**.
4. Ao finalizar, o lead é marcado como **Matriculado**, com a data de conversão, e entra nos indicadores de conversão por campanha e origem.

### 3.12 Leads da Landing Page
Em **CRM / Comercial → Leads da Landing Page** ficam os pedidos de demonstração enviados pelo formulário da página inicial do sistema. São **escolas interessadas em contratar o Torre360**, não famílias em busca de vaga; por isso são separados dos Interessados. O menu mostra o número de leads novos. Use **Em contato**, **Descartar** e **Reabrir** para acompanhar cada pedido. Disponível para administradores.

### 3.13 Comunicação em Massa por E-mail
Envie um mesmo e-mail para um grupo de pessoas de uma só vez, sem precisar selecionar contato por contato.

1. **Pela tabela de Interessados:** selecione os leads desejados (caixas de seleção) e use a ação em lote **Enviar Comunicação por E-mail**. Informe o assunto e a mensagem (editor de texto rico) e confirme — o envio é feito em segundo plano.
2. **Por segmento, em CRM / Comercial → Comunicação em Massa:** crie uma nova comunicação escolhendo o **público**:
   - **Interessados (CRM):** filtre por **status** do lead e/ou **origem**. É preciso escolher ao menos um filtro, para evitar enviar para "todos os leads" sem querer.
   - **Responsáveis por turma:** escolha uma ou mais turmas; o e-mail vai para os responsáveis dos alunos com matrícula ativa nelas.
3. Escreva o **assunto** e a **mensagem**. Use `[Nome]` para inserir automaticamente o primeiro nome de cada destinatário.
4. Salve como rascunho. A lista mostra a quantidade estimada de **destinatários**. Clique em **Enviar** para confirmar (a tela mostra quantas pessoas vão receber antes de você confirmar) — o envio roda em fila em segundo plano com tempo limite estendido (até 10 minutos) e isolamento de falhas por destinatário: se um e-mail individual for rejeitado pelo provedor SMTP ou falhar, o disparo continua para os demais normalmente.
5. **Quem não recebe:** pessoas sem e-mail cadastrado e pessoas que desativaram o campo **"Aceita receber comunicações da escola"** no próprio cadastro (ficha de Pessoa) — veja a seção 4.1. Isso não afeta notificações individuais obrigatórias (boletim, ocorrências, financeiro), que continuam sendo enviadas normalmente.
6. Ao terminar o processamento, o remetente recebe uma notificação no sininho com o resumo (total de enviados e falhas). Em caso de interrupção inesperada do job, o status é alterado para **Falhou** e o remetente é alertado imediatamente. Uma comunicação já enviada não pode ser reenviada nem editada; para repetir o envio, crie uma nova.

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
3. **Nacionalidade:** O cadastro possui dois campos distintos exigidos pelo Censo Escolar (Educacenso) do INEP: a **Classificação de Nacionalidade** (Brasileira, Brasileira nascida no exterior/Naturalizada ou Estrangeira) e o **País de Nacionalidade** (o país propriamente dito). Ambos são enviados separadamente no Educacenso e por isso não devem ser confundidos.
4. **Endereços e Automação via CEP:** Na aba de endereços, você pode vincular um ou mais endereços à pessoa.
   - **Agilidade no Preenchimento:** Comece digitando o **CEP**. Ao sair do campo (ou pressionar TAB), o sistema consulta automaticamente a base do **ViaCEP** e preenche para você o **Logradouro**, **Bairro** e a **Cidade/Estado**.
   - **Tipos de Endereço:** Escolha o **Tipo** (ex: Residencial ou Comercial) e complete com o **Número** e **Complemento** (apartamento, bloco, etc).
5. **Foto:** Use o editor integrado para ajustar a foto de perfil.
6. **Aceita receber comunicações da escola:** ativado por padrão. Desative se a pessoa pedir para não receber e-mails de comunicação em massa (campanhas, avisos gerais — veja a seção 3.9). Notificações individuais obrigatórias (boletim, ocorrências, financeiro) não são afetadas por essa opção.

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

### 4.4 Necessidades Especiais, Transtornos e Recursos de Acessibilidade
Na tela de edição de qualquer **Pessoa**, o sistema oferece abas dedicadas para o acompanhamento pedagógico e de inclusão:
1. **Necessidades de Educação Especial:** Permite registrar e gerenciar as necessidades do aluno (ex: *Baixa visão, Surdez, TEA, Altas habilidades ou superdotação*), incluindo observações complementares.
2. **Transtornos de Aprendizagem:** Permite categorizar transtornos diagnosticados ou observados (ex: *Discalculia, Dislexia, TDAH, TPAC*), com campo livre para detalhar pareceres e observações.
3. **Recursos de Acessibilidade:** Permite definir os recursos de apoio que a pessoa necessita no dia a dia ou em exames/avaliações (ex: *Tradutor-intérprete de Libras, Prova ampliada (Fonte 18), Auxílio ledor, Prova em Braille, Tempo adicional*).

---

## 🎓 5. Acadêmico — Ensino e Avaliação

### 5.1 Filtros e Gestão de Avaliações
1. Vá em **Avaliações → Avaliações**.
2. **Filtros Avançados:** Utilize a barra de filtros para localizar registros com precisão:
   - **Múltipla Seleção:** Os filtros de Categoria, Turma, Disciplina, Etapa e Professor permitem selecionar **várias opções simultaneamente**.
   - **Filtro de Período:** Use o filtro de **Data Prevista** para definir um intervalo (Data Inicial e Final) e visualizar apenas as avaliações agendadas para aquele período.
   - **Pendência de Lançamento:** Localize rapidamente provas ou trabalhos onde ainda faltam alunos sem nota lançada.
3. **Visibilidade Restrita:** Para garantir a organização e o foco pedagógico, usuários com o papel de **Professor** visualizam apenas as avaliações que estão vinculadas diretamente a eles. Administradores e secretaria continuam com acesso total.
4. **Edição em Lote:** Selecione uma ou mais avaliações na tabela e utilize a ação **Editar em Lote** para atualizar de uma só vez a Categoria, a Etapa, a Data Prevista ou a Nota Máxima de todos os registros selecionados.
5. Localize a prova/trabalho e utilize a ação de **Lançar Notas**.
6. **Padronização de Nomes:** Para facilitar a busca e identificação, as avaliações no sistema seguem o padrão de nome: `Categoria Avaliação - Turma - Disciplina - Etapa Avaliativa`.
7. **Restrição de Duplicidade (Prevenção de Erros):** O sistema impede a criação de avaliações duplicadas. Não é permitido salvar mais de uma avaliação com a mesma combinação de **Turma, Disciplina, Etapa Avaliativa, Categoria e Professor**. Caso tente cadastrar uma combinação idêntica, um aviso de validação será exibido e o registro não será salvo.
8. O sistema exibirá a lista de alunos matriculados na turma vinculada para preenchimento rápido.
9. **Botão de Ajuda nas Telas de Cadastro, Edição e Lançamento de Notas:** Ao criar, editar uma avaliação ou lançar notas, você terá à disposição um botão de **Ajuda** (ícone de interrogação cinza) no canto superior direito do cabeçalho da página. Clicando nele, você poderá consultar as instruções específicas ou ações que seu usuário tem permissão para realizar.
10. **Auditoria e Segurança:** Toda criação, alteração ou exclusão de avaliação é automaticamente auditada e registrada no log de atividades, permitindo identificar qual usuário realizou cada mudança.

### 5.2 Frequência Escolar e Cronograma de Aulas
1. **Widget de Pendências no Dashboard:** No painel principal (**Dashboard**), o sistema exibe o widget **Pendências de Lançamento de Frequência**, agrupando automaticamente todas as aulas com chamadas não lançadas ou incompletas em datas iguais a hoje ou anteriores.
   - **Lançamento Rápido em Lote por Dia:** Clique em **Lançar Chamada do Dia** no card da data correspondente para abrir um modal interativo.
   - **Configuração de Chamada em Lote:** O modal seleciona por padrão todas as matérias/aulas do dia e atribui **Presença** para todos os alunos matriculados. É possível desselecionar aulas específicas, desmarcar alunos ou alterar o status individual para **Ausente** antes de confirmar.
2. Em **Calendário e Horários → Cronograma de Aulas** (ou **admin/cronograma-aulas**), a listagem por padrão ativa o filtro **Frequência Pendente** (apenas pendentes), exibindo prioritariamente os cronogramas de aulas que possuem frequências ainda não lançadas ou incompletas.
3. Selecione o Cronograma de Aula do dia e utilize a ação **Frequência** para realizar o lançamento da chamada individual no diário de classe.
4. Marque as faltas ou presenças dos alunos. O padrão é "Presença".
5. **Auditoria e Segurança:** Toda ação de lançamento, alteração ou exclusão de frequência escolar (presença/ausência) é automaticamente auditada e registrada no log de atividades para controle e rastreabilidade dos lançamentos feitos por professores e administradores.
6. **Alerta de Falta ao Responsável:** Ao marcar um aluno como **Ausente** (na chamada individual ou em lote), o(s) responsável(is) cadastrado(s) recebem automaticamente um aviso por e-mail, sininho e push, com a disciplina e a data da falta. O alerta não é enviado para faltas lançadas em datas fora do período em que a matrícula estava ativa (ex: antes da ativação ou após o desligamento do aluno).

### 5.3 Boletim do Aluno
1. Na listagem de **Matrículas**, abra o menu **⋮ Mais ações** da linha e use a ação **Boletim** (aparece apenas quando o aluno já tem notas lançadas).
2. **Impressão de Boletim:** Na visualização do boletim de uma matrícula, agora é possível exportar o documento em PDF. Você pode escolher imprimir uma etapa específica (ex: 1º Bimestre) ou todas as etapas que já possuem notas registradas. O PDF gerado inclui uma **legenda detalhada das avaliações** (explicando o significado de cada coluna/categoria) e, logo abaixo, a **lista de datas das faltas** consolidadas daquela etapa para conferência da família.
3. O sistema gera uma tabela dinâmica por Etapa Avaliativa (Bimestre/Trimestre) mostrando as notas de cada disciplina e a média global.
4. Notas abaixo da média aparecem destacadas em vermelho.
5. **Frequência Escolar Acumulada:** A frequência do aluno é exibida de forma acumulativa de todo o período letivo (e não separada por etapa). Por essa razão, a coluna de frequência só é exibida na tabela da última etapa avaliativa do período letivo. Nela, o percentual reflete a presença global do aluno no ano/período, e ao passar o mouse sobre o valor, é exibida a listagem detalhada de todas as datas com faltas (DD/MM). Adicionalmente, antes da legenda do boletim, é apresentada uma listagem de frequências acumuladas por disciplina (ex: Inglês=100%; Matemática=85%). Para as disciplinas com frequência inferior a 100%, ao passar o mouse sobre o valor, um balão explicativo (tooltip) detalha as datas em que o aluno faltou.
6. **Edição de Notas:** Caso possua a permissão necessária, você visualizará o botão **Editar Notas** no topo da página do boletim. Esta tela permite o preenchimento rápido de todas as notas da etapa em um layout idêntico ao de consulta.
7. **Impressão de Boletins por Turma (Individual e em Lote):** No menu **Acadêmico → Turmas**, usuários que possuam permissão de visualização de boletim (`Boletim:Matricula`) podem emitir e baixar todos os boletins dos alunos ativos da(s) turma(s) em um único arquivo PDF consolidado.
   - **Para uma única turma:** Clique na ação **Imprimir Boletins** na respectiva linha da turma. O sistema abrirá um modal questionando se deseja filtrar por uma etapa avaliativa específica ou gerar todas as etapas juntas.
   - **Para múltiplas turmas em lote:** Selecione as turmas desejadas na tabela através das caixas de seleção, abra as ações em lote e clique em **Imprimir Boletins em Lote**. O mesmo modal de filtro por etapa será exibido. O PDF gerado realiza a quebra de página automática por aluno para facilitar a impressão física.

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
5. **Avaliações de Habilidades (Segregado):**
   - **Avaliações de Habilidades:** Vá em **Avaliações → Avaliações por Habilidades** para criar o cabeçalho de avaliação, onde se vincula apenas a **Turma**, a **Etapa Avaliativa** e o **Professor**.
   - **Lançar Notas (Lançamento em Lote):** Na lista de **Avaliações de Habilidades**, clique no botão **Lançar Notas** na linha da avaliação. Na tela que se abre:
      - O sistema apresentará seções colapsadas para cada aluno matriculado na turma da avaliação. Clique na seção correspondente ao aluno para expandir.
      - Dentro da seção do aluno, são exibidas todas as habilidades vinculadas à turma. Defina o **Conceito** para cada habilidade e, opcionalmente, insira uma **Observação Pedagógica**.
   - **Notas de Habilidades:** Para gerenciar ou cadastrar notas individuais, vá em **Avaliações → Notas de Habilidades**:
     - Selecione a **Avaliação de Habilidade**.
     - Selecione a **Habilidade**.
     - Selecione o **Aluno** (filtrado para a turma da avaliação).
     - Defina o **Conceito** e as observações pedagógicas.

### 5.6 Configuração de Disciplinas e Ordenação no Boletim
1. Vá em **Acadêmico → Disciplinas**.
2. No cadastro da disciplina, utilize o campo **Ordem no Boletim**.
3. **Funcionamento:** O sistema utiliza este número inteiro para ordenar as disciplinas de cima para baixo na visualização do boletim. Disciplinas com números menores (ex: 1, 2, 3) aparecem primeiro.
4. Caso duas disciplinas tenham o mesmo número de ordem, elas serão exibidas por ordem alfabética de nome.
5. **Restrição de Integridade (Notas Lançadas):** Não é permitido editar ou excluir uma disciplina que já possua notas lançadas em alguma de suas avaliações. Ao tentar salvar ou excluir, o sistema exibirá um aviso informando a restrição. Isso vale tanto para a exclusão individual quanto para a exclusão em lote.

### 5.7 Situações e Datas da Matrícula (Padronização)
As situações de matrícula no Torre360 são fixas e padronizadas para garantir a consistência dos relatórios. Cada estado possui uma cor e ícone específicos na listagem:
- **Ativa (Verde):** Aluno regularmente matriculado e frequentando.
- **Reserva (Cinza):** Vaga reservada (pré-matrícula) aguardando efetivação.

Além da situação, a **Matrícula** registra as seguintes datas de acompanhamento:
- **Data de Ativação:** Data em que a matrícula foi ativada e o vínculo letivo com o aluno iniciou.
- **Data de Desativação:** Data em que a matrícula foi encerrada, desativada, trancada, cancelada ou concluída.

- **Regra de Pendência de Chamada por Data:** Para ser contabilizado no diário de classe e na pendência de lançamento de chamada de um determinado Cronograma de Aula, o aluno deve ter sua **Data de Ativação menor ou igual à data da aula** e (**Data de Desativação posterior à data da aula ou nula**). Se um aluno for matriculado na data X, ele só contabilizará presença/falta e pendência a partir da data X, não sendo cobrado em aulas de datas anteriores (X-1, X-2, etc.).

- **Permissão de Chamada do Professor Regente:** O professor regente/conselheiro da turma (definido no cadastro da turma) possui autorização total para visualizar pendências e realizar o lançamento de frequência para **todas as aulas da sua turma**, mesmo quando a aula estiver cadastrada no nome de outro professor ou ministrada por um docente convidado/substituto.

**Edição em Lote:** Na listagem de Matrículas (`/admin/matriculas`), é possível selecionar múltiplas matrículas e acionar a ação **Editar em Lote** para atualizar simultaneamente os campos de **Turma**, **Período Letivo**, **Situação**, **Data de Ativação** e **Data de Desativação**.

### 5.7.1 Listagem de Matrículas (`/admin/matriculas`)
1. **Cartões de resumo (topo):** mostram, para o que está na lista no momento (aba, busca e filtros), quantas matrículas há, quantas têm **pendências**, quantas estão **sem responsável**, quantas estão com **contrato não gerado** e quantas estão com **contrato não assinado**.
2. **Abas de situação, com contador:** *Ativas* (aba que abre por padrão), *Pendentes*, *Com pendências*, *Trancadas*, *Concluídas*, *Canceladas*, *Reserva*, *Evasão* e *Todas*. *Trancadas*, *Concluídas*, *Reserva* e *Evasão* só aparecem quando existem matrículas nessa situação. A aba **Com pendências** reúne as matrículas ativas ou pendentes com algum problema a resolver (aluno sem responsável, cadastro incompleto, documentos obrigatórios faltando/rejeitados, **contrato não gerado** ou **contrato não assinado**) e é a lista de trabalho da secretaria. Não há mais filtro de situação separado: as abas fazem esse papel.
3. **Colunas visíveis:**
   - **Aluno:** nome e, logo abaixo, a turma e o curso. O nome leva à ficha da pessoa (conforme a permissão do usuário). A busca procura pelo nome do aluno ou da turma.
   - **Situação:** badge colorido com ícone.
   - **Pendências:** um badge para cada tipo de problema (*Sem responsável*, *Cadastro incompleto*, *N documentos faltando*, *N documentos rejeitados*, *Contrato não gerado*, *Contrato não assinado*), **empilhados um embaixo do outro** para a coluna ficar estreita e as ações da linha continuarem visíveis sem rolar a tabela para o lado, ou **Em dia** (verde). Passe o mouse para ver o resumo e clique para abrir o detalhe, com links diretos para corrigir (veja a seção 6.3.1). A linha **não** muda de cor.
   - **Período Letivo** e **Contrato** (ícone verde quando a matrícula já tem contrato gerado; cinza quando não tem).
   - **Colunas opcionais (seletor de colunas):** Turma (para ordenar por ela), Data de Ativação, Data de Desativação, Criada em e Atualizada em.
4. **Filtros** (recolhidos acima da tabela e lembrados durante a sessão): **Curso**, **Turma** e **Período Letivo** (os dois últimos aceitam várias opções), **Pendências** (escolha um ou mais tipos, incluindo *Contrato não gerado* e *Contrato não assinado*; aparecem as matrículas que tenham qualquer um deles) e **Contrato** (com/sem contrato, em qualquer situação).
5. **Ações em cada linha:** *Editar* e *Documentos* (com o número de documentos faltando em um badge vermelho) aparecem como ícones; as demais ficam no menu **⋮ Mais ações**, agrupadas por assunto: *Boletim*; *Avisar pendência por e-mail* e *Avisar preceptoria por e-mail*; e *Gerar contrato*. Cada ação só aparece quando se aplica à matrícula e ao perfil do usuário.
6. **Ações em lote:** avisar pendências, avisar preceptoria, **Editar em Lote** e excluir (a exclusão fica por último, em vermelho).
7. **Tela de Matrículas dentro da Turma:** a aba *Matrículas* da edição de uma turma usa a mesma tabela e mantém o filtro **Situação** (padrão: *Ativa*), pois ali não há abas.

### 5.8 Gestão e Cadastro de Turmas (Campos do Educacenso / INEP)
1. Vá em **Acadêmico → Turmas**.
2. **Cadastro e Edição de Turmas:** Ao cadastrar ou atualizar uma turma, é possível preencher:
   - **Código:** Código identificador da turma (código INEP/Educacenso ou controle interno).
   - **Etapa de Ensino Agregada:** Seleção do agrupamento macro da etapa conforme classificação do Educacenso (ex: *301 - Educação Infantil*, *302 - Ensino Fundamental*, *304 - Ensino Médio*, etc.).
   - **Etapa de Ensino:** Seleção da etapa específica vinculada à Etapa Agregada selecionada (ex: *14 - Ensino fundamental de 9 anos - 1º Ano*, *25 - Ensino médio - 1ª Série*, etc.). O seletor é filtrado dinamicamente com base na Etapa Agregada escolhida.
   - **Tipo de mediação didático-pedagógica:**
     - `1 - Presencial`
     - `2 - Semipresencial`
     - `3 - Educação a distância – EAD`
   - **Tipo de turma:**
     - `4 - Atividade complementar`
     - `5 - Atendimento educacional especializado (AEE)`
     - `6 - Curricular (etapa de ensino)`
     - `9 - Curricular (etapa de ensino) com Atividade Complementar`
   - **Local de funcionamento diferenciado da turma:**
     - `0 - A turma não está em local de funcionamento diferenciado`
     - `1 - Sala anexa`
     - `2 - Unidade de atendimento socioeducativo`
     - `3 - Unidade prisional`
   - **Forma de Organização da Turma (Educacenso 2026):**
     - `1 - Série/Ano (Série Anual)`
     - `2 - Períodos semestrais`
     - `3 - Ciclos`
     - `4 - Grupos não seriados com base na idade ou competência`
     - `5 - Módulos`
     - `6 - Alternância regular de períodos de estudos`
   - **Modalidade de Ensino:**
     - `1 - Ensino Regular`
     - `2 - Educação Especial`
     - `3 - Educação de Jovens e Adultos (EJA)`
     - `4 - Educação Profissional`
   - **Língua em que o Ensino é Ministrado & Bilíngue Surdos:**
     - Escolha entre `1 - Somente em Língua Portuguesa`, `2 - Em Língua Indígena e Língua Portuguesa` ou `3 - Somente em Língua Indígena` (habilita o campo de Código da Língua Indígena do INEP).
     - Marque a flag **Turma de Educação Bilíngue de Surdos** se aplicável.
   - **Atendimento Educacional Especializado (AEE - Educacenso 2026):**
     - Para turmas do tipo AEE ou de Educação Especial, o painel disponibiliza seletores para marcar os recursos aplicados (ex: *Ensino de Libras, Sorobã, Informática Acessível, Comunicação Alternativa e Aumentativa (CAA), Tecnologia Assistiva, Processos Cognitivos, Enriquecimento Curricular, Português como 2ª Língua e Orientação e Mobilidade*).
   - **Turma de Educação Especial (Classe Especial):** Seleção para identificar turmas de classe especial.
   - **Carga Horária Total (horas):** Carga horária total da turma definida em horas.
   - **Horário de Funcionamento (Dias da Semana):** Quadro colapsável em largura total que permite definir e visualizar os horários de início e término fixos para todos os dias da semana (Domingo a Sábado).
3. **Edição em Lote:** Na listagem de turmas, selecione duas ou mais turmas e clique no botão **Editar em Lote** nas ações em lote. Isso permite atualizar de uma só vez a Série, Turno, Etapa Agregada, Etapa de Ensino, Professor Conselheiro, Vagas, Carga Horária Total, Tipo de Avaliação, Tipo de Mediação, Tipo de Turma, Local Diferenciado, Forma de Organização, Modalidade, Língua Ministrada ou Flags de Educação Especial e Bilíngue de Surdos.
4. **Exportar para Educacenso em Lote:** Na listagem de turmas (`/admin/turmas`), selecione uma ou mais turmas e acione a ação em lote **Exportar para Educacenso**. O sistema gerará e baixará automaticamente um arquivo `.txt` configurado no padrão oficial do INEP (Registro 20) separado por Pipe (`|`), com campos não preenchidos representados por delimitadores vazios (ex: `||`).
5. **Ajuda e Responsividade:** Todas as telas da gestão de turmas incluem o botão de **Ajuda** no cabeçalho e exibição adaptada para celulares em formato de lista/cards.
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

### 5.9 Lançamento de Notas em Grade (Lançamento Rápido)
Para agilizar o lançamento de uma avaliação já cadastrada, sem precisar navegar até a listagem de Avaliações e abrir cada registro individualmente:
1. Vá em **Avaliações → Lançamento de Notas em Grade**.
2. Selecione, em sequência, a **Turma**, a **Disciplina** (filtrada apenas pelas disciplinas com avaliações naquela turma) e a **Avaliação** (filtrada pela combinação de turma e disciplina escolhida).
3. Assim que uma avaliação é selecionada, o sistema exibe uma grade com todos os alunos matriculados na turma e um campo de nota para cada um, já preenchido com o valor existente (se houver).
4. Digite as notas e pressione **Enter** em qualquer campo, ou clique em **Salvar Notas** no topo da página, para gravar tudo de uma vez.
5. O sistema valida que nenhuma nota ultrapasse a **Nota Máxima** configurada na avaliação; deixar um campo em branco remove a nota daquele aluno, caso já exista uma.
   - **Faltou / Não se aplica:** Ao lado de cada aluno há o campo **Situação**. Use **Faltou** quando o aluno não fez a avaliação, ou **Não se aplica** quando a avaliação é destinada apenas a alguns alunos (ex.: prova de adaptação ou de segunda chamada). Ao escolher uma situação, o campo de nota é limpo e bloqueado. O aluno deixa de aparecer como **pendência de lançamento** e a avaliação **não entra na média** dele. Para voltar a lançar nota, selecione **Com nota** no campo Situação. Se o aluno ficar sem nota e sem situação, ele continua como pendente.
   - O mesmo campo **Situação** existe na tela **Lançar Notas** aberta a partir da listagem de Avaliações.
6. **Visibilidade Restrita:** Assim como na listagem de Avaliações, professores só conseguem selecionar turmas, disciplinas e avaliações vinculadas a si mesmos. Administradores, secretaria e coordenação têm acesso a todas.

### 5.10 Fechamento do Ciclo Letivo e Situação Final
Rotina de fechamento que consolida as etapas avaliativas de cada disciplina e define automaticamente a situação final do aluno (Aprovado, Recuperação ou Reprovado).
1. Vá em **Acadêmico → Fechamento do Ciclo Letivo**.
2. Selecione o **Período Letivo** e, opcionalmente, uma **Turma** específica (deixe em branco para processar todas as turmas do período de uma vez).
3. Clique em **Calcular Situação Final**. O sistema irá, para cada aluno com matrícula ativa ou concluída e cada disciplina de sua turma:
   - Calcular a **média simples** entre as médias de todas as Etapas Avaliativas (bimestres/trimestres) do período letivo.
   - Se existir uma categoria de avaliação marcada como **"É recuperação final?"** (configurável em **Avaliações → Categorias de Avaliação**) com nota lançada para o aluno, essa nota substitui média(s) de etapa conforme o modo de recuperação configurado no Período Letivo (item 5.10.1 abaixo).
   - Classificar o resultado conforme as notas de corte configuradas no cadastro do **Período Letivo** (campos **Nota Mínima para Aprovação**, padrão 7,0, e **Nota Mínima para Recuperação**, padrão 5,0): média igual ou acima da nota de aprovação = **Aprovado**; entre a nota de recuperação e a de aprovação = **Recuperação**; abaixo da nota de recuperação = **Reprovado**.
4. O resultado é exibido na própria página, agrupado por turma, com aluno, disciplina, média final e um selo colorido de situação (verde = Aprovado, amarelo = Recuperação, vermelho = Reprovado).
5. **Gravação Atômica:** Cada cálculo é processado e persistido dentro de uma transação protegida (`DB::transaction`). Um registro é salvo por aluno/disciplina/período letivo. Rodar o fechamento novamente para o mesmo período **recalcula e substitui** o resultado anterior de forma integral (com rollback automático se houver erro imprevisto durante o processamento em lote) — garantindo consistência total após correções de notas. O registro de exame final também segue o mesmo padrão transacional seguro.
6. **Turmas por Habilidades:** Turmas configuradas com Tipo de Avaliação `Habilidades` não entram no fechamento, pois não possuem notas numéricas — apenas turmas `Notas` ou `Híbrido`.

#### 5.10.1 Recuperação Anual ou por Etapa

No cadastro do **Período Letivo**, o toggle **"Recuperação por etapa (em vez de recuperação anual)"** define como as notas de recuperação são aproveitadas:

- **Desligado (padrão) — recuperação anual:** todas as avaliações de recuperação do período, não importa a etapa em que foram lançadas, são somadas num único valor, que substitui a **menor** média de etapa do aluno (se for melhor que ela). Só uma etapa é recuperada.
- **Ligado — recuperação por etapa:** cada nota de recuperação só pode substituir a média da **própria etapa** em que foi lançada. Permite recuperar mais de uma etapa de forma independente (por exemplo, uma recuperação logo depois de cada bimestre fechar).

#### 5.10.2 Exame Final

Ainda no cadastro do **Período Letivo**, o toggle **"Permitir exame final"** habilita um segundo mecanismo, além (ou no lugar) da recuperação: quando ligado, aparece também o campo **"Nota Mínima para Aprovação após o Exame Final"** (padrão 5,0).

1. Depois de calcular a situação final, disciplinas que ficaram em **Recuperação** mostram um botão **Lançar Exame Final** na própria tabela de resultados (coluna "Exame Final").
2. Ao clicar, informe a **Nota do Exame Final** (0 a 10) e salve.
3. O sistema calcula a **média simples entre a média do período e a nota do exame** e compara com a nota mínima pós-exame configurada — o resultado é sempre **Aprovado** ou **Reprovado** (não existe uma segunda recuperação).
4. O resultado aparece na mesma coluna como um selo colorido com a média pós-exame entre parênteses, no lugar do botão — não é possível lançar o exame final duas vezes para a mesma disciplina.
5. Recalcular o fechamento **preserva** o exame final já lançado se a disciplina continuar em Recuperação; se a nova situação deixar de ser Recuperação (por exemplo, depois de uma correção de nota), o exame lançado é descartado, pois deixou de fazer sentido.
6. O resultado pós-exame passa a valer também no **Histórico Escolar** emitido pela Secretaria Digital (seção 31.1) — a disciplina aparece com a situação definitiva, não com "Recuperação".

---

## 📝 6. Secretaria e Documentação

### 6.0 Assistente de Matrícula (Wizard)
O **Assistente de Matrícula** (`Acadêmico → Nova Matrícula (Wizard)`) é a forma mais rápida e guiada de registrar um ou mais alunos com todos os seus vínculos familiares em um único fluxo de 3 etapas.

#### Etapa 1 — Dados do(s) Aluno(s)
- Utilize o campo **CPF** para autocompletar dados de uma pessoa já cadastrada. Se o cadastro for encontrado, os campos são preenchidos automaticamente e o aluno existente é reutilizado (sem duplicação).
- O formulário permite cadastrar **múltiplos alunos** na mesma matrícula (irmãos, por exemplo) clicando em **"Adicionar Aluno"**. Todos compartilharão os mesmos responsáveis.
- O cabeçalho do item no Repeater exibe o **nome do aluno** conforme é preenchido, para fácil identificação.
- **Criar conta de acesso:** Ao preencher o campo **E-mail**, aparecerá um checkbox **"Criar conta de acesso para esta pessoa?"**. Se marcado, o sistema criará automaticamente um usuário com o papel **`aluno`** e enviará um e-mail de boas-vindas com a senha gerada para o endereço informado.

> [!NOTE]
> Se já existir um usuário cadastrado com o e-mail informado, o sistema apenas vinculará a Pessoa a esse usuário e garantirá que o papel `aluno` esteja atribuído.

#### Etapa 2 — Pais / Responsáveis
- Adicione um ou mais responsáveis e defina o **vínculo** (Pai, Mãe, Tutor etc.) e se é **Responsável Financeiro** (e o percentual correspondente).
- O campo **CPF** também busca automaticamente um responsável já cadastrado. Responsáveis existentes **não são duplicados**: o sistema os encontra e vincula diretamente.
- Os responsáveis cadastrados serão vinculados a **todos os alunos** adicionados na Etapa 1.
- **Criar conta de acesso:** Igualmente ao aluno, se o responsável possuir e-mail e o checkbox estiver marcado, será criado um usuário com o papel **`responsavel`**.

#### Etapa 3 — Plano e Matrícula
- Selecione a **Unidade / Escola** e o **Período Letivo** (pré-selecionado com o período mais recente).
- Selecione o **Curso** — a lista de turmas é filtrada automaticamente pela unidade e curso escolhidos.
- Selecione a **Turma** — o campo exibe a quantidade de **vagas ocupadas / vagas totais** (ex: `3/30 vagas`) e marca turmas lotadas com 🔴. Não é possível concluir a matrícula se a turma estiver cheia.
- Defina a **Situação Inicial** da matrícula: `Ativa` (padrão), `Pendente` (aguardando documentação/pagamento) ou `Reserva`.
- Defina a **Data de Ativação** (preenchida automaticamente com a data de hoje).
- Ao clicar em **"Finalizar Matrícula"**, o sistema criará automaticamente:
  - As Pessoas (Aluno e Responsáveis) — **reutilizando cadastros existentes** se encontrados por CPF.
  - Uma Matrícula por aluno, com situação, período letivo e data de ativação corretos.
  - Um Contrato vinculado a cada matrícula.
  - Os usuários de acesso (se solicitado), com envio de e-mail de boas-vindas.
  - A **conversão automática no CRM**: se algum aluno possuía um cadastro de Interessado ativo no CRM, a `data_conversao` é registrada automaticamente.
- Após salvar, você é redirecionado para a tela de edição da primeira matrícula criada.

> [!TIP]
> Use o botão **Ajuda** (ícone de interrogação cinza) no canto superior direito para consultar instruções detalhadas sobre o wizard a qualquer momento.

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
8. **Filtro Padrão:** Para facilitar o dia a dia, a listagem de matrículas exibe por padrão apenas os alunos com **Situação: Ativa**. Caso precise consultar alunos em outras situações (como Trancado ou Cancelado), utilize a barra de filtros da tabela.
9. **Atalho para Ficha do Aluno:** Na listagem de matrículas, ao clicar em cima do nome de um aluno, você será direcionado para o cadastro da Pessoa (Aluno). O redirecionamento respeita as permissões do seu usuário: se possuir permissão de edição, abrirá em modo de edição; se possuir apenas permissão de visualização, abrirá em modo de visualização; se não possuir nenhuma destas permissões, o nome não será clicável.

### 6.1.1 Importação e Exportação de Contratos em Lote

Para facilitar a gestão em larga escala, o sistema permite exportar e importar contratos em lote por meio de planilhas eletrônicas.

#### 📤 Como Exportar
1. Acesse **Financeiro → Contratos**.
2. Para exportar a listagem completa de contratos, clique no botão **Exportar** no cabeçalho superior direito da página.
3. Para exportar apenas contratos específicos, selecione-os usando as caixas de seleção ao lado de cada registro na tabela, acesse o menu de **Ações em Lote** e escolha **Exportar Selecionados**.
4. O arquivo gerado conterá as informações fundamentais de cada contrato, incluindo os IDs das tabelas relacionadas (`matricula_id` e `template_contrato_id`) e os nomes de exibição amigáveis correspondentes para facilitar o preenchimento manual posterior.

#### 📥 Como Importar
1. Acesse **Financeiro → Contratos** e clique no botão **Importar** no cabeçalho superior direito.
2. Selecione o arquivo com os contratos no formato da planilha (como CSV ou Excel) e faça o upload.
3. **Mapeamento e Atualização Inteligente:**
   - **ID Existente:** Se a planilha contiver a coluna de ID com um valor que já existe no banco de dados, o respectivo contrato terá suas informações atualizadas.
   - **Novo Contrato:** Caso o ID seja omitido ou não corresponda a nenhum registro no sistema, um novo contrato será criado.
   - **Resolução de Chaves Estrangeiras:** O importador associa automaticamente o contrato à matrícula do aluno e ao modelo do contrato. Caso os códigos de ID (`matricula_id` ou `template_contrato_id`) não sejam informados, mas o nome do aluno (`matricula_aluno_nome`) ou o nome do modelo (`template_contrato_nome`) estejam presentes, o sistema resolverá as relações buscando os registros correspondentes de forma transparente.

#### 🛡️ Confirmação ao Salvar e Criar Contratos
Ao criar um novo contrato ou salvar as alterações em um contrato existente, o sistema exibirá obrigatoriamente um **modal de confirmação**. 
- Se o contrato já foi enviado para a plataforma de assinatura digital (Assinafy), o modal informará que salvar as alterações limpará os dados da assinatura anterior, exigindo uma nova submissão.
- Para novos contratos ou contratos não submetidos, o modal solicitará a confirmação simples antes de efetivar o salvamento.

### 6.5 Alerta de Preceptoria Disponível
Para garantir que todos os alunos aproveitem os momentos de preceptoria, o sistema monitora a agenda dos professores.
1. **Aviso Individual:** Na lista de **Matrículas**, o sistema exibirá automaticamente a ação **Avisar preceptoria por e-mail** (ícone de calendário verde, no menu **⋮ Mais ações** da linha) se:
   - A matrícula do aluno não possuir nenhuma preceptoria agendada dentro dos **ciclos de preceptoria vigentes** (aqueles cuja data atual esteja entre o início e o fim do ciclo).
   - Existirem horários vagos (janelas) cadastrados por professores no sistema.
2. Ao clicar no botão, o sistema solicitará confirmação, exibirá a **data e hora do último envio realizado** (se houver) e listará os e-mails do aluno e responsáveis que receberão o alerta.
3. **Avisar Preceptoria em Lote (Ação em Lote):** É possível enviar alertas para múltiplas matrículas simultaneamente na tela de **Matrículas**:
   - Selecione uma ou mais matrículas na tabela utilizando as caixas de seleção.
   - Acesse o menu de **Ações em Lote** e clique em **Avisar Preceptoria em Lote**.
   - **Regra de Proteção:** Caso alguma das matrículas selecionadas já possua agendamento de preceptoria feito em ciclos vigentes, o sistema ignorará automaticamente o envio para ela, garantindo que responsáveis com agendamentos já realizados não recebam avisos desnecessários.
   - O sistema emitirá um resumo detalhado ao final informando quantas matrículas foram notificadas, quantas foram ignoradas por já possuírem agendamento ou por falta de janelas/e-mail cadastrado.
4. O aviso incentiva a família a acessar o painel e realizar o agendamento no horário de sua preferência.

### 6.5.1 Cadastro e Criação de Preceptorias em Lote (Múltiplas Datas e Ranges)
1. Vá em **Preceptoria → Preceptorias** e clique em **Criar Preceptoria** (`/admin/preceptorias/create`).
2. No formulário de criação, selecione o **Ciclo de Preceptoria**, a **Hora Início**, a **Hora Fim**, o **Professor** responsável e, opcionalmente, a **Matrícula / Aluno**.
3. **Modo de Seleção de Datas:** Escolha entre as duas modalidades disponíveis:
   - **Datas Específicas (Avulsas):** Adicione uma ou mais datas específicas clicando em *"Adicionar outra data"*.
   - **Intervalo de Datas (Range):** Informe uma **Data Inicial** e uma **Data Final**. Opcionalmente, marque os **dias da semana** desejados (ex: *Terça-feira e Quinta-feira*) para que o sistema gere instâncias de preceptoria apenas para esses dias dentro do período.
4. Ao salvar, o sistema criará automaticamente uma instância de preceptoria separada para cada dia gerado ou selecionado.
5. **Ajuda Integrada:** Em todas as páginas da gestão de preceptorias (Listagem, Cadastro, Edição e Agendamento), utilize o botão de **Ajuda** no cabeçalho para obter orientações específicas da tela.

### 6.2 Gestão de Documentos
1. Cada Matrícula possui uma lista de documentos necessários (RG, CPF, Histórico Escolar).
2. Vá na aba **Documentos** da matrícula para fazer o upload dos arquivos.
3. O sistema utiliza uma **Máquina de Estados** para gerir a situação do documento:
   - **Pendente:** Documento enviado mas ainda não revisado.
   - **Em Análise:** Documento em processo de conferência pela secretaria.
   - **Aprovado:** Documento validado e aceito.
   - **Rejeitado:** Documento com problemas (ilegível, errado, etc).
4. As transições de estado são controladas; por exemplo, um documento *Aprovado* não pode voltar para *Pendente* sem passar por uma revisão, garantindo a integridade do processo.

### 6.3 Aviso de Pendência de Documentos
1. Na lista de **Matrículas**, caso o aluno possua documentos obrigatórios pendentes ou rejeitados, você verá a ação **Avisar pendência por e-mail** (ícone de envelope amarelo, no menu **⋮ Mais ações** da linha).
2. Ao clicar no botão, um modal de confirmação exibirá:
   - A **data e hora do último envio** de aviso realizado (se houver).
   - A lista de e-mails dos destinatários.
   - A lista detalhada de quais documentos estão faltando e quais foram rejeitados (com o respectivo motivo da rejeição).
3. Esta funcionalidade permite manter a família informada sobre a necessidade de regularização documental para efetivação da matrícula.

### 6.3.1 Consulta de Pendências da Matrícula
1. Na lista de **Matrículas**, a coluna **Pendências** mostra um badge para cada problema da matrícula: **Sem responsável** (aluno sem Pai/Mãe/Responsável), **Cadastro incompleto** (dados básicos incompletos ou falta de endereço no **Aluno**, nos **Responsáveis** ou no **Responsável Financeiro** do contrato), **documentos faltando**, **documentos rejeitados**, **Contrato não gerado** e **Contrato não assinado**. Matrículas sem nenhum problema mostram **Em dia**. Ao passar o mouse, um resumo textual das pendências é exibido.
2. Ao clicar na coluna, um modal se abre listando em destaque todas as pendências daquela matrícula:
   - **Responsável não informado:** alerta de que não há responsáveis vinculados à ficha do aluno, com atalho para a edição rápida do cadastro de pessoa.
   - **Cadastro incompleto:** detalhamento para cada pessoa vinculada (identificando se é o Aluno, o Pai, a Mãe ou o Responsável Financeiro), listando os campos ou o endereço ausentes, com link direto para abrir a ficha de edição daquela pessoa.
   - **Documentos pendentes:** os documentos obrigatórios que estão faltando e os rejeitados (com o motivo), com um link rápido para a gestão de documentos da matrícula.
   - **Contrato não gerado:** a matrícula ainda não tem contrato. O modal orienta a usar a ação **Gerar contrato** (menu **⋮ Mais ações** da linha, disponível quando o aluno tem responsável) ou a criar o contrato manualmente.
   - **Contrato não assinado:** o contrato foi gerado, mas ainda não foi assinado (a assinatura ainda não foi concluída por todos; veja a seção 6.3). O modal traz um link para abrir o contrato.
   - **Regra:** as duas pendências de contrato valem apenas para matrículas **Ativas** ou **Pendentes**; matrículas Canceladas, Concluídas, Trancadas, em Reserva ou em Evasão não são cobradas. A verificação é nesta ordem: primeiro o contrato precisa ser gerado e, depois, assinado.
3. Para tratar um tipo de problema por vez, use a aba **Com pendências** e o filtro **Pendências** (veja a seção 5.7.1). O fundo da linha não é mais pintado de vermelho: a indicação fica concentrada na coluna **Pendências**.
4. No painel inicial, os cartões do widget **Matrículas com Pendências** levam à lista de Matrículas já na aba *Ativas* e com o filtro do tipo de pendência correspondente.

### 6.4 Visualização e Prévia de Documentos
1. Ao acessar a edição de um documento, o sistema exibe automaticamente uma **Prévia do Documento** (imagem ou PDF) logo abaixo do campo de upload.
2. Esta funcionalidade permite conferir o conteúdo do arquivo rapidamente sem a necessidade de downloads manuais.
3. Para PDFs, o navegador utiliza seu visualizador nativo integrado à página.

> [!NOTE]
> **Armazenamento e Acesso Seguro (LGPD):** Todos os documentos emitidos oficialmente (declarações, certificados) e os arquivos anexados pelos estudantes são armazenados em disco privado protegido. O acesso é estritamente controlado por regras de autorização do sistema: apenas a equipe da escola e o próprio estudante ou seus responsáveis legais podem visualizá-los ou baixá-los, garantindo a privacidade dos dados e a conformidade legal.

6.3 Assinatura Digital (Assinafy)
O sistema é integrado à plataforma **Assinafy** para assinatura digital de contratos.
1. Na lista de contratos, clique na ação **Assinar Contrato** para o documento pendente.
2. Você será direcionado para uma página de visualização. Clique em **Iniciar Assinatura Digital** para ser enviado ao portal do Assinafy.
3. **Múltiplos Signatários:** Se o contrato possuir mais de um Responsável Financeiro vinculado (com usuário cadastrado no sistema), **todos** receberão um convite de assinatura por e-mail automaticamente.
4. O texto do contrato exibe automaticamente:
   - **O aluno** do contrato (nome completo, data de nascimento, CPF, turma e série/ano).
   - **Todos os responsáveis financeiros** como CONTRATANTE. Seu endereço residencial será exibido prioritariamente; caso não possua, o sistema utilizará o primeiro endereço comercial vinculado.
   - O **valor total** e o **número de parcelas** com valor estimado por parcela.
5. Quando o contrato for assinado por todos os responsáveis, o Assinafy avisa o sistema e o status é atualizado automaticamente. Depois da última assinatura o contrato passa por três etapas, exibidas na coluna **Assinatura** com rótulos próprios: **Todos assinaram** (todas as assinaturas coletadas), **Certificando** (certificado digital em geração) e **Certificado** (etapa final). As três contam como contrato **assinado**: a ação passa a ser *Ver Contrato Assinado*, o contrato sai da lista de pendentes e a pendência **Contrato não assinado** da matrícula desaparece.
   - **Data de Aceite:** é a data em que a **última assinatura** foi coletada (quando todos assinaram), e não a data de criação do contrato.
   - **Atualização automática e manual:** o sistema consulta o Assinafy de hora em hora para recuperar avisos que não chegaram. Se quiser conferir na hora, use a ação **Sincronizar Assinaturas** do contrato.
   - Detalhes técnicos e procedimento de reparo em `docs/assinafy_assinatura_contratos.md`.
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
   - `{{ALUNO.TABELA}}`: Gera automaticamente a tabela com os dados do aluno (nome completo, data de nascimento, CPF, turma e série/ano) em formato vertical.
   - `{{RESPONSAVEIS_INFO}}`: Gera o texto qualificando os responsáveis financeiros.
   - `{{FATURAS_TABELA}}`: Gera uma tabela com o cronograma de parcelas e vencimentos.
5. **Template Padrão:** Marque a opção "Template Padrão" em um dos modelos para que ele seja selecionado automaticamente ao criar novos contratos.
6. **Seleção no Contrato:** No formulário de **Contratos**, você pode escolher qual template deseja utilizar para aquele contrato específico.
7. **Cabeçalho e Rodapé:** O formulário de edição/criação do template possui abas exclusivas para configuração de **Cabeçalho** e **Rodapé**. Nelas, você pode inserir imagens de logotipos, textos institucionais e informações da mantenedora utilizando o mesmo editor visual rico. Estes elementos serão aplicados automaticamente no topo e final do contrato (tanto na tela de visualização quanto no PDF enviado para assinatura digital). Além disso:
   - **Numeração de Páginas:** Você pode inserir as chaves `{PAGINA_ATUAL}` (ou `{PAGE_NUM}`) e `{TOTAL_PAGINAS}` (ou `{PAGE_COUNT}`) no cabeçalho ou rodapé para que a numeração seja calculada e renderizada dinamicamente em cada página do PDF final (ex: "Página {PAGINA_ATUAL} de {TOTAL_PAGINAS}").
   - **Imagens:** Imagens locais inseridas por upload no cabeçalho, rodapé ou conteúdo do contrato serão automaticamente processadas e codificadas em Base64 na geração do PDF para garantir sua renderização completa e sem falhas de carregamento.

### 6.7 Templates de Crachá V2 (Editor SVG)
O sistema conta com um novo módulo de criação de crachás versão 2 (V2), utilizando o editor vetorial SVG-Edit, permitindo maior flexibilidade e controle visual do desenho.
1. Vá em **Secretaria → Templates de Crachá V2**.
2. **Criação:** Defina o nome, tipo de entidade (Pessoa ou Turma) e as dimensões em pixels (Largura e Altura).
3. **Editor Canvas:** Após salvar o registro, clique na ação de linha **Editar Canvas** (ou no botão do formulário de edição) para abrir o editor gráfico do SVG-Edit em uma nova aba do navegador.
4. **Inserção de Campos Dinâmicos:** Na barra lateral do editor gráfico, utilize os botões rápidos para injetar tags especiais no centro do desenho, como:
   - `{nome}`, `{cpf}`, `{email}`, etc.
   - **Foto da Pessoa ({foto}):** Insere uma caixa de imagem que servirá como área reservada. No momento da impressão, o sistema substituirá a área pela foto real do aluno ou pessoa cadastrada.
5. **Ajuste e Salvamento:** Mova, rotacione ou altere cores dos elementos utilizando as ferramentas do SVG-Edit. Ao finalizar, clique no botão **Salvar Template** no cabeçalho superior.

### 6.8 Impressão de Crachás V2
A nova versão de impressão permite gerar crachás vetoriais baseados em SVG de forma rápida e com alta fidelidade visual.
1. Acesse as listagens de **Pessoas** ou de **Turmas**.
2. Selecione os registros desejados e utilize a ação em lote **Imprimir Crachá V2 (SVG)**.
3. Escolha o template V2 correspondente e clique para efetuar o download. O PDF resultante será gerado posicionando os crachás na grade do papel A4 de acordo com as dimensões especificadas no template.

---

## 💰 7. Financeiro Avançado

### 7.1 Faturas e Itens
Em vez de títulos estáticos, o Torre360 trabalha com **Faturas**.
1. Uma fatura pode conter múltiplos itens (Mensalidade + Taxa de Material + Uniforme).
2. As faturas podem ser geradas em lote a partir de contratos.
3. Na tela de edição de um Contrato (`Financeiro → Contratos → Editar`), na aba de Faturas Relacionadas, é possível visualizar, criar, editar e excluir faturas associadas a este contrato individualmente.

### 7.1.1 Gerar Faturas Automaticamente
Na tela de edição de um contrato (`Financeiro → Contratos → Editar`), utilize o botão **Gerar Faturas Automaticamente** para criar o parcelamento do contrato de forma rápida.

> [!NOTE]
> Esta funcionalidade só ficará visível e disponível para uso se o contrato **não possuir nenhuma fatura associada** no momento. Além disso, certifique-se de que o contrato possui uma **Data de Aceite** preenchida antes de prosseguir.

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
4. Utilize o botão de **Ajuda** (ícone de interrogação cinza) no cabeçalho para consultar um resumo dos filtros e da ação de atualização do relatório.

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
- **Acadêmico:** **Questionários**, **Respostas de Questionários**, **Avaliações de Habilidades** (BNCC), **Notas de Habilidades**, **Campos de Experiência** e **Habilidades**.


### 9.2 Auditoria de Ações
O sistema registra automaticamente ações críticas e navegações:
- **Navegações no Painel:** As visualizações de recursos realizadas por usuários com papéis de **Responsável**, **Professor** ou **Secretaria** são registradas automaticamente no Log de Atividades (`admin/activity-logs`) para controle de acessos.
- **Frequência Escolar:** Lançamentos, atualizações e exclusões de registros de presença e ausência de alunos são auditados detalhadamente, identificando o aluno, a aula, a disciplina e a situação atribuída (presente/ausente), com rastreamento das alterações e do usuário responsável pelo lançamento.
- **Módulo de Matrícula:** O sistema loga acessos à lista de matrículas e à tela de documentos.
- **Gestão de Documentos:** Uploads, substituições e exclusões de arquivos são auditados com identificação do usuário e data/hora.
- **Financeiro:** Alterações em transações e planos de contas são rastreadas para evitar inconsistências.
- **Notificações:** Tentativas de envio de mensagens Push (Firebase/FCM) e suas respectivas respostas do servidor são registradas no histórico da matrícula para depuração e acompanhamento técnico.

### 9.3 Ferramentas de Super Administrador
Páginas restritas exclusivamente a usuários com o papel **super_admin**, ambas com botão de **Ajuda** no cabeçalho:
- **Assistente de Configuração Escolar:** Wizard de 3 etapas (Escola, Calendário, Estrutura de Ensino) usado apenas na configuração inicial do sistema, quando ainda não há unidades cadastradas. Cria automaticamente a primeira Unidade, Período Letivo, Curso e Turma.
- **Git Pull (Atualizar Sistema):** Busca as últimas alterações do repositório (`git pull origin main`) e, em caso de sucesso, executa automaticamente `optimize:clear` e `migrate --force`. É uma ação sensível que afeta o ambiente de produção para todos os usuários — utilize com cautela.

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
- **Padronização de CPF:** Os campos de CPF em todo o sistema aceitam e exibem apenas os 11 dígitos numéricos (sem ponto ou traço). Qualquer digitação de máscara é tratada e os dados são salvos estritamente em formato numérico.
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
- **Falta do Aluno:** Enviada ao(s) responsável(is) sempre que uma falta é lançada para o aluno (veja a seção 5.2).
- **Lançamento de Frequência Pendente:** Alerta enviado aos professores quando uma aula em seu cronograma ainda não teve a frequência lançada. Agora também disponível via Push e Sininho.
- **Agendamento de Preceptoria:** Notificação multicanal disparada sempre que uma nova preceptoria é marcada ou cancelada. São notificados o solicitante, o professor, o aluno e seus responsáveis legais.
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

### 12.2 Visibilidade Dinâmica por Perfil (Role)
Para garantir a privacidade e organização, a visualização das aulas e avaliações no calendário se adapta automaticamente ao seu papel (role) ativo:
- **Professores:** Visualizam apenas as suas próprias aulas e avaliações agendadas, facilitando a gestão do seu dia a dia pedagógico.
- **Responsáveis:** Visualizam apenas o cronograma de aulas e avaliações das turmas onde seus dependentes (filhos) possuem matrícula ativa ou vínculo legal/financeiro.
- **Administradores/Secretaria:** Possuem visão global de todas as turmas, professores e disciplinas cadastradas.

### 12.3 Filtro por Período e Turma
1. Vá em **Calendário e Horários → Calendário de Aulas**.
2. Além do filtro de **Período** (Data Início/Fim), você pode utilizar os seletores de **Turmas**, **Disciplinas** e **Professores** no topo da página para refinar a visualização.
3. Clique em qualquer evento no calendário para abrir os detalhes completos da aula ou avaliação.

---

## 📍 13. Gestão de Instituições e Unidades
 
O sistema gerencia a estrutura da escola de forma hierárquica, permitindo que uma Instituição de Ensino possua múltiplas Unidades.
 
### 13.1 Instituição de Ensino
1. Vá em **Localização e Cadastros → Instituições de Ensino**.
2. **Cadastro:** Informe o nome da instituição, CNPJ e dados de contato.
3. **Logo e Identidade:** É possível fazer o upload da logo oficial da instituição. Esta imagem é utilizada no cabeçalho do **Boletim Escolar** e em outros relatórios oficiais.
4. **Redes Sociais:** Configure os perfis globais da instituição para redirecionamentos digitais.
 
### 13.2 Unidades
1. Vá em **Localização e Cadastros → Unidades**.
2. **Vínculo:** Ao cadastrar ou editar uma unidade, você deve associá-la a uma **Instituição de Ensino** cadastrada.
3. **Dados Específicos:** Configure o CNPJ, endereço e canais de contato exclusivos daquela unidade física.
 
### 13.3 Representantes Legais da Unidade
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
2. **Geral:** Defina o título, descrição, o período em que o questionário ficará disponível para preenchimento, e o **Máximo de Respostas por Usuário** (por padrão é 1. Deixe em branco para permitir respostas ilimitadas/infinitas).
3. **Privacidade:** Marque a opção **Respostas Anônimas** caso deseje que a identidade do respondente seja preservada nos relatórios. Observação: para questionários anônimos, a limitação de número de respostas por usuário logado não se aplica.
4. **Público-Alvo:** Utilize a aba de público para restringir quem deve responder. Você pode filtrar por:
   - Uma **Unidade** específica.
   - Um **Curso**, **Série** ou **Turma**.
   - Por **Perfil/Role** (ex: apenas Professores ou apenas Alunos).
   - Por **Usuário** individual.
5. **Visibilidade Inteligente:** O sistema gerencia automaticamente quem pode visualizar e responder cada formulário:
   - Se você definir um **Perfil/Role**, todos os usuários com esse papel terão o questionário habilitado.
   - Se definir um **Usuário Específico**, apenas esse indivíduo poderá ver e responder, garantindo privacidade para avaliações individuais ou feedbacks direcionados.
   - O questionário respeita as datas de início e fim da aplicação, ocultando-se automaticamente fora do período configurado.
6. **Permissões e Acesso (Donos e Observadores):** Na aba de permissões, você pode definir gestores específicos para o questionário:
   - **Donos:** Podem visualizar o questionário, editar a estrutura, visualizar o dashboard de respostas e excluir.
   - **Observadores:** Podem apenas visualizar o questionário e o dashboard de respostas, sem permissão de alteração.
   - **Lista de Questionários:** Se você não for o Público-Alvo, Dono, Observador ou *Super Admin*, o questionário nem sequer aparecerá na sua listagem.
   - O *Super Admin* sempre possui permissão total.
7. **Clonagem em Lote:** Para agilizar a criação de novos formulários baseados em modelos existentes, o sistema permite a clonagem em lote. Selecione os questionários na tabela e utilize a ação **Clonar Selecionados**. O sistema duplicará toda a estrutura (blocos, perguntas, opções e lógica condicional), o público-alvo e os responsáveis, mas manterá o novo questionário sem nenhuma resposta vinculada.
8. **Responder na Edição:** Na tela de edição do questionário, os administradores e donos podem clicar no botão **Responder Questionário** no topo da página. Esse botão abrirá o formulário de resposta em uma nova aba, facilitando o teste e o preenchimento direto.
9. **Avisar Respondedores (Notificação por E-mail):** Na listagem de questionários, é possível enviar um aviso por e-mail para todos os possíveis respondedores de um questionário ativo.
   - **Como acessar:** Localize o questionário na tabela e clique no botão **Avisar Respondedores** (ícone de envelope amarelo).
   - **Confirmação e Histórico:** O sistema exibirá uma confirmação contendo a data e hora do último envio realizado (esta informação também aparece no tooltip ao passar o mouse sobre o botão na tabela).
   - **Destinatários (Carregamento Lazy):** O modal de confirmação possui uma seção colapsada contendo a opção para carregar a lista de destinatários. Como a lista pode ser muito grande, ela é carregada sob demanda apenas quando você marcar a opção para carregar os e-mails, economizando processamento.
   - **Disparo:** Ao confirmar, as notificações de aviso serão enviadas em segundo plano para todos os e-mails qualificados (que ainda não atingiram o limite máximo de respostas configurado).

### 16.2 Estrutura de Perguntas
Os questionários são organizados em **Blocos Temáticos** (ex: Infraestrutura, Qualidade de Ensino, Gestão).
1. Adicione um Bloco e, dentro dele, adicione as **Perguntas**.
2. **Clonagem e Duplicação:**
   - **Clonar Bloco:** Cada bloco de perguntas possui um botão de ação **"Clonar Bloco"** (ícone de duas folhas). Ao clicar nele, o bloco inteiro e todas as perguntas nele contidas serão duplicados no final do formulário.
   - **Clonar Pergunta:** Cada pergunta possui um botão de ação **"Clonar Pergunta"** (ícone de duas folhas). Ao clicar nele, a pergunta (com seu enunciado, tipo, opções e configurações de condição de exibição) será duplicada dentro do mesmo bloco.
3. **Reorganização e Movimentação:**
   - **Reordenar:** Arraste os blocos ou perguntas para cima/baixo pelos ícones de ordenação para reorganizá-los.
   - **Mover de Bloco:** Cada pergunta possui uma ação rápida chamada **"Mover de Bloco"** (ícone de setas bidirecionais). Clique nela, escolha o bloco de destino e a pergunta será movida imediatamente para lá.
4. **Tipos de Perguntas:**
   - **Discursiva:** Campo de texto livre.
   - **Objetiva:** Seleção de uma única opção.
   - **Múltipla Escolha:** Permite marcar várias opções.
   - **Escala Likert:** Escala de concordância de 1 a 5 (de 'Discordo totalmente' a 'Concordo totalmente').
   - **Lista de Usuários do Sistema:** Seleção dinâmica com os usuários cadastrados no sistema.
   - **Lista de Alunos de uma Turma:** Seleção dinâmica com alunos vinculados ao contexto de turmas do respondente (ou todos os alunos ativos do sistema).
   - **Lista de Pessoas Cadastradas:** Seleção dinâmica com todas as pessoas cadastradas no sistema.

### 16.2.1 Exibição Condicional de Perguntas

Cada pergunta pode ter uma **Condição de Exibição** que a torna visível apenas quando a resposta de outra pergunta do mesmo questionário satisfaz uma lógica configurada. Isso permite criar questionários **dinâmicos e ramificados**, exibindo apenas o que é relevante para cada respondente.

**Como configurar:**
1. Dentro de uma pergunta, expanda a seção **Condição de Exibição** (clique no cabeçalho recolhido).
2. Selecione a **Pergunta de Referência** — a pergunta cujo valor será avaliado. Deixe vazio para que a pergunta seja **sempre exibida**.
3. Escolha o **Operador / Condição** (veja tabela abaixo).
4. Informe o **Valor Esperado** quando o operador exigir.

**Tabela de Operadores:**

| Operador | A pergunta aparece quando… | Exige "Valor Esperado"? |
|---|---|:---:|
| **É igual a** | A resposta for exatamente igual ao valor informado. | ✅ Sim |
| **É diferente de** | A resposta for diferente do valor informado. | ✅ Sim |
| **Contém** | A resposta contiver o trecho ou opção informada. | ✅ Sim |
| **Não contém** | A resposta não contiver o trecho ou opção informada. | ✅ Sim |
| **Foi preenchida (qualquer valor)** | O respondente preencher qualquer coisa (não deixar em branco). | ❌ Não |
| **Não foi preenchida** | O respondente deixar o campo em branco / sem resposta. | ❌ Não |
| **É maior que** | A resposta for um número maior que o valor informado. | ✅ Sim |
| **É menor que** | A resposta for um número menor que o valor informado. | ✅ Sim |

> [!NOTE]
> Para perguntas de **Múltipla Escolha**, o "Valor Esperado" deve ser exatamente igual ao **rótulo da opção** cadastrada (ex: `Sim`, `Não`, `Esportes`). O sistema verificará se aquela opção foi marcada.

---

**📋 Exemplos Práticos — Um para cada operador:**

#### Exemplo 1 — Operador "É igual a"
> **Cenário:** Mostrar "Quantos filhos você tem?" **somente se** a resposta de "Você tem filhos?" for *Sim*.

| Campo | Valor |
|---|---|
| Pergunta de Referência | "Você tem filhos?" *(Objetiva: Sim / Não)* |
| Operador | É igual a |
| Valor Esperado | `Sim` |

---

#### Exemplo 2 — Operador "É diferente de"
> **Cenário:** Mostrar "Qual outro meio de transporte você usa?" **somente se** a resposta de "Como você vai à escola?" *não for* "A pé".

| Campo | Valor |
|---|---|
| Pergunta de Referência | "Como você vai à escola?" *(Objetiva)* |
| Operador | É diferente de |
| Valor Esperado | `A pé` |

---

#### Exemplo 3 — Operador "Contém"
> **Cenário:** Mostrar "Qual atividade esportiva você pratica?" **somente se** em "Quais suas preferências de lazer?" o respondente tiver marcado *Esportes* (mesmo que tenha marcado outras opções também).

| Campo | Valor |
|---|---|
| Pergunta de Referência | "Quais suas preferências de lazer?" *(Múltipla Escolha)* |
| Operador | Contém |
| Valor Esperado | `Esportes` |

---

#### Exemplo 4 — Operador "Não contém"
> **Cenário:** Mostrar "Gostaria de receber informações sobre natação?" **somente se** a resposta de "Quais esportes você já pratica?" *não incluir* "Natação".

| Campo | Valor |
|---|---|
| Pergunta de Referência | "Quais esportes você já pratica?" *(Múltipla Escolha)* |
| Operador | Não contém |
| Valor Esperado | `Natação` |

---

#### Exemplo 5 — Operador "Foi preenchida (qualquer valor)"
> **Cenário:** Mostrar "Você gostaria de dar mais detalhes sobre sua sugestão?" **somente se** o respondente tiver digitado *qualquer coisa* no campo "Deixe sua sugestão" (ou seja, não deixou em branco).

| Campo | Valor |
|---|---|
| Pergunta de Referência | "Deixe sua sugestão" *(Discursiva)* |
| Operador | Foi preenchida (qualquer valor) |
| Valor Esperado | *(não necessário)* |

---

#### Exemplo 6 — Operador "Não foi preenchida"
> **Cenário:** Mostrar "Por que você não tem e-mail?" **somente se** o respondente deixou em branco o campo "Informe seu e-mail".

| Campo | Valor |
|---|---|
| Pergunta de Referência | "Informe seu e-mail" *(Discursiva)* |
| Operador | Não foi preenchida |
| Valor Esperado | *(não necessário)* |

---

#### Exemplo 7 — Operador "É maior que" (Numérico)
> **Cenário:** Mostrar "Quais foram as dificuldades?" **somente se** a nota atribuída em "Avalie de 1 a 10" for maior que 7.

| Campo | Valor |
|---|---|
| Pergunta de Referência | "Avalie de 1 a 10" *(Discursiva/Numérica)* |
| Operador | É maior que |
| Valor Esperado | `7` |

---

> [!TIP]
> As perguntas com condição não satisfeita são **ocultadas em tempo real** durante o preenchimento — o respondente não precisa fazer nada. Além disso, as respostas dessas perguntas ocultas **não são salvas** no banco de dados, garantindo a integridade dos relatórios.

> [!NOTE]
> A condição de exibição funciona com perguntas de **qualquer bloco** do mesmo questionário, não apenas do bloco atual.



### 16.3 Acompanhamento de Resultados
1. Na lista de questionários, você verá a contagem de **Respostas** em tempo real.
2. Ao clicar em **Visualizar** um questionário, o sistema exibe um **Dashboard de Estatísticas** com gráficos de pizza/donuts mostrando o status das respostas e o engajamento do público.
3. No menu **Respostas de Questionários**, você pode consultar individualmente cada envio realizado, o tempo de preenchimento e o perfil institucional do respondente.
4. **Comparação de Respostas:** No menu **Respostas de Questionários**, você pode selecionar múltiplas respostas na tabela e utilizar a Ação em Lote (Bulk Action) **Comparar Respostas**. O sistema abrirá uma janela com uma tabela comparativa lado a lado, mostrando os campos comuns (Nome do Questionário, Respondente, Perfil e Data) e agrupando as perguntas correspondentes que possuam o mesmo ID de Pergunta (identificador), facilitando a visualização e análise de respostas, mesmo de questionários diferentes. Nesta tela de comparação, os usuários também dispõem de um botão para gerar e baixar um relatório em PDF formatado em paisagem (landscape) com as informações comparadas, além de um botão de ajuda rápida no cabeçalho.
5. **Feedbacks e Pareceres Avaliativos:** Na tela de visualização de uma resposta de questionário, usuários com permissão de criação (`Create:QuestionarioResposta`) visualizarão a ação de cabeçalho **Adicionar Feedback**. Ao clicar nesta ação:
   - Um modal será aberto permitindo registrar comentários, pareceres ou orientações avaliativas sobre aquela resposta.
   - O feedback registrado ficará gravado no histórico da resposta, associado ao usuário gestor/avaliador que o escreveu e com a respectiva data/hora.
   - Todos os feedbacks cadastrados são exibidos cronologicamente na seção **Feedbacks / Pareceres Avaliativos** logo abaixo das informações de envio, facilitando o acompanhamento pedagógico ou administrativo do formulário.

---

## 🚀 17. Captação Pública de Interessados

O Torre360 disponibiliza um formulário público que pode ser integrado ou divulgado no site da sua escola para captar o interesse de novos alunos de forma automática e integrada ao funil do CRM.

### 17.1 Como Funciona
- O formulário público é acessado através do endereço: `https://seu-dominio.com/quero-matricular`.
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

### 17.5 Proteção Contra Bot e Flood (reCAPTCHA v3 e Rate Limiting)
Para garantir que seu e-mail e painel não sejam inundados de SPAM ou ataques automatizados, a página utiliza camadas complementares de segurança:
- **Google reCAPTCHA v3:** Analisa o comportamento de navegação de forma invisível para avaliar se o preenchimento é humano legítimo, bloqueando scripts maliciosos.
- **Proteção de Taxa (Rate Limiting):** A submissão do formulário possui limitação estrita de 15 envios por minuto por endereço IP (`throttle:15,1`), prevenindo ataques de negação de serviço, tentativas de submissão em massa (*flooding*) e consumo abusivo do envio de e-mails.

---

## 🧑‍🏫 18. Módulo de Preceptoria

O módulo de **Preceptoria** permite agendar e registrar reuniões pedagógicas individuais entre um professor e um aluno (matriculado), além de manter relatórios dessas sessões com um editor de texto rico e suporte a modelos reutilizáveis.

### 18.1 Conceitos

| Conceito | Descrição |
|---|---|
| **Ciclo de Preceptoria** | Divisão temporal ou acadêmica (ex: 1º Trimestre) para organizar os agendamentos. |
| **Preceptoria** | O agendamento em si: data, hora início, hora fim (opcional), professor, matrícula do aluno e o ciclo ao qual pertence. |
| **Relatório de Preceptoria** | Documento gerado após a sessão, contendo observações e registros. Um relatório está vinculado a exatamente uma Preceptoria. |
| **Template de Relatório** | Modelo de texto reutilizável que pode ser carregado em qualquer relatório como ponto de partida. |

---

### 18.2 Gerenciar Ciclos de Preceptoria

Antes de criar agendamentos, é necessário que existam ciclos cadastrados (ex: Trimestres).

1. Vá em **Preceptoria → Ciclos de Preceptoria**.
2. Clique em **Novo Ciclo de Preceptoria**.
3. Informe o **Nome**, o **Período Letivo** e o intervalo de datas (**Início** e **Fim**).
4. Clique em **Salvar**.

### 18.3 Gerenciar Preceptorias

1. Vá em **Preceptoria → Preceptorias**.
2. Clique em **Nova Preceptoria** para criar um agendamento.
3. Preencha:
   - **Ciclo de Preceptoria** (obrigatório).
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
   - **Enviar Lembretes em Lote:** Selecione várias preceptorias (por exemplo, todas as do dia ou da semana) e clique em **Enviar Lembretes em Lote** para disparar de uma só vez o lembrete de agendamento (e-mail, push e sininho) para todas as elegíveis. O modal de confirmação informa quantas preceptorias selecionadas serão realmente notificadas e quantas serão ignoradas por não estarem completamente agendadas ou já terem ocorrido.
7. **Visualização de Detalhes:** Clique no botão de visualização (ícone de olho) na coluna de ações da tabela de preceptorias para acessar a página de detalhes, onde você pode conferir todas as informações do agendamento em uma interface limpa e organizada.
8. **Alertas e Lembretes:**
   - **Badge de Alerta:** Na listagem de preceptorias, a data de agendamentos previstos para **amanhã** aparecerá destacada em vermelho com um ícone de alerta, facilitando a identificação de compromissos imediatos.
   - **Botão Relembrar:** Para cada preceptoria completamente agendada (com data, horário, professor e aluno) que ocorrerá no futuro, o botão **Relembrar** (ícone de envelope amarelo) estará disponível. Ao acioná-lo, o sistema enviará um lembrete automático por e-mail, push e sininho para o professor, para o aluno e para os responsáveis vinculados.
   - Os lembretes (individuais ou em lote) são enviados de forma assíncrona por uma fila (queue), garantindo que a tela não trave enquanto os e-mails são processados.

---

### 18.4 Criar Templates de Relatório

1. Vá em **Preceptoria → Templates de Relatório**.
2. Crie um template com **Nome** e **Corpo** (editor de texto completo estilo Office).
3. Os templates são reutilizáveis em qualquer Relatório de Preceptoria.

---

### 18.5 Criar e Editar Relatórios de Preceptoria

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

### 18.6 Agendar Preceptoria (Responsáveis e Alunos)

Responsáveis e alunos podem agendar suas próprias preceptorias diretamente pelo painel, escolhendo entre os horários disponibilizados pelos professores.

1. Vá em **Preceptoria → Agendar Preceptoria**.
2. **Seleção da Matrícula:**
   - Se você for um **Aluno**, suas matrículas serão exibidas automaticamente.
   - Se você for um **Responsável**, verá as matrículas de todos os alunos aos quais está vinculado.
3. **Seleção do Horário:** Após escolher a matrícula, o sistema filtra automaticamente os horários disponíveis apenas de professores que possuem vínculo acadêmico direto com o aluno.
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
- **Envio Assíncrono:** `LembretePreceptoriaNotification` implementa `ShouldQueue`, então cada lembrete (disparado individualmente pelo botão **Relembrar** ou em massa pela ação **Enviar Lembretes em Lote**) é processado em background pela fila (`QUEUE_CONNECTION=database`), evitando travar a interface ao notificar muitos destinatários de uma vez.
- **Ação em Lote `relembrar_lote`:** Definida em `PreceptoriasTable`, filtra a seleção pelas preceptorias elegíveis (`isCompletamenteAgendada()` + `isAgendamentoFuturo()`) e chama `relembrarAgendamento()` para cada uma, somando envios e falhas antes de exibir o resultado consolidado.
 desejado e clique em **Confirmar Agendamento**.

> [!IMPORTANT]
> Para usuários com o papel exclusivo de **Aluno** ou **Responsável**, os horários de preceptoria só ficam disponíveis para agendamento com pelo menos **2 dias de antecedência** (D+2) da data atual. Administradores e secretaria não possuem essa restrição.
6. **Interface Dinâmica:** Para evitar confusão, se o aluno selecionado já possuir um agendamento futuro em aberto, o sistema ocultará a seção de "Horários Disponíveis" e o botão de confirmação, exibindo em destaque os detalhes do agendamento atual e uma opção para cancelá-lo caso necessário.
7. **Notificações Ampliadas:** Assim que o agendamento é confirmado (ou cancelado), uma notificação automática é enviada simultaneamente por **E-mail**, **Push (Celular)** e **Sininho do Painel** para:
   - O usuário que realizou a operação (Solicitante);
   - O Professor vinculado à preceptoria;
   - O Aluno e seus Responsáveis Legais vinculados à matrícula.

> [!TIP]
> Caso o professor desejado não apareça com horários disponíveis, entre em contato com a secretaria para que novos "slots" de preceptoria sejam criados no sistema.

---

### 18.7 Widget de Agendamento (Dashboard)

Para facilitar o acesso à agenda pedagógica, o sistema exibe um widget informativo no **Dashboard** principal para usuários com os papéis de **Aluno** ou **Responsável**.

- **Alerta de Disponibilidade:** O widget aparecerá automaticamente se:
    1. O usuário (ou um de seus dependentes) tiver uma matrícula ativa.
    2. Não houver nenhuma preceptoria agendada para o futuro para essa matrícula.
    3. Existirem horários vagos (slots) cadastrados por professores no sistema.
- **Ação Rápida:** O widget exibe o nome do aluno e uma mensagem informando que é possível realizar um agendamento. Ao clicar no widget (ou no botão de ação), você será redirecionado diretamente para a tela de **Agendar Preceptoria** com a matrícula já pré-selecionada.

---

### 18.8 Visibilidade e Acesso Restrito

Para garantir a privacidade e o foco pedagógico, a visualização das preceptorias é filtrada conforme o papel do usuário:
- **Professores:** Cada professor visualiza apenas as preceptorias (agendamentos e registros) associadas à sua própria Pessoa. Eles não têm visibilidade sobre a agenda de outros colegas.
- **Responsáveis e Alunos:** Visualizam agendamentos vinculados à sua própria matrícula ou de seus dependentes.
- **Regras de Agendamento (Todos os Usuários):** Na tela de agendamento, independente do papel (mesmo para Administradores), a visualização de horários disponíveis é restrita apenas aos professores que possuem vínculo acadêmico direto com o aluno (Professor Conselheiro da Turma ou professores que ministram aulas constantes no Cronograma de Aula da turma do aluno).
- **Administradores/Secretaria:** Possuem visibilidade total de todos os agendamentos e registros já realizados no sistema, embora o agendamento de novos horários siga as restrições de vínculo descritas acima.

---

### 18.9 Calendário de Preceptorias

O sistema disponibiliza uma visão visual e interativa de todos os horários através da tela **Preceptoria → Calendário**.

1. **Visão Mensal/Semanal:** Visualize os horários de preceptoria distribuídos no calendário.
2. **Cores Indicativas:**
   - **Verde:** Horários que já possuem uma matrícula (aluno) vinculada.
   - **Cinza:** Horários disponíveis para agendamento.
3. **Filtros Avançados:** Você pode filtrar a visualização por:
   - **Status:** Ver apenas horários "Agendados" ou apenas "Disponíveis".
   - **Professores:** Selecione um ou mais professores para visualizar especificamente as janelas de atendimento deles.
4. **Segurança e Visibilidade:**
   - **Super Admin e Secretaria:** Possuem visão total de todos os agendamentos de todos os professores da instituição.
   - **Responsáveis:** Visualizam apenas os agendamentos de seus próprios filhos ou horários disponíveis dos professores vinculados academicamente aos seus filhos.
   - **Professores:** Visualizam automaticamente apenas os seus próprios horários e agendamentos.

---

## 👤 19. Perfil e Menu de Usuário

No canto superior direito da tela, ao clicar no seu nome ou avatar, você acessa o **Menu de Usuário**.

### 19.1 Gestão de Papéis (Roles) e Alternância de Contexto
O Torre360 permite que um usuário possua múltiplos papéis (ex: Professor e Responsável). Para garantir a segurança e facilitar o uso, você utiliza apenas **um papel por vez**.

1. **Role Ativo:** O menu exibe qual papel você está exercendo no momento (ex: `Role Ativo: professor`).
2. **Alternar Contexto:** Se você tiver outros papéis disponíveis, eles aparecerão como opções (ex: `Atuar como: responsavel`). 
3. **Como Trocar:** Clique na opção desejada. O sistema atualizará seu Dashboard, menu lateral e permissões instantaneamente para o novo contexto.
4. **Pessoa:** Exibe o nome completo da entidade "Pessoa" associada ao seu usuário.

---

---

## 📱 20. Aplicativo Móvel e PWA

O **Torre360** oferece duas formas de acesso mobile para garantir que você esteja sempre conectado, seja você um administrador, professor ou responsável.

### 20.1 Aplicativo Nativo (Android)
O aplicativo nativo oferece a experiência mais completa e integrada com o hardware do seu dispositivo.

1.  **Instalação:** Obtenha o link de download ou o arquivo APK diretamente com a secretaria da escola.
2.  **Ícones e Identidade:** O aplicativo possui ícone personalizado e tela de abertura (Splash Screen) exclusiva da **Torre 360**.
3.  **Vantagens do App Nativo:**
    - **Notificações Push Reais:** Receba alertas mesmo com o aplicativo fechado.
    - **Desempenho Otimizado:** Carregamento mais rápido das interfaces.
    - **Integração com Câmera:** Facilidade para tirar fotos de documentos e fazer o upload diretamente para o sistema.
    - **User-Agent Exclusivo:** O sistema reconhece que você está acessando via aplicativo para oferecer ajustes de layout específicos.

### 20.2 Instalação via PWA (Android e iOS)
Se você prefere não instalar um aplicativo da loja ou utiliza iOS (iPhone), você pode "instalar" o Torre360 diretamente do seu navegador através da tecnologia **PWA (Progressive Web App)**.

1.  **Como Instalar (Android/Chrome):**
    - Acesse o site do sistema pelo Chrome.
    - Clique nos três pontinhos (menu) e selecione **"Instalar aplicativo"** ou **"Adicionar à tela inicial"**.
2.  **Como Instalar (iOS/Safari):**
    - Acesse o site pelo Safari.
    - Clique no ícone de **Compartilhar** (quadrado com seta para cima).
    - Role para baixo e selecione **"Adicionar à Tela de Início"**.
3. **Vantagens do PWA:**
    - Não ocupa espaço significativo na memória.
    - Atualizações automáticas (sempre que o site for atualizado, o "app" também será).
    - Funciona em tela cheia (Standalone), removendo as barras do navegador.

---

## 🤖 21. Assistente de IA (Chat Flutuante)

O Torre360 conta com um **Assistente de IA integrado**, projetado para responder a dúvidas e guiar os usuários de forma interativa através de um chat flutuante disponível no canto inferior direito de todas as telas do painel.

### 21.1 Como Acessar
1. O botão flutuante de chat (ícone de balão de fala) aparecerá no canto inferior direito se o seu usuário possuir a permissão necessária.
2. Clique no botão para abrir o painel de chat. Para minimizá-lo, clique no botão de fechar (X) ou no botão flutuante novamente.

### 21.2 Principais Recursos e Inteligência
- **Base de Conhecimento Local:** A IA responde às suas perguntas com base exclusiva nos documentos oficiais do sistema, incluindo o próprio **Manual do Usuário** e a **Estrutura de Banco de Dados** (facilitando suporte operacional e técnico).
- **Contexto da Página Atual:** O assistente identifica a página exata em que você está navegando. Se você estiver na tela de Lançamento de Notas e perguntar *"o que devo fazer aqui?"*, ele responderá de forma direcionada àquele processo.
- **Navegação SPA Inteligente:** Em suas respostas, o assistente pode sugerir atalhos (ex: *"Acesse a tela de [Matrículas](/admin/matriculas) para conferir os dados"*). Ao clicar nesses links, a navegação ocorre de forma instantânea dentro do painel, sem recarregar o navegador.
- **Persistência da Conversa:** Como o histórico é gerenciado na sessão do navegador, você pode clicar nos links sugeridos, mudar de página ou até mesmo atualizar a tela, e a sua conversa com o assistente continuará exatamente de onde parou.
- **Limpeza de Histórico:** Se desejar iniciar uma nova conversa do zero, basta clicar no ícone de **Lixeira** localizado no cabeçalho do chat.

### 21.3 Controle de Acesso (Segurança)
O acesso ao assistente é gerenciado pelo **Filament Shield**:
- O administrador do sistema deve conceder a permissão customizada `use_assistant` para os papéis (roles) que necessitam utilizar a funcionalidade.
- Sem essa permissão configurada, o botão do chat não será exibido ou carregado no painel do usuário.

---

## 🆔 22. Módulo de Crachás para Pessoas e Turmas

O módulo de crachás do Torre360 permite criar modelos de crachás com um editor gráfico interativo e realizar a impressão em lote dos crachás em PDF. Os templates podem ser vinculados a **Pessoas** ou a **Turmas**.

### 22.1 Gerenciar Modelos (Templates)
1. Acesse o menu **Secretaria → Templates de Crachá**.
2. Clique em **Criar** para criar um novo modelo.
3. Configure as opções básicas:
   - **Nome do Template:** Identificação amigável (ex: "Crachá de Funcionário").
   - **Tipo de Entidade:** Defina se o template é do tipo **Pessoa** (apenas dados da pessoa) ou **Turma** (dados da turma e da pessoa vinculados por matrícula).
   - **Largura e Altura:** Dimensões da área útil do crachá em pixels (px).
   
   > [!TIP]
   > **Conversão de Pixels (px) para Milímetros (mm) na Impressão:**
   > Os crachás são gerados em um papel **A4 físico**. Para obter as dimensões exatas na régua ao imprimir, multiplique o tamanho em milímetros por **3.7795** para descobrir o valor em pixels a configurar:
   > - **Crachá Padrão de Mercado (54 mm x 86 mm):** Configure como **204 px** de largura e **325 px** de altura.
   > - **Crachá Médio (80 mm x 110 mm):** Configure como **302 px** de largura e **416 px** de altura.
   > - **Crachá Grande (90 mm x 130 mm):** Configure como **340 px** de largura e **491 px** de altura.

4. No **Editor de Layout**:
   - **Variáveis Dinâmicas baseadas no Tipo de Entidade:** Ao mudar o tipo de entidade no formulário, a barra lateral recarrega automaticamente as variáveis correspondentes:
     - **Pessoa:** Disponibiliza campos da pessoa como `{nome}`, `{cpf}`, `{email}`, `{telefone}`, `{profissao}`, etc.
     - **Turma:** Além de todos os campos da pessoa (que representam o aluno), disponibiliza campos de texto da turma (como `{turma_nome}`, `{turma_periodo}`, `{turma_serie}` e `{turma_curso}`) e o elemento dinâmico retangular **Cor da Turma** (que é inserido no crachá como um polígono retangular colorido, preenchido automaticamente com a respectiva cor cadastrada na Turma no momento da impressão).
   - **Adicionar Texto Livre / Variáveis:** Os textos no editor funcionam como **Caixas de Texto (Textbox)**. Eles delimitam o retângulo azul semi-transparente que conterá o texto. Ao redimensionar a caixa puxando as alças laterais, o texto **se reorganiza e quebra linhas automaticamente** para caber dentro da largura definida, em vez de se esticar.
   - **Tamanho da Fonte:** A edição do texto no canvas é bloqueada para evitar desalinhamento. O tamanho da fonte, cores, negrito, itálico, alinhamento e família da fonte devem ser editados exclusivamente através do **Painel de Configuração Lateral** que aparece quando o texto está selecionado.
   - **Foto do Aluno/Colaborador:** Adicione o espaço reservado para a foto da pessoa clicando em "Inserir Foto (Placeholder)".
   - **Imagens Customizadas (Editáveis):** Você pode inserir logotipos, ícones ou qualquer imagem do seu computador clicando em "Carregar Imagem (Editável)". Ela se tornará um objeto livre que pode ser movido, rotacionado ou redimensionado no crachá.
   - **Alinhamento & Profundidade:** Utilize os botões rápidos de alinhamento e profundidade para centralizar elements horizontalmente ou definir quais objetos ficam por cima dos outros (Trazer para Frente / Enviar para Trás).
5. Clique em **Salvar** para registrar as alterações de layout.

### 22.2 Impressão de Crachás em Grade (Papel A4)
O sistema calcula automaticamente quantos crachás cabem por folha A4 com base nas dimensões configuradas no template e os organiza lado a lado em uma **grade perfeitamente distribuída e centralizada** na página. Há duas formas de realizar a impressão em lote:

#### A. A partir da listagem de Pessoas (Alunos/Funcionários)
1. Acesse o menu **Secretaria → Pessoas**.
2. Marque a caixa de seleção ao lado do nome das pessoas para quem deseja gerar os crachás.
3. No topo da tabela, acesse o menu de ações em lote e clique em **Imprimir Crachá**.
4. No formulário do modal, selecione qual **Modelo de Crachá** deseja aplicar. Se selecionar um modelo do tipo **Turma**, o sistema buscará automaticamente a matrícula ativa de cada pessoa para obter as informações da turma (caso ela não possua turma ativa, os campos de turma ficarão em branco).
5. Confirme a operação para baixar o arquivo PDF.

#### B. A partir da listagem de Turmas (Todos os Alunos Ativos)
1. Acesse o menu **Acadêmico → Turmas**.
2. Selecione as turmas desejadas e clique em **Imprimir Crachá dos Alunos** nas ações em lote.
3. Selecione o modelo de crachá e confirme. O sistema buscará todos os alunos com matrícula ativa nas turmas e gerará o PDF unificado com os crachás já preenchidos.
4. Se a quantidade de crachás selecionados exceder a capacidade de uma única folha A4, o sistema realizará a quebra de página automaticamente no PDF para as folhas seguintes.

### 22.3 Templates de Crachá V3 (Editor Moveable — HTML Interativo)
O sistema conta com um novo módulo de crachás (V3) utilizando o editor **Moveable**, baseado em elementos HTML interativos que podem ser arrastados, redimensionados e rotacionados livremente na tela. Existe para comparação e uso em paralelo com o módulo V1 (básico).

1. Vá em **Secretaria → Templates de Crachá V3**.
2. **Criação:** Clique em **Novo Template** e defina o nome, tipo de entidade (Pessoa ou Turma) e as dimensões em pixels (Largura e Altura). Salve para liberar o editor.
3. **Editor Canvas (Nova Aba):** Após salvar, clique em **Editar Canvas** na tabela ou no botão do formulário de edição. O editor abrirá em **uma nova aba do navegador**.

#### Interface do Editor V3:

**Cabeçalho:**
- Nome do template, dimensões e tipo de entidade exibidos no topo.
- Controles de **Zoom** (−/+ de 30% a 200%).
- Botões de **Desfazer (Ctrl+Z)** e **Refazer (Ctrl+Y)** com histórico de até 50 estados.
- Seletor de **cor de fundo** do crachá.
- Botão **Salvar** (Ctrl+S) e **Fechar**.

**Sidebar Esquerda — Aba "Elementos":**
- **Inserir Elemento:** Botões para adicionar Texto, Retângulo, Círculo, Linha ou **Importar Imagem (Local)** (permite fazer o upload de qualquer arquivo de imagem local, mantendo o canal alpha de transparência para arquivos do tipo PNG).
- **Campos de Pessoa:** Lista de variáveis dinâmicas como `{nome}`, `{foto}`, `{cpf}`, `{email}`, etc.
- **Campos de Turma** (somente para templates do tipo Turma): Variáveis como `{turma_nome}`, `{turma_serie}`, `{turma_curso}`.
- Clique em qualquer variável para **inserir o elemento no canvas** automaticamente.

**Sidebar Esquerda — Aba "Propriedades":**
Ao selecionar um elemento no canvas, a aba Propriedades exibe:
- **Posição e Tamanho:** X, Y, Largura e Altura editáveis numericamente.
- **Rotação:** Slider de -180° a +180°.
- **Texto:** Conteúdo, família da fonte (Sans-serif, Serif, Monospace), tamanho de fonte, peso, alinhamento e cor do texto.
- **Fundo e Borda:** Cor de fundo (com opção de transparência), cor e espessura da borda, arredondamento.
- **Formato da Foto** (somente para o elemento `{foto}`): Opção para selecionar o corte da foto entre **Retângulo**, **Canto Arredondado** e **Círculo / Elipse**.
- **Alinhamento:**
  - **Alinhar ao Canvas:** Alinha o elemento selecionado em relação ao fundo do crachá (Esquerda, Centro Horizontal, Direita, Topo, Centro Vertical, Base).
  - **Alinhar com outro Elemento:** Permite escolher outro elemento alvo e alinhar o atual em relação a ele (bordas e centros).
- **Profundidade (Camadas):** Controles para reordenar a pilha de elementos no layout e no PDF final: **Trazer p/ Frente** (topo da pilha), **Enviar p/ Trás** (fundo da pilha), **Avançar** (sobe um nível) e **Recuar** (desce um nível).
- **Ações:** Duplicar e Deletar elemento.

**Canvas Central:**
- Exibe o crachá no tamanho real (ajustável pelo zoom).
- Clique em um elemento para selecioná-lo e manipular com o Moveable.
- **Duplo clique** em um elemento de texto para editar o conteúdo inline.
- Tecla **Delete** para remover o elemento selecionado.

4. **Salvamento:** O layout é salvo como **JSON estruturado** no banco de dados, preservando posição, tamanho, rotação, estilos e o tipo de cada elemento (dinâmico ou estático).

#### 5. Impressão de Crachás V3 em Lote:
Para gerar e imprimir os crachás dos alunos no novo modelo V3:
1. Vá em **Cadastro → Pessoas**.
2. Selecione as pessoas desejadas na tabela.
3. No botão de ações em lote, selecione **Imprimir Crachá V3 (Moveable)**.
4. Escolha o modelo de crachá V3 desejado e confirme. O sistema gerará um arquivo PDF contendo os crachás diagramados na folha A4 com substituição automática de dados e fotos.

### 22.4 Interfaces Individuais do Gerador de Crachás
Para facilitar e separar a geração de crachás por versão, o sistema oferece duas interfaces dedicadas sob o menu **Secretaria**:

*   **Gerador de Crachás V1:** Acesso via **Secretaria → Gerador de Crachás V1**, utiliza os modelos do Editor Canvas (FabricJS).
*   **Gerador de Crachás V3:** Acesso via **Secretaria → Gerador de Crachás V3**, utiliza os modelos do Editor Moveable.

Em cada um dos geradores, o formulário de parâmetros permite selecionar:
1.  **Modelo de Crachá:** O template correspondente à versão do gerador acessado.
2.  **Selecionar Pessoas por:** Escolha a forma de obter os dados das pessoas:
    *   **Por Turma:** Seleciona todos os alunos matriculados ativos de uma ou mais turmas escolhidas. Os dados das respectivas turmas também são injetados no PDF.
    *   **Seleção Individual:** Permite buscar e selecionar livremente as pessoas pelo campo de multiselect (com busca inteligente de nome/CPF).
3.  Clique em **Gerar Crachás em PDF** para baixar o lote de crachás correspondente de forma automática.

---

## 📄 23. Templates de Contrato

O módulo de **Templates de Contrato** (acessível no menu **Financeiro → Templates de Contrato**) permite criar e gerenciar os modelos de contrato que serão utilizados na geração de contratos dos alunos.

### 23.1 Clonagem em Lote (Bulk Action)
Para agilizar o processo de criação de novos modelos a partir de um já existente, você pode duplicá-los facilmente na listagem de templates:
1. Vá em **Financeiro → Templates de Contrato**.
2. Selecione um ou mais templates de contrato na tabela marcando a caixa de seleção lateral.
3. Clique no botão de ações em lote no topo da tabela e selecione **Clonar Selecionados**.
4. Confirme a ação. O sistema gerará cópias exatas do conteúdo dos templates selecionados, adicionando o sufixo `(Cópia)` no nome dos novos registros e garantindo que o status de "Padrão" seja copiado como desmarcado (evitando conflito com o modelo padrão atual).

### 23.2 Suporte a Condicionais e Loops (Sintaxe Blade)
Como o sistema utiliza o framework Laravel, o processamento dos templates de contrato suporta **estruturas de controle do Blade** diretamente no texto do modelo. Isso possibilita a criação de contratos dinâmicos, que podem ocultar seções inteiras ou iterar sobre coleções de dados.

#### Exemplos Práticos:

*   **Estrutura de Repetição (Loop) para Responsáveis Financeiros:**
    Caso o contrato possua um ou mais responsáveis cadastrados, você pode listar as informações de cada um utilizando o `@foreach`:
    ```html
    @foreach($responsaveis as $rf)
        <p>
            <strong>Nome:</strong> {{ $rf->pessoa->nome }} <br>
            <strong>CPF:</strong> {{ $rf->pessoa->cpf }}
        </p>
    @endforeach
    ```

*   **Estrutura Condicional (IF-ELSE) para Dados Opcionais:**
    Você pode verificar a presença de um responsável (como o Pai ou a Mãe) e exibir o bloco correspondente somente se os dados existirem no cadastro do aluno:
    ```html
    @if($aluno->responsaveis->where('pivot.tipo_vinculo.nome', 'Pai')->count())
        <p>
            <strong>Pai:</strong> {{ $aluno->responsaveis->firstWhere('pivot.tipo_vinculo.nome', 'Pai')->nome }}
        </p>
    @else
        <p><em>Pai não cadastrado / não declarado.</em></p>
    @endif
    ```

*   **Exibição de Contagens (Quantidade de itens em coleções):**
    Para exibir a contagem total de faturas ou responsáveis de uma coleção de forma simples:
    ```html
    <p>Este contrato possui um total de {{ $faturas->count() }} parcelas.</p>
    ```

*   **Criação de Tabelas Dinâmicas no Editor Visual (Truque das Linhas de Controle):**
    Para desenhar uma tabela de tamanho variável (que cresce de acordo com o número de registros) diretamente pelo editor visual do painel, insira uma tabela de 4 linhas:
    *   **Linha 1 (Cabeçalho):** Digite os títulos das colunas (ex: Parcela, Vencimento, Valor).
    *   **Linha 2 (Abertura do Loop):** Mescle todas as células desta linha e digite: `@foreach($faturas->sortBy('vencimento') as $index => $f)`
    *   **Linha 3 (Dados):** Digite as variáveis nas respectivas células de dados:
        *   Célula 1: `{{ $index + 1 }}`
        *   Célula 2: `{{ \Carbon\Carbon::parse($f->vencimento)->format('d/m/Y') }}`
        *   Célula 3: `R$ {{ number_format($f->valor, 2, ',', '.') }}`
    *   **Linha 4 (Fechamento do Loop):** Mescle todas as células desta linha e digite: `@endforeach`


*   **Entidades Disponíveis no Escopo do Blade:**
    Ao utilizar a sintaxe Blade `{{ $variavel }}`, você tem acesso direto às seguintes variáveis de contexto:
    - `$contrato`: O model do Contrato sendo gerado (ex: `{{ $contrato->valor_total }}`). Caminhos comuns úteis:
      - `{{ $contrato->matricula->turma->nome }}`: Nome da turma do aluno neste contrato.
      - `{{ $contrato->matricula->turma->serie->nome }}`: Nome da série/ano do aluno.
      - `{{ $contrato->matricula->pessoa->nome }}`: Nome do aluno associado ao contrato.
    - `$aluno`: O model da Pessoa (aluno) vinculada à matrícula. Atributos comuns disponíveis:
      - `{{ $aluno->nome }}`: Nome completo.
      - `{{ $aluno->cpf }}`: CPF do aluno.
      - `{{ $aluno->identidade }}`: Registro Geral (RG) / Identidade do aluno.
      - `{{ $aluno->data_nascimento }}`: Data de nascimento.
      - `{{ $aluno->responsaveis }}`: Lista de parentes/contatos do aluno.
    - `$unidade`: A Unidade de Ensino vinculada ao curso (ex: `{{ $unidade->nome }}`).
    - `$responsaveis`: A coleção de responsáveis financeiros associados ao contrato.
    - `$faturas`: A coleção de faturas geradas para o contrato.

*   **Macros e Tabelas Customizáveis (Para uso no Editor Visual):**
    Caso você prefira não usar o modo Código Fonte ou não queira montar tabelas e loops manualmente no editor visual, digite as seguintes macros exatamente como texto plano utilizando a sintaxe `{{!! $variavel !!}}` (em camelCase e com cifrão). Os layouts e o visual destas macros podem ser editados de forma centralizada no menu **Configurações** do painel administrativo (as configurações iniciam com o prefixo `template_contrato_` e em formato snake_case):
    - `{{!! $tabelaFatura !!}}`: Insere a tabela dinâmica completa das parcelas do contrato (configuração: `template_contrato_tabela_fatura`).
    - `{{!! $tabelaAluno !!}}`: Insere a tabela com dados cadastrais e acadêmicos do Aluno (configuração: `template_contrato_tabela_aluno`).
    - `{{!! $infoResponsaveis !!}}`: Insere o parágrafo corrido com a qualificação dos responsáveis financeiros e endereços (configuração: `template_contrato_info_responsaveis`).
    - `{{!! $assinaturasRepresentantes !!}}`: Insere as linhas de assinatura para os representantes legais da escola (configuração: `template_contrato_assinaturas_representantes`).
    - `{{!! $assinaturasResponsaveis !!}}`: Insere de forma consolidada e inteligente todas as linhas de assinatura necessárias do contrato (Pai, Mãe e Responsável Financeiro) (configuração: `template_contrato_assinaturas_responsaveis`). Ela aplica automaticamente as seguintes regras para simplificar o contrato:
      - Caso o Pai ou a Mãe sejam também o Responsável Financeiro, as assinaturas deles constarão com a observação "e Responsável Financeiro(a)".
      - A assinatura do Responsável Financeiro de terceiros é exibida somente se ele não for nem o Pai nem a Mãe, evitando duplicidade de linhas.
    - `{{!! $assinaturaPai !!}}`: Insere a linha de assinatura específica do Pai do aluno (configuração: `template_contrato_assinatura_pai`).
    - `{{!! $assinaturaMae !!}}`: Insere a linha de assinatura específica da Mãe do aluno (configuração: `template_contrato_assinatura_mae`).
    - `{{!! $assinaturaResponsavelFinanceiro !!}}`: Insere a linha de assinatura específica do Responsável Financeiro (configuração: `template_contrato_assinatura_responsavel_financeiro`).
    - `{{!! $assinaturaResponsavelLegalUnidade !!}}`: Insere a linha de assinatura do Representante Legal da Unidade de Ensino (configuração: `template_contrato_assinatura_responsavel_legal_unidade`).

### 23.2 Gestão de Contratos e Assinatura Digital (`/admin/contratos`)
Acesse **Financeiro → Contratos**.
- **Confirmação e Reset de Assinatura ao Editar (`/admin/contratos/{id}/edit`):** Caso um contrato já tenha sido submetido anteriormente para assinatura na plataforma Assinafy, ao tentar editar suas informações principais ou modificar suas faturas (criar, editar, excluir ou regerar faturas automaticamente), o sistema exibirá um modal de confirmação. Ao confirmar a alteração, o estado e o histórico da assinatura anterior são resetados no sistema, permitindo que a nova versão do contrato (com valores e dados atualizados) seja enviada para assinatura digital ao acessar `/contratos/{id}/visualizar`.
- **Widget do Dashboard (Contratos Pendentes):** Quando o usuário ativo possuir contratos onde é um dos signatários e sua assinatura estiver pendente, um widget em destaque será exibido no topo do Dashboard (`/admin`), apresentando os detalhes do contrato e o botão de ação rápida **Assinar Agora**. O widget se oculta automaticamente assim que não houver pendências de assinatura para aquele usuário.
- **Indicador de Pendências no Menu (Navigation Badge):** O item **Contratos** no menu lateral exibe um badge numérico em destaque (cor de alerta/warning) contendo a quantidade de contratos com pendência de assinatura para o usuário ativo.
- **Status por Signatário:** A coluna **Signatários e Status** na tabela de contratos exibe o estado individual de cada pessoa responsável pela assinatura (ex: Pai, Mãe, Responsável Financeiro). É possível visualizar de forma clara quem já assinou (🟢 *Assinado*) e quem ainda falta assinar (🟡 *Pendente*). A coluna **Assinatura** mostra o estado do contrato como um todo: *Pendente*, *Todos assinaram*, *Certificando*, *Certificado* (os três últimos significam contrato assinado), além de *Recusado*, *Cancelado*, *Expirado* e *Erro no envio*.
- **Sincronização em Tempo Real:** Ao clicar no botão **Sincronizar Assinaturas** (ícone de recarga), o sistema realiza a consulta síncrona na API do Assinafy e atualiza instantaneamente a lista de quem já assinou o contrato no painel.
- **Assinatura Digital (Assinafy):** Na tabela de contratos, ao clicar em **Assinar Contrato** para um contrato que esteja pendente de assinatura, o sistema realiza a comunicação direta com a plataforma **Assinafy** e encaminha o usuário diretamente para a URL de assinatura do documento (ex: `https://app.assinafy.com.br/release/...`).
- **Visualização de Contrato Assinado:** Quando o status do contrato for "Assinado", a ação na tabela se altera para **Ver Contrato Assinado**, permitindo a visualização da versão concluída ou o download do arquivo PDF com o certificado de assinatura digital.

### 23.3 Gestão de Faturas, Dar Baixa e Transações Bancárias (`/admin/faturas` e `/admin/transacao-bancarias`)
- **Status Controlado da Fatura:** O campo de status é gerenciado por um Enum (`StatusFatura`), com rótulos e cores de identificação visual clara:
  - 🟡 **Pendente:** Fatura aguardando pagamento.
  - 🟢 **Pago:** Fatura quitada integralmente.
  - 🔴 **Atrasado:** Fatura com vencimento expirado sem quitação.
  - 🔵 **Pago Parcialmente:** Foi dada baixa em valor inferior ao saldo devedor.
  - ⚪ **Cancelado:** Fatura anulada.
- **Ação "Dar Baixa" em 1-Clique com Garantia Transacional:** Diretamente na listagem de **Faturas** ou no gerenciador de faturas dentro do **Contrato**, a ação **Dar Baixa** permite registrar pagamentos instantaneamente:
  - Abre um modal pré-preenchido com o saldo devedor atual da fatura.
  - Solicita a seleção do **Banco**, valor recebido, data do pagamento e observações.
  - Ao confirmar, o sistema executa a operação em uma transação de banco de dados atômica (`DB::transaction`): gera automaticamente a **Transação Bancária** de entrada vinculada à fatura e atualiza seu status para *Pago* (ou *Pago Parcialmente* caso restem valores), garantindo que nenhuma inconsistência financeira ou lançamento parcial órfão ocorra em caso de interrupção ou falha de rede.
- **Filtros por Período e Status de Faturas:** É possível filtrar faturas por status (ex: somente em aberto) e definir faixas de vencimento.
- **Interface de Transações Bancárias Legível:** A tela de transações bancárias exibe o nome do Banco, o Aluno/Contrato vinculado, o Plano de Contas, o Fornecedor e traz badges coloridos identificando **↑ Entrada (verde)** e **↓ Saída (vermelho)** com valores formatados em moeda (R$).

---

## 🏫 24. Gestão de Instituições de Ensino e Unidades Escolares

O sistema permite gerenciar a estrutura da rede de ensino em dois níveis: **Instituição de Ensino** (mantenedora/rede) e **Unidades Escolares** (escolas/unidades físicas).

### 24.1 Instituição de Ensino (`/admin/instituicao-ensinos`)
Acesse **Localização e Cadastros → Instituições de Ensino**.
- **Código INEP:** Registro do código oficial da mantenedora junto ao INEP.
- **Órgão Vinculado:** Identificação do órgão ao qual a escola pública está vinculada.
- **Flags de Vínculos com Órgãos Públicos / Mantenedores:**
  - *Secretaria de Educação/Ministério da Educação*
  - *Secretaria de Segurança Pública/Forças Armadas/Militar*
  - *Secretaria da Saúde/Ministério da Saúde*
  - *Outro órgão da administração pública*
- **Dados Gerais:** Nome, CNPJ, Logotipo institucional e status (Ativo/Inativo).
- **Canais de Comunicação:** Celular/WhatsApp, Instagram, Facebook e YouTube.
- **Exportação Completa para o Educacenso (Ação em Lote):** Selecione uma ou mais instituições na tabela e utilize a opção em lote **Exportar para Educacenso** para gerar o arquivo `.txt` completo no formato oficial do INEP (Censo Escolar 2026), contendo todos os registros: **00** (Escola), **10** (Infraestrutura), **20** (Turmas), **30** (Pessoas Físicas), **40** (Gestores), **50** (Docentes) e **60** (Vínculos de Alunos).
- **Ajuda Integrada:** O botão de ajuda no cabeçalho resume as ações disponíveis de acordo com suas permissões.

### 24.2 Unidade Escolar (`/admin/unidades`)
Acesse **Localização e Cadastros → Unidades**.
- **Código INEP:** Código identificador oficial da escola no Censo Escolar/INEP.
- **Situação de Funcionamento:** Indica o status da escola (1-Em atividade, 2-Paralisada ou 3-Extinta).
- **Contato Escolar:** Telefone no formato `(99)99999-999` e E-mail de contato da unidade.
- **Órgão Regional de Ensino:** Código da diretoria/órgão regional de ensino vinculado.
- **Dados do Censo / MEC:**
  - **Localização / Zona da escola:** 1-Urbana ou 2-Rural.
  - **Localização diferenciada:** 1-Área de assentamento, 2-Terra indígena, 3-Comunidade quilombola, 7-Não está em área de localização diferenciada ou 8-Área onde se localizam povos e comunidades tradicionais.
  - **Dependência administrativa:** 1-Federal, 2-Estadual, 3-Municipal ou 4-Privada.
- **Ajuda Integrada:** Botão de ajuda no cabeçalho com orientações sobre preenchimento e permissões registradas no Shield.


---

## 📄 25. Preceptorias e Agendamento (`/admin/preceptorias`)

O módulo de **Preceptorias** permite o cadastro, acompanhamento e agendamento de atendimentos de preceptoria para os alunos.

### 25.1 Controle de Permissões via Filament Shield
O acesso e as ações do módulo de Preceptorias são totalmente configuráveis através do **Filament Shield** (em **Gerenciamento de Acesso → Shield / Roles**):
- **Visualização (`ViewAny:Preceptoria` / `View:Preceptoria`):** Permite visualizar a listagem e os detalhes dos atendimentos.
- **Criação e Edição (`Create:Preceptoria` / `Update:Preceptoria`):** Permite cadastrar novos horários e gerenciar a agenda dos preceptores.
- **Agendamento (`Agendar:Preceptoria`):** Controla quem pode acessar a rota `/admin/preceptorias/agendar` e utilizar o formulário simplificado de agendamento de horários para dependentes/alunos. Sem esta permissão atribuída à função do usuário no Shield, o acesso ao caminho `admin/preceptorias/agendar` é bloqueado com erro 403 Forbidden.

---

## 📝 26. Lançamento Rápido no Diário (`/admin/cronograma-aulas/{record}/frequencia`)

O módulo de **Lançamento Rápido no Diário** permite que o professor registre no final da aula, em uma única tela fluida e responsiva (mobile-friendly):
1. **Conteúdo Ministrado & BNCC:** Registro descritivo dos tópicos lecionados e seleção das Habilidades da BNCC desenvolvidas.
2. **Dever / Tarefa de Casa:** Campo dedicado para registrar lições e prazos de entrega para a turma.
3. **Anexos de Material de Aula:** Upload de apresentações, exercícios e arquivos PDF suporte.
4. **Frequência dos Alunos:** Chamada rápida com botões de presenças e faltas.
5. **Botão de Presença em Lote:** Permite marcar todos os alunos presentes com um único clique.

---

## 🏥 27. Saúde Escolar, Ficha Médica e Ambulatório (`/admin/ficha-medicas` e `/admin/atendimento-enfermagems`)

Módulo essencial para a Educação Infantil e Ensino Fundamental para assegurar os cuidados médicos e alimentares:
1. **Restrições Alimentares Destacadas:** Toggles e alertas visuais de alergia a lactose, glúten e amendoim para a cantina/cozinha da escola.
2. **Medicamentos de Uso Contínuo:** Controle de dosagens, horários de administração e arquivo de autorização dos pais.
3. **Contatos de Emergência:** Telefones e grau de parentesco para acionamento urgente.
4. **Atendimentos de Enfermagem (`/admin/atendimento-enfermagems`):** Prontuário do ambulatório escolar registrando sintomas, medicamentos ministrados e condutas adotadas.

---

## 🚨 28. Convivência e Ocorrências da Rotina (`/admin/ocorrencia-escolars` e `/admin/tipo-ocorrencias`)

Permite o acompanhamento da rotina disciplinar, operacional e pedagógica dos estudantes:
1. **Tipos de Ocorrências (`/admin/tipo-ocorrencias`):** Classificação por gravidade (Positiva, Leve, Média, Grave) e categorias (Disciplinar, Operacional, Pedagógico, Saúde).
2. **Registro de Ocorrências:** Cadastro de acontecimentos (atraso na chegada, uniforme incompleto, desentendimento, advertências ou elogios pedagógicos).
3. **Notificação em Tempo Real aos Responsáveis:** Envio automático via e-mail, push e painel interno aos pais cadastrados. A notificação pode ser facilmente desativada/ativada em registros específicos.

---

## 🏠 29. Portal do Aluno/Responsável (`/portal`)

Além do acesso ao painel administrativo (com visibilidade restrita — veja a seção 6.1, item "Visibilidade Restrita"), usuários com o papel **aluno** ou **responsavel** também podem entrar por um endereço dedicado e mais simples: **`/portal`**.

O Portal usa o **mesmo login e senha** do painel principal — é só um caminho de entrada alternativo, mais enxuto, focado em telas de consulta e ação:

1. **Início:** Lista os alunos vinculados ao seu cadastro (o próprio, se você for aluno; seus dependentes, se você for responsável), com atalhos rápidos para as telas abaixo.
2. **Boletins (`/portal/academico`):** Lista as matrículas acessíveis e permite baixar o boletim em PDF de cada uma.
3. **Financeiro (`/portal/financeiro`):** Lista as faturas do(s) aluno(s), com vencimento, valor, valor pago, saldo devedor e status.
4. **Documentos (`/portal/documentos`):** Lista os contratos vinculados, com opções para visualizar, baixar o PDF, iniciar/continuar a assinatura digital (Assinafy) ou baixar o contrato já assinado.
5. **Ocorrências (`/portal/ocorrencias`):** Lista as ocorrências escolares (elogios, advertências, atrasos etc.) registradas para o(s) aluno(s), com o mesmo destaque de gravidade por cor usado no admin.
6. **Preceptoria (`/portal/preceptoria`):** Permite agendar (ou desagendar) um horário de preceptoria para o aluno diretamente pelo Portal, com a mesma regra de antecedência mínima de 2 dias já usada no admin. A lista de horários mostra apenas os professores vinculados à turma do aluno (professor conselheiro ou do cronograma de aulas).
7. **Notas (`/portal/notas`):** Mostra as notas do aluno por etapa avaliativa (bimestre/trimestre), com uma linha por disciplina: nota de cada categoria de avaliação, **média**, **média da turma** e **frequência**. É o mesmo cálculo do boletim. Médias abaixo da nota mínima de aprovação do período letivo (padrão 7,0) e frequências abaixo de 75% aparecem em vermelho; notas substituídas por uma avaliação de recuperação aparecem riscadas. Para turmas avaliadas por habilidades (Educação Infantil/BNCC), a tela também exibe o **conceito** de cada habilidade (Realiza bem, Em desenvolvimento, Não realiza, Não observado) e a observação do professor.
8. **Frequência (`/portal/frequencia`):** Mostra o percentual geral de presença, o total de aulas registradas, presenças e faltas, o resumo **por disciplina** e a lista aula a aula (data, disciplina, horário e situação), com filtros por situação (faltas/presenças) e disciplina. Quando a frequência geral fica abaixo do mínimo exigido (75%), aparece um aviso no topo da tela.
9. **Horários (`/portal/horarios`):** Mostra as aulas da semana do aluno, dia a dia, em ordem de horário, com disciplina, professor e — quando o professor registrou — conteúdo e dever de casa. Dias não letivos (feriados/recessos) aparecem destacados. Use **Anterior**, **Hoje** e **Próxima** para navegar entre as semanas; sábado e domingo só aparecem quando há aula ou dia não letivo. Os horários vêm do cronograma de aulas cadastrado pela escola.

**Vários filhos:** nas telas de Notas, Frequência e Horários, quando o seu cadastro está ligado a mais de um aluno, aparece uma lista no topo para escolher qual aluno consultar. A tela só permite ver matrículas que pertencem à sua família, mesmo que o endereço seja alterado manualmente.

**Calendário (`/portal/calendario`):** além das provas e dos dias não letivos, agora mostra as **aulas** da turma do aluno (de 45 dias atrás até 90 dias à frente). Em celulares, o calendário abre na visão de lista.

> [!NOTE]
> **Pagamento de fatura online não está disponível no Portal.** Hoje o Financeiro do Portal é só consulta (ver fatura, valor, status). Colocar um botão de "pagar agora" exige integrar um gateway de pagamento (PIX/boleto/cartão) — isso não foi implementado porque depende de uma decisão de produto (qual gateway, taxas, conta recebedora) antes de qualquer código.

> [!NOTE]
> Usuários com papéis de equipe (administrador, secretaria, professor, coordenador, super_admin) não têm acesso ao `/portal` — continuam usando exclusivamente o painel `/admin`. Se uma conta de equipe (já autenticada) tentar acessar `/portal`, o sistema exibe um aviso e redireciona automaticamente de volta para o `/admin`, em vez de mostrar um erro 403.

> [!NOTE]
> As rotas de visualização/download de contrato (`/contratos/{id}/...`) e de boletim (`/matriculas/{id}/boletim/download`) agora verificam se o registro pertence ao usuário logado antes de liberar o acesso — aluno/responsável só acessam os próprios registros ou os de seus dependentes; usuários com papel de equipe continuam com acesso irrestrito.

> [!WARNING]
> **Correção (22/08/2026):** as telas de Boletins, Financeiro e Documentos do Portal estavam sendo publicadas sem a tabela de dados aparecer (página em branco, sem listar nada). O problema era só de exibição — a permissão e os dados já estavam corretos — e foi corrigido.

---

## 🛡️ 30. Controle Granular de Widgets pelo Filament Shield (`/admin/shield/roles`)

O sistema permite o gerenciamento completo e granular de quais widgets aparecem na tela inicial (**Dashboard**) para cada papel (**Role**):

1. **Acesso:** Vá em **Sistema e Segurança → Papéis (Shield)** e clique em **Editar** no papel desejado (ex: `super_admin`, `admin`, `secretaria`, `professor`, etc.).
2. **Aba Widgets:** Acesse a aba **Widgets** na tela de permissões do papel.
3. **Seleção Individual:** Todos os 13 widgets da aplicação estão disponíveis para marcação:
   - *Visão Geral de Estatísticas (`StatsOverview`)*
   - *Alunos por Turma (`AlunosPorTurmaChart`)*
   - *Matrículas com Pendências (`MatriculasPendentesWidget`)*
   - *Contratos Pendentes de Assinatura (`ContratosPendentesWidget`)*
   - *Questionários Pendentes (`QuestionariosPendentes`)*
   - *Pendências de Lançamento de Frequência (`FrequenciaPendenteWidget`)*
   - *Agendamento de Preceptoria (`PreceptoriaSchedulingWidget`)*
   - *Calendário de Preceptorias (`PreceptoriaCalendarWidget`)*
   - *Calendário de Aulas e Avaliações (`CronogramaCalendarWidget`)*
   - *Agenda de Follow-up CRM (`CrmFollowUpCalendarWidget`)*
   - *Origem dos Interessados (`InteressadoOrigemChart`)*
   - *Interessados por Status (`InteressadoStatusChart`)*
   - *Supervisor de Filas e Jobs (`QueueSupervisorWidget`)*
4. **Respeito às Permissões para Super Admin:** Mesmo para o papel **Super Administrador (`super_admin`)**, a exibição dos widgets na tela inicial respeita estritamente as opções marcadas na aba de Widgets do Shield. Isso permite que a instituição personalize a tela inicial mesmo para administradores globais, exibindo apenas as informações mais relevantes.
5. **Combinação com Regras de Negócio:** Para widgets dinâmicos (como *Contratos Pendentes*, *Questionários Pendentes* e *Pendências de Frequência*), o widget só é renderizado se a permissão estiver ativa no papel **e** se houver dados/pendências reais pertinentes ao usuário logado.

---

## 📄 31. Secretaria Digital — Declarações Oficiais e Histórico com QR Code

O módulo de **Secretaria Digital** elimina a burocracia do atendimento físico e permite que a instituição elabore minutas personalizadas, atenda a pedidos de certidões e emita documentos timbrados protegidos por autenticação pública digital com QR Code.

### 31.1 Modelos de Documentos (`/admin/template-documentos`)
1. **Templates com Macros Dinâmicas:** A secretaria pode cadastrar modelos de certidões e declarações (Matrícula, Frequência, Quitação Financeira, Transferência, Histórico Escolar, Conclusão de Curso) utilizando o editor de texto enriquecido (Rich/TinyEditor).
2. **Tags de Substituição Automática:** O sistema substitui automaticamente na hora da emissão:
   - `{NOME_ALUNO}`, `{CPF_ALUNO}`, `{DATA_NASCIMENTO_ALUNO}`, `{MAE_ALUNO}`, `{PAI_ALUNO}`
   - `{MATRICULA}`, `{ANO_LETIVO}`, `{CURSO}`, `{SERIE}`, `{TURNO}`, `{TURMA}`
   - `{NOME_INSTITUICAO}`, `{CNPJ_INSTITUICAO}`, `{ENDERECO_INSTITUICAO}`
   - `{DATA_EXTENSO}`, `{CIDADE_DATA}`, `{CODIGO_AUTENTICIDADE}`
3. **Autenticidade e Carimbo Digital:** Ao marcar o campo *Exige Código e QR Code de Autenticidade*, cada documento gerado recebe um carimbo com hash único e QR Code para auditoria instantânea por câmeras de celular.
4. **Histórico Escolar real:** no modelo do tipo Histórico Escolar, a tag `{{TABELA_HISTORICO}}` traz uma tabela por ano/período letivo do aluno com a média e a situação final **já calculadas pelo Fechamento do Ciclo Letivo** (seção 5.10) — incluindo o resultado do exame final, quando houver. Um período letivo ainda não fechado aparece com um aviso em vez de dados inventados.

### 31.2 Gestão de Solicitações e Emissão (`/admin/solicitacao-documentos`)
1. **Atendimento a Requerimentos:** A secretaria visualiza pedidos abertos tanto presencialmente quanto vindos do Portal da Família.
2. **Ação Rápida de Emissão:** Com um clique no botão **Emitir Documento (com QR Code)**, o sistema processa todas as macros, gera o PDF timbrado oficial no servidor e cria o hash rastreável.
3. **Download e Impressão:** O operador pode baixar o PDF oficial imediatamente ou enviar aos pais com status *Emitido*.

### 31.3 Validação Pública de Autenticidade (`/validar-documento/{codigo}`)
1. Qualquer empresa, órgão público ou cartório que receba o documento pode apontar a câmera do smartphone para o QR Code impresso no rodapé ou acessar o link público: `http://seusistema/validar-documento`.
2. O validador confere o código alfanumérico na base de dados e exibe uma tela oficial confirmando a autenticidade, curso/série, data de lavratura e situação do documento.
3. **Proteção contra Raspagem em Massa (Rate Limiting):** A rota pública conta com limitação automática de requisições por IP (`throttle: 15 req/min`), neutralizando tentativas de varredura automatizada por robôs.
4. **Busca Estrita por Código de Alta Entropia:** A consulta aceita exclusivamente o *Código de Verificação* aleatório de 12 dígitos gerado no QR Code (ex: `TR36-XXXX-XXXX-XXXX`). Buscas por número de protocolo sequencial são bloqueadas na consulta pública para evitar ataques de enumeração de estudantes.
5. **Conformidade com a LGPD e Proteção de Menores:** O nome do estudante é parcialmente mascarado na tela pública (ex: `B****** M*****`), permitindo a conferência por quem porta o documento físico sem expor dados pessoais completos a terceiros ou raspadores web.

---

## 🔄 32. Rematrícula Online (Administração e Portal da Família)

Automatize o processo de rematrícula entre períodos letivos: a família confirma a vaga pelo Portal, e o sistema gera a nova matrícula, o contrato, a cobrança e já envia para assinatura digital — sem a secretaria precisar fazer nada manualmente, a menos que algo falhe.

### 32.1 Campanhas de Rematrícula (`/admin/periodo-rematriculas`)
1. **Abertura de Período:** a secretaria cadastra a campanha escolhendo o *Período Letivo Origem* (ex: 2026) e o *Período Letivo Destino* (ex: 2027), a vigência (data de início/fim) e se está ativa.
2. **Modelo de Contrato e Cobrança:** selecione o *Modelo de Contrato* a ser gerado, o *Valor Total*, a *Quantidade de Parcelas* e o *Valor de Entrada* (0 se não houver) — esses dois últimos definem automaticamente como as faturas são geradas quando a família confirma a rematrícula. Sem um modelo de contrato selecionado, a campanha só cria a nova matrícula, sem contrato nem cobrança.
3. **Mensagem aos Pais:** texto livre exibido no Portal durante a campanha.

### 32.2 Acompanhamento (`/admin/rematriculas`)
A secretaria acompanha o status de cada rematrícula:
- **Iniciada:** a família abriu o processo, mas ainda não confirmou os dados.
- **Dados Confirmados:** a família confirmou e a nova matrícula, o contrato e as faturas já foram gerados, mas o contrato não chegou a ser enviado para assinatura porque o envio ao Assinafy falhou. A secretaria resolve na tela do Contrato gerado (**Assinar Contrato**), ou a própria família tenta em *Documentos e Contratos*. Esse status só muda quando o contrato é assinado. (Se a campanha não tem modelo de contrato, a rematrícula vai direto para *Confirmada*.)
- **Aguardando Assinatura do Contrato:** tudo certo — nova matrícula, contrato e faturas já gerados, contrato enviado para o Assinafy.
- **Rematrícula Confirmada:** o contrato foi assinado (confirmação automática via webhook do Assinafy) — processo concluído.
- **Cancelada.**

### 32.3 Rematrícula pelo Portal da Família (`/portal/rematricula`)
1. Durante uma campanha ativa, a família vê a lista de dependentes elegíveis e o botão **Realizar Rematrícula**.
2. O responsável escolhe a série pretendida e o turno de preferência para o próximo período, pode deixar observações, e confirma.
3. **Nos bastidores, tudo acontece automaticamente:** o sistema cria a nova matrícula no período de destino, gera o contrato a partir do modelo configurado na campanha (copiando os responsáveis financeiros do contrato anterior), gera as faturas (entrada + parcelas, com vencimentos a partir do dia da rematrícula, não da assinatura) e envia o contrato para assinatura digital via Assinafy. A família recebe o link de assinatura pelo mesmo processo já usado na matrícula original (seção 23.3).
4. **O aviso na tela reflete o estado real:** *Rematrícula Confirmada!* só aparece quando a campanha não tem contrato a assinar. Com o contrato enviado, a família vê *Falta assinar o contrato* (a rematrícula só é confirmada depois da assinatura); se o envio falhou, vê *Dados registrados — contrato ainda não enviado*. As duas últimas mensagens ficam fixas e levam a *Documentos e Contratos*.
5. Depois que a nova matrícula é gerada, o botão **Realizar Rematrícula** some para aquele estudante e, na secretaria, a ação **Efetivar Rematrícula** também deixa de aparecer — assim não há risco de gerar matrícula, contrato e cobrança em duplicidade.

---

## 📢 33. Comunicação Escolar — Eventos com RSVP e Central de Atendimento

O módulo de **Comunicação Escolar** reduz o fluxo de ligações e atendimento presencial, estabelecendo um canal direto, seguro e rastreável entre a escola e as famílias.

### 33.1 Gestão de Eventos e Atividades (`/admin/evento-escolars`)
1. **Cadastro de Atividades:** A coordenação publica reuniões de pais e mestres, celebrações, feiras de ciências, palestras ou passeios pedagógicos externos.
2. **Direcionamento de Público:** O evento pode ser destinado a toda a escola ou apenas a turmas específicas.
3. **Controle de Vagas e Prazo Limite:** É possível fixar limite máximo de participantes e prazo de expiração para o RSVP.
4. **Termos de Autorização de Saída:** Para passeios externos, ativa-se a exigência de autorização formal dos pais com a minuta legal pré-configurada.
5. **Lista de Presença em Tempo Real:** Com um clique na ação **Lista de Presença**, a equipe visualiza os alunos confirmados, acompanhantes declarados e o status do termo de autorização assinado.

### 33.2 Confirmação de Presença no Portal da Família (`/portal/eventos`)
1. Os responsáveis visualizam cards informativos com data, horário, local, vagas restantes e programação do evento.
2. Com um clique em **Confirmar Presença**, informam a quantidade de acompanhantes e, caso o evento exija autorização de saída, assinam o termo digital com registro de data, hora e IP do dispositivo.

### 33.3 Central de Atendimento & Chamados (`/admin/atendimento-chamados` e `/admin/atendimento-setores`)
1. **Setores de Atendimento:** Configure os departamentos disponíveis para contato (Secretaria, Setor Financeiro, Coordenação Pedagógica, Ambulatório e Direção).
2. **Painel de Chamados:** A equipe acompanha chamados organizados por protocolo único (`ATD-YYYY-XXXXX`), com filtros por setor, status e prioridade.
3. **Ação Rápida "Responder":** O atendente responde a dúvida da família, anexa documentos/comprovantes em PDF e atualiza a situação do atendimento com notificações instantâneas.

### 33.4 Atendimento pelo Portal da Família (`/portal/atendimento`)
1. **Abertura Descomplicada:** O responsável clica em **Novo Chamado**, seleciona o setor, o estudante (opcional), informa o assunto e descreve a solicitação com opção de anexos.
2. **Histórico e Conversa:** Todas as mensagens da escola e da família ficam salvas em uma linha do tempo organizada e segura.
3. **Avaliação do Atendimento:** Ao término da solicitação, o responsável avalia a experiência com nota de 1 a 5 estrelas e comentário de feedback para a ouvidoria da escola.

---

## 🏫 34. Ensalamento em Lote Assistido (`/admin/ensalamento`)

O módulo de **Ensalamento em Lote Assistido** foi desenvolvido para apoiar a equipe pedagógica e a secretaria durante o planejamento do período letivo, permitindo organizar a distribuição dos estudantes entre as turmas de forma visual, equilibrada e em conformidade com a capacidade física das salas de aula.

### 34.1 Filtros do Planejamento Letivo
1. Acesse o menu lateral em **Acadêmico → Ensalamento em Lote Assistido** (URL: `/admin/ensalamento`).
2. Utilize os filtros superiores para selecionar o cenário desejado:
   - **Período Letivo:** Selecione o ano letivo em planejamento ou em curso.
   - **Curso:** Filtre por segmento (ex: Educação Infantil, Ensino Fundamental).
   - **Série / Ano:** Escolha a série correspondente (ex: 1º Ano, 2º Ano).
   - **Turno (Opcional):** Permite focar em um turno específico (Matutino/Vespertino) ou analisar todos simultaneamente.

### 34.2 Indicadores e Cards Visuais de Ocupação
O topo da tela consolida os números globais do cenário:
- **Turmas na Série:** Quantidade de salas abertas para a série selecionada.
- **Capacidade Total:** Soma das vagas máximas configuradas para todas as turmas.
- **Já Ensalados:** Total de alunos já alocados e taxa percentual de ocupação global.
- **Aguardando Turma:** Quantidade de estudantes com matrícula ativa aguardando definição de sala.

Em cada **Card de Turma**, você acompanha:
- **Barra de Ocupação Dinâmica:** Progresso visual colorido conforme o preenchimento (verde até 85%, âmbar entre 85% e 99%, vermelho se lotada).
- **Equilíbrio de Gênero:** Contagem e proporção em tempo real de meninos e meninas matriculados na sala.
- **Lista de Alunos Ensalados (Sanfona):** Expanda para visualizar os estudantes alocados, com atalhos para **Transferir** de sala ou **Desensalar** (retornando o aluno para a fila de espera).

### 34.3 Alocação Manual e em Lote
1. Na seção inferior, o sistema lista todos os estudantes matriculados que ainda não possuem turma definida na série.
2. **Alocação Individual Rápida:** No seletor da coluna direita do aluno, escolha a turma desejada; o aluno é alocado imediatamente com validação de vagas livres.
3. **Alocação em Lote:**
   - Marque a caixa de seleção de múltiplos alunos (ou utilize a caixa do cabeçalho para selecionar todos).
   - No topo da tabela, escolha a **Turma de Destino**.
   - Clique em **Alocar Selecionados**. O sistema valida o teto de capacidade da sala antes de confirmar.

### 34.4 Distribuição Automática Inteligente
Para otimizar o início do ano letivo sem a necessidade de alocar aluno por aluno manualmente:
1. Clique no botão de cabeçalho **Distribuição Automática Inteligente** (ou na ação rápida do quadro de turmas).
2. Selecione as turmas participantes do rateio.
3. Escolha o critério do algoritmo:
   - **Equilíbrio Harmônico de Gênero (Recomendado):** Distribui circularmente meninas e meninos para garantir paridade proporcional entre as turmas.
   - **Ordem Alfabética:** Ordena os alunos de A a Z e divide em blocos homogêneos sequenciais.
   - **Equilíbrio por Faixa Etária:** Distribui ordenando por data de nascimento para igualar a média de idade entre as salas.
4. Defina as opções complementares:
   - *Redistribuir todos da série:* Reorganiza inclusive quem já estava ensalado.
   - *Respeitar estritamente o limite de vagas:* Impede qualquer superlotação acima da capacidade cadastrada da turma.
5. Clique em **Executar Distribuição**. Os alunos são alocados instantaneamente em transação segura no banco de dados.

### 34.5 Controle de Acesso e Permissões (Shield)
- **`View:Ensalamento`:** Permite visualizar os cenários, ocupação de salas e métricas de turmas.
- **`Manage:Ensalamento`:** Habilita ações de alocação manual, execução da distribuição automática, transferências e desensalamento.
- **Ajuda Contextual:** O botão de interrogação no cabeçalho exibe orientações adaptadas às permissões do usuário logado.

---

## 💰 35. Régua de Cobrança Inteligente e Redução de Inadimplência (`/admin/regua-cobrancas`)

O módulo de **Régua de Cobrança Inteligente** automatiza a comunicação financeira com as famílias em momentos estratégicos do ciclo de vencimento das faturas, diminuindo drasticamente os atrasos por esquecimento e fornecendo canais diretos de pagamento (linha digitável, PIX Copia e Cola e link do portal).

### 35.1 Conceito da Régua por Gatilhos de Dias
O sistema monitora diariamente as faturas em aberto e calcula a data de vencimento em relação ao dia atual:
- **Gatilhos Preventivos (Dias Negativos):**
  - **-5 dias:** Lembrete amigável avisando sobre o vencimento próximo.
  - **-2 dias:** Segundo lembrete preventivo com dados para pagamento.
- **Gatilho de Vencimento (Dia 0):**
  - Notificação "Vence Hoje" alertando para o pagamento no dia do vencimento.
- **Gatilhos de Inadimplência (Dias Positivos):**
  - **+3 dias:** Primeiro comunicado de atraso com aviso de fatura pendente.
  - **+7 dias:** Segunda notificação de cobrança amigável.
  - **+15 dias:** Aviso formal de regularização financeira pré-bloqueio de serviços.

### 35.2 Canais de Disparo Multicanal
Os lembretes podem ser configurados para os seguintes canais:
1. **E-mail:** E-mail formatado com detalhes da fatura, valor restante, vencimento e botão de pagamento.
2. **Portal da Família:** Notificação interna (Sininho) no painel dos pais.
3. **Push Notification:** Alerta direto na tela do smartphone dos responsáveis com app escolar instalado.
4. **Todos (Multicanal):** Disparo simultâneo em todos os canais cadastrados.

### 35.3 Macros Dinâmicas nas Mensagens
Ao cadastrar ou editar uma régua, utilize as seguintes variáveis automáticas:
- `{{RESPONSAVEL_NOME}}`: Nome do responsável financeiro ou familiar.
- `{{ALUNO_NOME}}`: Nome do estudante vinculado à matrícula do contrato.
- `{{NUMERO_FATURA}}`: Número de identificação da fatura (ex: #1234).
- `{{VALOR}}`: Saldo devedor atual da fatura formatado em Reais (ex: 550,00).
- `{{DATA_VENCIMENTO}}`: Data de vencimento no formato brasileiro (dd/mm/aaaa).
- `{{DIAS_ATRASO}}`: Quantidade exata de dias em atraso (caso vencida).
- `{{LINK_PAGAMENTO}}`: Link direto para visualização da fatura no Portal.
- `{{PIX_COPIA_COLA}}`: Código PIX para cópia instantânea no aplicativo bancário.

### 35.4 Cobrança Pontual e Histórico na Gestão de Faturas (`/admin/faturas`)
Além do envio automatizado noturno:
1. **Ação "Cobrar":** Em qualquer fatura em aberto na tabela de Faturas, o operador clica no botão **Cobrar**, escolhe o modelo de régua ou redige uma mensagem personalizada e dispara imediatamente para todos os responsáveis.
2. **Histórico de Lembretes:** Clique em **Histórico de Lembretes** para consultar a lista completa de notificações emitidas (data, horário, canal, destinatário e mensagem enviada).
3. **Executar Régua do Dia:** No topo da listagem de Faturas ou na listagem de Réguas, utilize o botão **Executar Régua do Dia** para forçar o processamento imediato sob demanda (com suporte a modo de simulação *Dry-Run*).

### 35.5 Automação Agendada (Cron)
O comando Artisan `php artisan cobranca:executar-regua` está programado no agendador do Laravel (`routes/console.php`) para rodar automaticamente todos os dias às **08:00**, garantindo que nenhum lembrete seja esquecido.

---

## 🚀 36. Atualização do Sistema e Manutenção de Banco em Produção (`/admin/git-pull`)

A ferramenta de atualização do sistema permite aos Super Administradores manter o ambiente de produção sincronizado com a branch `main` do repositório Git e gerenciar a aplicação de esquemas de banco de dados e otimização de caches diretamente pelo painel administrativo.

### 36.1 Recursos Disponíveis:
1. **Atualização Completa (Git Pull Origin Main):**
   - Baixa os commits mais recentes do repositório remoto.
   - Executa a limpeza completa de cache de rotas, configurações, visualizações e componentes (`optimize:clear`).
   - Aplica automaticamente todas as migrações de banco de dados pendentes de forma segura (`migrate --force`).
   - Apresenta feedback com o log exato das alterações baixadas e migrações executadas.
2. **Executar Migrações do Banco:**
   - Executa diretamente o comando de migrações (`migrate --force`) do Laravel utilizando o driver Artisan nativo da aplicação.
   - Cria imediatamente novas tabelas e colunas adicionadas ao sistema, sem depender de comandos no terminal do servidor de hospedagem.
3. **Limpar Caches do Sistema:**
   - Limpa os caches de compilação do Blade, esquemas do Filament, rotas e configurações para garantir que novas telas e recursos apareçam imediatamente.

### 36.2 Controle de Acesso:
- A página e o atalho de sincronização no cabeçalho do painel são de visibilidade e execução estritamente restritas a usuários autenticados com o papel de **Super Administrador** (`super_admin`).

---

## 🗂️ 37. Planejamento Pedagógico — Matriz Curricular, Salas, Grade Horária e Planos de Aula

Recursos para organizar o período letivo **antes do início das aulas**: definir quais disciplinas cada série deve ter, montar a grade de horários da semana, reservar salas físicas e planejar o conteúdo com antecedência.

### 37.1 Matriz Curricular (`Acadêmico → Séries`)

1. Abra uma **Série** e vá na aba **Matriz Curricular**.
2. Cadastre cada disciplina que a série deve ter, com a **carga horária semanal** (em aulas), se é **obrigatória** ou optativa, e a **ordem**.
3. Sempre que uma **turma nova** dessa série for criada, o sistema já vincula automaticamente essas disciplinas a ela (aba **Disciplinas** da turma) — sem professor definido, para a coordenação atribuir depois.
4. Se a turma já existir (ou a matriz for alterada depois), use o botão **Sincronizar Disciplinas da Matriz**, disponível tanto na aba Matriz Curricular da Série (aplica a todas as turmas da série) quanto na edição da própria Turma (aplica só a ela). Só **adiciona** o que falta — nunca remove uma disciplina vinculada manualmente.

### 37.2 Salas (`Acadêmico → Salas`)

Cadastro dos ambientes físicos da escola — salas de aula, laboratórios, quadra, auditório etc. — usados para reservar espaço na grade horária. Informe a **unidade**, o **nome**, a **capacidade** (opcional, usada apenas como referência) e o **tipo**. Salas podem ser desativadas sem serem excluídas.

> Este cadastro é diferente do recurso de **Ensalamento em Lote Assistido** (seção 34), que distribui **alunos entre turmas**. Aqui o assunto é o **espaço físico** usado pela turma em cada aula.

### 37.3 Grade Horária (na Turma → aba "Grade Horária")

1. Abra a turma e vá na aba **Grade Horária**.
2. Cadastre cada horário recorrente da semana: **disciplina**, **professor**, **sala** (opcionais), **dia da semana** e **horário de início e fim**. Essa grade vale para todas as semanas do período letivo — é o "quadro de horários" da turma.
3. **Detecção de conflitos:** ao salvar, o sistema verifica se a turma, o professor ou a sala já têm outro horário cadastrado no mesmo dia da semana que se sobrepõe ao horário informado, e bloqueia o cadastro com uma mensagem explicando o conflito encontrado.
4. **Gerar Cronograma do Período:** na listagem de **Turmas**, use esta ação para transformar a grade horária em aulas reais no diário (cronograma de aulas) para todo o período letivo da turma. Dias não letivos (feriados, recessos) são pulados automaticamente, e rodar a ação de novo não duplica aulas já geradas — é seguro repetir depois de ajustar a grade.

### 37.4 Planos de Aula (`Acadêmico → Planos de Aula`)

Permite ao professor planejar o que vai lecionar antes da aula acontecer.

1. Crie um plano informando **turma**, **disciplina** (só aparecem as disciplinas já vinculadas à turma), **data prevista**, **objetivos**, **metodologia**, **recursos necessários**, **avaliação prevista** e as **habilidades da BNCC** que pretende trabalhar. É possível anexar materiais de apoio.
2. Quando a aula acontecer, clique em **Executar** na listagem: o sistema cria automaticamente o registro correspondente no diário de aulas (cronograma), levando o conteúdo planejado e as habilidades para lá, e permite ajustar a data caso a aula tenha sido dada em dia diferente do previsto.
3. Um plano **executado** não pode mais ser editado (o registro real do diário passa a ser a fonte da verdade) nem executado de novo.
4. Cada professor só vê os planos das turmas onde é professor da disciplina, professor conselheiro ou responsável pelo próprio plano; coordenação e administração veem todos.

---

## 🎬 38. Central de Ajuda — Vídeos Tutoriais (`/admin/video-tutorials`)

A **Central de Ajuda** reúne vídeos curtos que ensinam a usar as principais funcionalidades do Torre360, acessível pelo grupo **Ajuda** do menu lateral.

### 38.1 Assistir e Baixar Vídeos
1. Em **Ajuda → Vídeos Tutoriais**, a listagem mostra título, categoria, duração e situação (ativo/inativo) de cada vídeo.
2. **Assistir:** Abre o vídeo direto no navegador, em um player embutido — não precisa baixar nada. Funciona tanto para arquivos enviados ao sistema quanto para links do YouTube/Vimeo (o link é convertido automaticamente em player incorporado).
3. **Baixar / Abrir Link:** Baixa o arquivo de vídeo, ou abre o link externo em uma nova aba, quando o vídeo estiver hospedado fora do sistema.

### 38.2 Cadastro de um Novo Vídeo (`Create:VideoTutorial`)
1. Clique em **Criar Vídeo Tutorial** e preencha **Título**, **Descrição** e **Categoria** (texto livre, ex: CRM, Secretaria, Acadêmico).
2. Envie um **arquivo de vídeo** (até 40MB, formatos MP4/WebM/OGG) ou informe um **link externo** (YouTube/Vimeo). Se as duas opções forem preenchidas, o arquivo enviado tem prioridade sobre o link.
3. **Tela Relacionada:** Selecione opcionalmente a tela do sistema à qual esse vídeo se refere. Quando preenchido, o vídeo passa a aparecer automaticamente dentro do botão de **Ajuda** daquela tela específica, além de continuar disponível na Central de Ajuda.
4. **Ordem** define a posição de exibição na listagem; **Ativo** controla se o vídeo fica visível (um vídeo inativo some tanto da Central de Ajuda quanto do modal de Ajuda da tela relacionada).

### 38.3 Vídeo Dentro do Botão de Ajuda de Cada Tela
Além da Central de Ajuda, o vídeo relacionado a uma tela aparece automaticamente dentro do próprio modal de **Ajuda** daquela página, acima do texto explicativo de sempre. Hoje isso já está configurado para: **Kanban de Interessados**, **Acompanhamento de Rematrículas**, **Lançamento de Frequência**, o fluxo de **Avaliações** (Gestão de Avaliações, Cadastrar Avaliação e Lançamento de Notas em Grade), o **Assistente de Matrícula (Wizard)**, o fluxo de **Emissão de Documentos com QR Code** na Secretaria Digital (Gestão de Documentos, Emitir Documento) e o **Cadastro de Novos Usuários**.

### 38.4 Permissões
- **Ver e assistir:** Professor, Secretaria, Coordenador, Administrador e Super Administrador.
- **Cadastrar, editar e excluir vídeos:** Secretaria, Administrador e Super Administrador.

---

## ❓ 39. Botão de Ajuda em Todas as Telas

Cada tela do menu lateral está recebendo, aos poucos, o botão **Ajuda** (ícone ❓ cinza, no canto superior direito do cabeçalho da página).

### 39.1 Como usar
1. Abra qualquer tela e clique em **Ajuda**.
2. O quadro mostra, de cima para baixo: o **título com emoji e um resumo da tela**, o **vídeo tutorial** (quando houver um vinculado, veja a seção 38), os **tópicos** (🎯 o que você pode fazer, 🚀 passo a passo), **dicas** 💡 e **alertas** ⚠️.
3. Os tópicos se adaptam às suas permissões: ações que você não pode executar (ex.: criar ou editar) não aparecem na ajuda.
4. Clique em **Fechar** para voltar à tela.

### 39.2 Telas já cobertas
- **Todas as telas que já tinham o botão** ganharam o novo visual automaticamente: cabeçalho com resumo, seções coloridas, emojis nos títulos e tópicos, passos numerados e dicas em destaque (também em modo escuro).
- **Acadêmico:** Cursos, Planos de Aula, Salas, Frequências Escolares e Fechamento do Ciclo Letivo.
- **Secretaria:** Coordenadores e Matrículas (ajuda reescrita no novo formato, incluindo as abas Todas, Pendentes, Ativas e Canceladas).
- **Avaliações e Currículo (BNCC):** Notas e Habilidades.
- **Preceptoria e Calendário:** Ciclos de Preceptoria, Relatórios de Preceptoria, Templates de Relatório e Dias Não Letivos.
- **Financeiro e Operacional:** Fornecedores, Transações Bancárias, Ordens de Serviço, Contas a Pagar, Relatório de Inadimplência e Fluxo de Caixa.
- **Configurações (tabelas auxiliares):** Bancos, Cidades, Estados, Códigos BACEN, Centros de Custo, Plano de Contas, Turnos, Tipos de Vínculo, Tributações dos Cursos, Etapas Avaliativas, Categorias de Avaliação, Categorias de OS, Áreas de Conhecimento, Campos de Experiência, Configurações e Endereços.
- **Sistema e Segurança e Início:** Logs de Atividade (somente Super Administrador), E-mails Enviados e a tela Início.
- **Portal da Família/Aluno (`/portal`):** as 14 páginas (Início, Notas, Frequência, Horários, Calendário, Boletins, Documentos e Contratos, Solicitar Documentos Oficiais, Eventos e Atividades, Rematrícula Online, Financeiro, Ocorrências, Agendar Preceptoria e Central de Atendimento), com explicações em linguagem simples para as famílias.
- **Menu do Responsável/Aluno:** os links "Dados Cadastrais", "Boletim Escolar", "Preceptorias", "Documentos" e "Agendar preceptoria" também abrem telas com o botão de Ajuda.
- **CRM / Comercial:** Campanhas de Marketing, Comunicação em Massa, Leads da Landing Page e Modelos de WhatsApp.
- **Cobertura completa:** todas as telas do menu lateral do painel administrativo e do Portal da Família têm o botão de Ajuda. A única exceção é **Filament Shield → Roles** (tela de papéis e permissões, fornecida pelo pacote Shield), que por ora não tem o botão. Um teste automático impede que novas telas entrem no menu sem Ajuda. A lista de acompanhamento fica em `docs/ajuda_botoes_sidebar.md`.

### 39.3 Para a equipe de desenvolvimento
O guia de como montar a ajuda de uma nova tela (construtor `HelpContent` e botão `HasAjudaAction`) está em `docs/ajuda_modal_design.md`.

---

## 📜 40. Histórico Escolar Oficial Multi-Ano (`/admin/historico-escolars`)

O módulo de **Histórico Escolar Oficial Multi-Ano** atende a uma das exigências mais rigorosas da legislação educacional brasileira (Lei de Diretrizes e Bases da Educação Nacional - LDB nº 9.394/1996 e BNCC), permitindo consolidar toda a trajetória acadêmica do estudante em uma matriz curricular padronizada para **processos de transferência** ou **encerramento de ciclo (conclusão do Ensino Fundamental ou Ensino Médio)**.

### 40.1 Visão Geral e Benefícios
- **Matriz Curricular Tabular Oficial:** Apresenta todas as séries/anos cursados lado a lado em colunas, com os componentes curriculares organizados por Área do Conhecimento (Linguagens, Matemática, Ciências da Natureza, Ciências Humanas e Parte Diversificada).
- **Histórico Híbrido (Interno + Externo):** Permite registrar anos letivos cursados no Torre360 e também lançar notas, cargas horárias e estabelecimentos de ensino onde o aluno estudou antes de se transferir para a escola.
- **Sincronização com 1 Clique:** O sistema lê todas as matrículas ativas ou concluídas do estudante no Torre360, buscando as médias finais fechadas, cargas horárias da matriz curricular e taxas de frequência, montando a matriz automaticamente sem necessidade de digitação manual.
- **Emissão Oficial em PDF (A4 Paisagem):** Gera o documento timbrado oficial com cabeçalho institucional, dados do estudante, tabela matricial de desempenho, resumo cronológico de estabelecimentos cursados, termo formal de certificação de conclusão e assinaturas da Secretaria e Direção Escolar.
- **Carimbo Digital e QR Code de Autenticidade:** Cada histórico emitido possui um código alfanumérico único e QR Code criptográfico que permite a qualquer cartório, universidade ou escola de destino validar a autenticidade do documento em tempo real através do portal público (`/validar-documento`).

### 40.2 Como Emitir um Histórico Escolar
1. Acesse o menu lateral em **Secretaria → Histórico Escolar Multi-Ano** (`/admin/historico-escolars`).
2. Clique no botão **Novo Histórico Escolar**.
3. **Aba Dados Gerais:**
   - Selecione o **Estudante (Aluno)**.
   - Escolha o **Curso / Etapa** (ex: *Ensino Fundamental*, *Ensino Médio*).
   - Defina a **Situação do Aluno**:
     - *Em Curso:* para alunos que continuam estudando na instituição.
     - *Concluído:* para formandos (habilita o campo de **Data de Conclusão** e o termo formal de Certificado de Conclusão).
     - *Transferido:* para alunos em processo de transferência para outra escola.
   - A **Unidade Escolar** e a **Data de Expedição** já vêm preenchidas, e o **Código de Autenticidade** exclusivo é gerado automaticamente.
4. Clique em **Salvar**.
   - O sistema detecta automaticamente se o estudante possui matrículas internas no Torre360 e importa todos os anos letivos, séries, disciplinas e médias finais diretamente para o histórico!
5. **Aba Anos e Séries (Multi-Ano):**
   - Visualize e edite cada coluna da matriz curricular.
   - Para anos cursados em outra escola anterior, clique em **Adicionar Ano / Série**, selecione a origem como *Externo (Outra Escola)* e informe o nome do estabelecimento de ensino, cidade/UF, carga horária e as disciplinas com notas correspondentes.
6. **Aba Certificação e Observações:**
   - Personalize o texto oficial da certidão de conclusão ou adicione amparos legais específicos (ex: convalidações de estudos no exterior, exames de classificação, etc.).
---

## 🏛️ 41. Auto-Atendimento de Declarações e Documentos Oficiais no Portal da Família (`/portal/solicitacoes-documentos`)

O módulo de **Auto-Atendimento de Declarações e Documentos Oficiais** foi desenvolvido especificamente para **desafogar a secretaria escolar**, transferindo a emissão de certidões rotineiras para o próprio estudante ou seus responsáveis legais no Portal da Família, com total segurança jurídica, validação por QR Code e verificação financeira automática.

### 41.1 Benefícios para a Escola e as Famílias
- **Emissão Instantânea em 1 Clique:** O responsável não precisa abrir chamados burocráticos ou aguardar dias pela impressão de um documento. Ao clicar em **"Emitir Agora"**, o PDF oficial timbrado é gerado e assinado digitalmente em poucos segundos.
- **Régua de Segurança Financeira (Lei 12.007/2009):** O sistema impede automaticamente a emissão de **Declarações de Quitação de Débitos** caso a matrícula possua faturas em atraso ou vencidas em aberto, alertando com gentileza para regularização na aba Financeiro.
- **Validação Pública com QR Code Anti-Raspagem (LGPD):** Terceiros (empresas de transporte público, clubes, planos de saúde, outras escolas ou universidades) podem confirmar a autenticidade apontando a câmera do celular para o QR Code impresso no rodapé, que exibe a validação em ambiente seguro com mascaramento de dados sensíveis.
- **Atalhos Rápidos no Início (Dashboard):** Cada cartão de estudante na tela inicial do Portal possui o botão direto **"Declarações"**, permitindo que pais com múltiplos filhos alternem facilmente entre os dependentes.

### 41.2 Modelos Oficiais Disponíveis para Auto-Emissão
1. 🎓 **Declaração de Matrícula Regular:** Atesta o vínculo ativo do estudante, série, curso, turma, turno e período letivo.
2. 📅 **Declaração de Frequência Escolar:** Comprova o cumprimento de carga horária e assiduidade às aulas conforme as normas da LDB.
3. 🚌 **Declaração para Passe Escolar e Transporte Público:** Detalha endereço residencial, horários oficiais de entrada e saída das aulas para comprovação de itinerário junto a concessionárias e órgãos de trânsito.
4. ⏰ **Declaração de Turno e Horário de Aulas:** Informa a jornada escolar para fins de compatibilização com estágios supervisionados, programas de menor aprendiz ou atividades extracurriculares.
5. 💳 **Declaração de Quitação de Débitos:** Atesta a adimplência legal do período contratado, condicionada à verificação de quitação de todas as parcelas e faturas vencidas no sistema.
6. 📜 **Declaração de Conclusão de Série/Ano Letivo:** Declaração provisória de aprovação ao final do ano letivo enquanto o histórico definitivo é finalizado.
7. 🔄 **Declaração de Transferência e Reserva de Vaga:** Atesta a situação escolar provisória do estudante durante trâmites de transferência para outra unidade.
8. 📊 **Boletim Escolar Atualizado:** Atalho direto para download do boletim com notas e faltas consolidadas.
9. 🏛️ **Histórico Escolar Oficial Multi-Ano:** Integração direta com o módulo de Histórico Escolar Oficial Tabular para formandos e egressos.

---

## 🚀 42. Matrícula Externa 100% Self-Service para Novos Alunos (`/matricular-online`)

O módulo de **Matrícula Externa 100% Self-Service** permite que pais, mães e responsáveis legais realizem a matrícula integral de novos alunos diretamente pela internet, sem necessidade de deslocamento à secretaria escolar ou agendamento prévio. O processo é simples, rápido, seguro e atende rigorosamente às exigências da **LGPD (Lei Geral de Proteção de Dados)**.

### 42.1 Principais Benefícios
- **Captação Ativa 24h/7d:** A instituição passa a matricular estudantes a qualquer dia ou hora, aproveitando o momento de maior interesse das famílias.
- **Validação de Vagas em Tempo Real:** O sistema consulta a lotação da turma selecionada e bloqueia tentativas de matrícula quando o limite máximo de vagas da turma for atingido, prevenindo ocorrências de *overbooking*.
- **Assinatura Digital com Validade Jurídica:** Ao final do processo, o contrato de prestação de serviços educacionais é formalizado com registro de data/hora, endereço IP, dispositivo de origem e hash criptográfico SHA-256.
- **Onboarding Automático no Portal da Família:** Ao concluir o cadastro, o responsável recebe imediatamente uma conta no Portal da Família com perfil de acesso e e-mail com as instruções de boas-vindas.
- **Integração com o Funil de CRM:** Se o responsável ou estudante já tiver registrado interesse anteriormente no módulo de captação de leads (`/quero-matricular`), o lead é automaticamente convertido no CRM.

### 42.2 Os 5 Passos do Assistente de Matrícula (Wizard)

1. **Passo 1 — Escolha de Curso e Turma:**
   - Seleção da Unidade Escolar e do Curso/Nível pretendido.
   - Escolha da Série e da Turma desejada. O sistema exibe o turno e a disponibilidade de vagas em tempo real.
2. **Passo 2 — Dados do Estudante:**
   - Informações cadastrais do aluno: Nome completo, CPF, Data de Nascimento, Sexo, Cor/Raça e indicação de Necessidades Educacionais Especiais.
3. **Passo 3 — Responsável Legal & Financeiro:**
   - Dados do responsável: Nome, CPF, Telefone/WhatsApp, E-mail e Grau de Parentesco (Pai, Mãe, Responsável Legal).
   - Endereço residencial com preenchimento automático via busca de CEP (ViaCEP).
4. **Passo 4 — Upload de Documentos Digitais:**
   - Anexação de arquivos comprobatórios (PDF ou imagem):
     - Certidão de Nascimento ou RG do Aluno;
     - Documento de Identidade com Foto do Responsável (RG/CNH);
     - Comprovante de Residência Atualizado;
     - Histórico Escolar Anterior ou Declaração de Transferência.
5. **Passo 5 — Minuta Contratual e Aceites Digitais:**
   - Leitura da minuta resumida do Contrato de Prestação de Serviços Educacionais.
   - Marcação obrigatória de declaração de ciência dos termos contratuais, consentimento de tratamento de dados sob a LGPD e normas regimentais da escola.
   - Clique em **"Concluir Matrícula Online"**.

### 42.3 Confirmação e Protocolo
- O responsável é direcionado para a página de sucesso com o **Protocolo Oficial de Matrícula**, detalhamento da turma, dados do contrato aceito e lista dos documentos sob análise.
- A tela disponibiliza o botão **"Imprimir Comprovante"** para arquivo da família e um atalho direto para login no **Portal da Família** (`/portal`).
- A equipe da secretaria escolar recebe uma notificação instantânea no painel administrativo para validação documental e homologação da matrícula.

---

## 💳 43. Financeiro Automatizado: Gateway, Conciliação e Relatórios

Completa a Régua de Cobrança (seção 35) com a geração real de PIX/boleto, baixa automática por webhook, conciliação bancária e relatórios consolidados. Detalhes técnicos em `docs/financeiro_gateway_conciliacao_relatorios.md`.

### 43.1 Gerar Cobrança e Dados de Pagamento (`/admin/faturas`)
1. Na listagem de **Faturas**, uma fatura ainda sem cobrança gerada mostra o botão **Gerar Cobrança** — cria o PIX/boleto no gateway configurado (por padrão, um gateway de testes "Fake", até a escola configurar um gateway real).
2. Depois de gerada, o botão **Dados de Pagamento** mostra o código PIX copia-e-cola, a linha digitável e os links de pagamento/boleto para copiar ou repassar à família.
3. Com o gateway de testes ativo, aparece também **Simular Pagamento (Dev)** — confirma o pagamento na hora, como se a confirmação tivesse vindo do gateway de verdade, útil para testar o fluxo completo antes de configurar um gateway real.

### 43.2 Pagamento pelo Portal da Família (`/portal/financeiro`)
Na listagem de faturas do Portal, o responsável financeiro vê o botão **Pagar** em qualquer fatura em aberto: ao clicar, o sistema gera a cobrança automaticamente (se ainda não existir) e mostra o PIX copia-e-cola e os links de pagamento — sem precisar passar pela secretaria.

### 43.3 Conciliação Automática de Créditos (`/admin/transacao-bancarias`)
Ao importar um extrato (seção 23.3), o sistema tenta automaticamente casar cada crédito importado com uma fatura em aberto, por um número de fatura mencionado na descrição ou por valor e data próxima do vencimento — só concilia sozinho quando há exatamente uma fatura candidata. O botão **Conciliar Créditos Pendentes** reprocessa créditos que ficaram sem par (por exemplo, quando a fatura só foi gerada depois do crédito chegar).

### 43.4 Contas a Pagar (`/admin/conta-pagars`)
Cadastro de obrigações da escola com fornecedores: descrição, valor, vencimento, fornecedor, plano de contas e centro de custo. O botão **Dar Baixa** registra a saída no banco escolhido e marca a conta como paga. Contas pendentes vencidas são marcadas automaticamente como **Atrasado** todos os dias às 07h.

### 43.5 Relatórios

- **Relatório de Inadimplência** (`/admin/financeiro/relatorio-inadimplencia`): faturas em atraso com aluno, turma, responsável(is) e dias de atraso, com filtro por turma e por faixa de atraso (1–7, 8–15, 16–30, mais de 30 dias). Resumo no topo com total de faturas, valor devido e responsáveis inadimplentes.
- **Fluxo de Caixa** (`/admin/financeiro/fluxo-de-caixa`): entradas, saídas e saldo consolidados dos últimos 12 meses, a partir das transações bancárias já lançadas (baixas manuais, webhook e extratos importados).

---

## ✉️ 44. Pré-matrícula Online (Convite de Matrícula)

Link único enviado a um lead já qualificado pelo CRM para que a **própria família preencha a pré-matrícula**: dados do(s) responsável(is), do(s) aluno(s) e endereço. Esses dados chegam **já preenchidos no Assistente de Matrícula**, reduzindo muito o trabalho da secretaria. O link não permite ver nem navegar por nenhum outro cadastro.

### 44.1 Gerando o Link (`/admin/interessados`)
1. Na listagem de **Interessados**, um lead que já tenha ao menos um dependente cadastrado mostra, no menu **⋮ Mais ações**, a ação **Gerar Link de Pré-matrícula**.
2. Ao clicar, o sistema gera um link único (válido por 7 dias, de uso único) e mostra o endereço completo para copiar e enviar ao responsável (WhatsApp, e-mail etc.).
3. **Atenção:** abrir a ação de novo gera um link novo e **invalida o anterior** daquele lead.

### 44.2 O que a Família Preenche
1. **Responsável (você):** nome, CPF, data de nascimento, telefone, e-mail, vínculo com o aluno (Pai, Mãe etc.) e se é o responsável financeiro.
2. **Endereço da família:** CEP (o endereço é completado automaticamente), número e complemento.
3. **Segundo responsável (opcional):** nome, CPF, vínculo, contatos e se também é financeiro. Se os dois forem financeiros, informa-se o percentual do contrato de cada um.
4. **Cada aluno:** data de nascimento, CPF (se tiver), sexo, série de interesse e turno de preferência. O endereço dos alunos segue o da família.
5. **Aceite LGPD (obrigatório):** a família concorda com o uso dos dados para a matrícula. A data/hora e o IP do aceite ficam registrados.
6. O CPF é validado pelos dígitos verificadores. Depois de enviar, aparece a tela de agradecimento e o link não pode mais ser usado. Link expirado, já usado ou inválido mostra um aviso claro, com atalho para o formulário público completo.

### 44.3 O que Muda para a Secretaria
- O envio **não efetiva a matrícula por si só.** O histórico do lead recebe o registro "A família preencheu a pré-matrícula online…" e o Lead Score é recalculado.
- Ao clicar em **Matricular**, o **Assistente de Matrícula** abre com responsáveis (inclusive percentual financeiro), alunos, CPF, data de nascimento, sexo e endereço já preenchidos. A secretaria revisa, escolhe unidade, turma e plano, e conclui.
- Se o CPF informado já existe no sistema, o cadastro existente é reaproveitado.
- **Privacidade:** os dados da pré-matrícula ficam guardados no lead apenas até a matrícula ser efetivada; ao converter o lead, o rascunho é apagado.
- **Fora do escopo desta versão:** envio de documentos (RG, certidão, comprovante) e endereço diferente por aluno. A secretaria pode ajustar o endereço no assistente.

---

> **Torre360** — Gestão inteligente para instituições de ensino.


