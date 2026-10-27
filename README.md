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
| `MatrizCurricular` | Grade de referência (série × disciplina): carga horária semanal e obrigatoriedade. Origem do vínculo `turma_disciplina` ao criar uma turma. |
| `PeriodoLetivo` | **Eixo temporal central.** Ex: "1º Semestre 2025". Possui várias Turmas, Etapas e Dias Não Letivos. |
| `DiaNaoLetivo` | Feriados/recessos vinculados a um PeriodoLetivo |
| `Turma` | Classe de alunos — pertence a uma Série, Turno e PeriodoLetivo |
| `Sala` | Ambiente físico (sala de aula, laboratório...) de uma unidade, usado na grade horária |
| `GradeHorario` | Quadro de horários semanal recorrente da turma (disciplina, professor, sala, dia e horário), com detecção de conflitos. Gera o `CronogramaAula` do período. |
| `PlanoAula` | Planejamento prévio de uma aula (objetivos, metodologia, habilidades BNCC); ao ser executado, vira um `CronogramaAula` |
| `EtapaAvaliativa` | Bimestre/trimestre — pertence a um PeriodoLetivo |
| `Avaliacao` | Prova/trabalho — vinculada a uma EtapaAvaliativa, Disciplina e Turma. Possui `data_prevista`, `nota_maxima`, `peso_etapa_avaliativa`. |
| `Nota` | Nota individual — vincula uma Avaliacao a uma Matricula |
| `CronogramaAula` | Diário de aulas datado por Turma, Disciplina e Professor |
| `Coordenador` | Vincula uma Pessoa como coordenadora de um Curso |
| `SituacaoFinalDisciplina` | Situação final (Aprovado/Recuperação/Reprovado) de uma matrícula numa disciplina, gerada pelo **Fechamento do Ciclo Letivo**. Suporta recuperação anual ou por etapa e exame final, ambos configuráveis por `PeriodoLetivo` — detalhes em `docs/recuperacao_etapa_exame_final.md`. |

**Hierarquia pedagógica:**
```
Serie
 └── MatrizCurricular (Disciplina + carga horária) ──> popula turma_disciplina

PeriodoLetivo
 ├── DiaNaoLetivo
 ├── EtapaAvaliativa
 │    └── Avaliacao (+ Disciplina + Turma)
 │         └── Nota (por Matricula)
 └── Turma
      ├── GradeHorario (Disciplina + Professor + Sala) ──> gera ──> CronogramaAula
      ├── PlanoAula (Disciplina + Professor) ──> ao executar ──> CronogramaAula
      ├── CronogramaAula
      └── Matricula
```

---

### 4. 📝 Secretaria
Operação de secretaria virtual e vínculo aluno–escola. Rematrícula e matrícula por convite em `docs/matricula_rematricula_online.md`.

| Recurso | Descrição |
|---|---|
| `SituacaoMatricula` | Status da matrícula (Ativo, Trancado, Evadido...) |
| `Matricula` | Vínculo Aluno ↔ Turma. Permite criação rápida de Pessoa diretamente no formulário. Exibe alunos como "Nome - CPF". |
| `Contrato` | Geração de contrato derivado de uma Matricula |
| `TipoDocumento` | Documentos exigidos por Curso/Turma/Matrícula |
| `PeriodoRematricula` / `Rematricula` | Campanha de rematrícula online: a família confirma os dados pelo Portal, o sistema gera a nova Matrícula, Contrato, faturas e envia para assinatura via Assinafy automaticamente — a rematrícula só fica `Confirmada` quando o contrato é assinado |
| Convite de Matrícula Online | Link único e temporário (`ConviteMatriculaService`) enviado a um lead do CRM para confirmar/completar dados sem expor outros registros, antes da secretaria efetivar a matrícula |

---

### 5. 💰 Financeiro
Controle de tesouraria e cobrança. Detalhes do gateway, webhook e conciliação em `docs/financeiro_gateway_conciliacao_relatorios.md`.

| Recurso | Descrição |
|---|---|
| `ResponsavelFinanceiro` | Pessoa responsável pelos pagamentos de um contrato |
| `Fatura` | Parcelas/cobranças vinculadas a um contrato (tabela `faturas`, renomeada da antiga `titulos`). Tem PIX/boleto/link de pagamento via `GatewayPagamento` e baixa automática por webhook. |
| `TributacaoCurso` | Natureza fiscal do curso para NFS-e |
| `ReguaCobranca` | Lembretes automáticos por gatilho de dias antes/depois do vencimento, multicanal |
| `ContaPagar` | Contas a pagar (fornecedor, plano de contas, centro de custo), com baixa manual |
| `GatewayPagamento` (contrato) | Abstração de gateway de cobrança — driver `Fake` por padrão, resolvido por `GatewayPagamentoManager` |
| Relatórios | Inadimplência (por turma/faixa de atraso/responsável) e Fluxo de Caixa consolidado mensal |

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

> **Vários papéis:** o usuário atua em um papel por vez (menu **Atuar como**). O escopo "só o que eu leciono" do professor vale apenas enquanto o papel ativo é `professor` (`User::atuaComoProfessor()`); quem também é `super_admin` vê e lança tudo ao atuar como `super_admin`. Detalhes em `docs/escopo_professor_papel_ativo.md`.

---

### 7. 🎯 CRM e Captação
Funil de captação de alunos (menu **CRM / Comercial**). Detalhes em `docs/crm_captacao_campanhas_visitas.md`, `docs/crm_lead_score.md` e `docs/crm_followup_whatsapp.md`.

| Recurso | Descrição |
|---|---|
| `Interessado` | Lead com funil Kanban, lead score, histórico de contatos, dependentes e atribuição de campanha/UTM |
| `CampanhaMarketing` | Campanhas com código UTM e investimento; leads do formulário `/quero-matricular?utm_campaign=CODIGO` são atribuídos automaticamente |
| `VisitaInteressado` | Visitas à escola agendadas por lead, exibidas no calendário de follow-up e lembradas 24h antes (`crm:notificar-pendentes`) |
| Conversão | Ação **Matricular** abre o Assistente de Matrícula pré-preenchido com o lead e o marca como convertido ao finalizar |
| Repasse ao consultor | Ações **Enviar ao consultor** (linha) e **Enviar aos consultores (WhatsApp)** (lote) abrem um link `wa.me` com o contato direto do lead e o resumo dos últimos contatos (`ConsultorWhatsappService`) |
| Redes sociais do lead | Botão **Redes sociais** (linha da tabela de interessados) abre o perfil cadastrado em nova aba, ou uma lista quando há mais de um; só links `http(s)` viram botão (`Interessado::redesSociaisLinks()`) |
| Widgets | `ConversaoCampanhaWidget` e `ConversaoOrigemWidget`: leads, matrículas, taxa e custo por campanha/origem |
| `LandingLead` | Pedidos de demonstração da landing page (leads B2B, separados de `Interessado`) |

---

### 8. 📣 Comunicação
Canais de mensagem e disparo em massa. Detalhes em `docs/comunicacao_canais_alerta_falta_massa.md`.

| Recurso | Descrição |
|---|---|
| `CanalMensagem` (contrato) | Abstração de envio por e-mail (`EmailCanal`) ou push (`FcmCanal`), resolvida por `CanalMensagemManager` |
| Alerta de falta | Observer em `FrequenciaEscolar`: ao lançar falta, notifica o(s) responsável(is) por e-mail, sino e push |
| `ComunicacaoEmMassa` | Envio de e-mail segmentado (leads do CRM por status/origem, ou responsáveis por turma) ou para uma seleção explícita, processado em fila |
| `Pessoa.aceita_comunicacao` | Opt-out de comunicações em massa (LGPD); não afeta notificações individuais obrigatórias |

---

### 9. 🧑‍💼 RH e Gestão de Funcionários
Cadastro de funcionários, contratos de trabalho e férias. Detalhes em `docs/rh_funcionarios_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `Funcionario` | Cargo, regime (CLT/Estatutário/PJ/Estágio), admissão/desligamento, carga horária, unidade de lotação — ligado a `Pessoa` |
| `ContratoTrabalho` | Histórico de vigências salariais (append-only); "Registrar Aditivo" encerra a vigência atual e cria a próxima com o novo salário |
| `PeriodoFerias` | Período aquisitivo, dias de direito/gozados, status; ação "Registrar Gozo"; prazo de concessão (CLT, art. 134) calculado |
| **Painel de Férias** | Férias vencidas (risco de pagamento em dobro), com prazo acabando ou sem registro, para CLT ativos; aviso semanal só com contagens (`rh:avisar-ferias`) |
| `SubstituicaoProfessor` | Professor titular × substituto por turma/disciplina e período |

---

### 10. 📚 Biblioteca Escolar
Acervo de livros e controle de empréstimo/devolução. Detalhes em `docs/biblioteca_escolar_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `Livro` | Título, autor, ISBN, categoria, editora, quantidade total/disponível de exemplares |
| `Emprestimo` | Livro × matrícula, data de empréstimo, devolução prevista/real, status (Emprestado/Devolvido/Atrasado) |
| Atraso automático | `biblioteca:atualizar-emprestimos-atrasados` (diário, 07h) marca empréstimos vencidos como Atrasado |

---

### 11. ⚠️ Risco de Evasão Escolar
Score de risco de evasão (0-100) para alunos matriculados, espelhando o Lead Score do CRM. Detalhes em `docs/risco_evasao_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `RiscoEvasaoService` | Calcula o score a partir de frequência (40 pts), desempenho (35 pts) e inadimplência (25 pts) |
| `Matricula.risco_evasao_score` | Exibido como badge colorido na listagem de Matrículas (verde/âmbar/vermelho) |
| `ConfiguracaoRiscoEvasao` | Página de pesos em `/admin/secretaria/pesos-risco-evasao` (admin/super_admin) |
| Recálculo | Comando agendado diário `academico:recalcular-risco-evasao` (06h30) + ação manual "Recalcular todas as matrículas" |

---

### 12. 🎁 Bolsas e Descontos Educacionais
Bolsas e descontos recorrentes concedidos a alunos matriculados, aplicados automaticamente no financeiro. Detalhes em `docs/bolsas_descontos_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `TipoBolsa` | Modelo de bolsa/desconto (nome, percentual máximo, se exige aprovação, critério de renovação) |
| `BolsaConcedida` | Concessão para um aluno: percentual, vigência, status (Solicitada/Aprovada/Recusada/Encerrada) |
| Aprovação | Tipos sem `exige_aprovacao` já nascem aprovados; os demais passam por Aprovar/Recusar |
| Integração financeira | `GeracaoFaturasContratoService` aplica o percentual ativo automaticamente em cada item de fatura gerado |

---

### 13. 📦 Patrimônio Escolar
Controle de bens patrimoniais (computadores, mobiliário, material de laboratório). Detalhes em `docs/patrimonio_estoque_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `BemPatrimonial` | Descrição, número de patrimônio, categoria, valor/data de aquisição, unidade/sala, fornecedor, status |
| `MovimentacaoPatrimonio` | Histórico somente leitura de transferências e mudanças de status |
| Ações | "Transferir" (muda unidade/sala) e "Mudar Status" (em uso/em manutenção/baixado), ambas registrando histórico |

---

### 14. 🌞 Agenda Diária (Educação Infantil)
Registro diário da criança (humor, soneca, refeições, atividades, higiene), distinto de `MaterialAula` e `EventoEscolar`. Detalhes em `docs/agenda_diaria_educacao_infantil_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `RegistroRotinaDiaria` | Um por aluno por dia: humor, horário de soneca, atividades, higiene, foto opcional |
| `RefeicaoRotina` | Lista de refeições do dia (nome livre + quanto comeu), gerenciada via Repeater no próprio formulário |
| Portal da Família | Histórico por data, com "Ver Detalhes" mostrando a lista completa de refeições |

---

### 15. 🛡️ Autorização de Uso de Imagem e Consentimentos
Consentimentos da família por aluno (ex.: uso de imagem), com aceite digital simples no Portal (IP + timestamp) em vez de assinatura eletrônica completa. Detalhes em `docs/autorizacao_uso_imagem_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `TipoConsentimento` | Catálogo (nome, texto, se exige renovação periódica) |
| `ConsentimentoMatricula` | Status (Pendente/Autorizado/Não Autorizado), vigência, dados do respondente |
| Portal da Família | Lista os consentimentos ativos do aluno selecionado, com botões Autorizar / Não Autorizar |

---

### 16. 📁 Repositório de Conteúdo Pedagógico Digital
Apostilas e vídeo-aulas por turma/disciplina, publicadas para o aluno estudar fora do horário de aula. Detalhes em `docs/conteudo_pedagogico_digital_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `MaterialAula` | Título, descrição, turma, disciplina, professor responsável, tipo (Apostila/Vídeo-aula/Link Externo), data de publicação, visibilidade |
| Campo condicional | Upload de arquivo (apostila) ou URL (vídeo/link), de acordo com o tipo selecionado |
| Portal do Aluno | Página "Materiais" lista o conteúdo visível da turma do aluno selecionado, com ação "Abrir" |

---

### 17. ⏳ Lista de Espera por Turma Lotada
Fila de pretendentes para turmas lotadas, notificados automaticamente quando abre vaga. Detalhes em `docs/lista_espera_matricula_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `ListaEsperaMatricula` | Turma, período letivo, aluno (pessoa), origem no CRM (interessado/dependente, opcional), status (Aguardando/Notificado/Convertido/Desistiu) |
| Notificação automática | Cancelamento, transferência ou exclusão de matrícula que libera vaga notifica o primeiro da fila (e-mail, push e sininho) |
| Conversão automática | Nova matrícula da mesma pessoa na turma marca a entrada da fila como Convertida |

---

### 18. 🔄 Transferência Escolar
Processo de saída (para outra escola) e entrada (vinda de outra escola), reaproveitando o Histórico Escolar e o motor de declarações já existentes. Detalhes em `docs/transferencia_escolar_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `TransferenciaEscolar` | Matrícula, tipo (Saída/Entrada), escola externa (nome/cidade/UF), data, motivo, status |
| "Concluir Saída" | Emite a Declaração de Transferência (`DocumentoService`) e fecha a matrícula como Cancelada |
| "Marcar Histórico Recebido" | Encerra o processo de Entrada após lançar o ano externo no Histórico Escolar |

---

### 19. 📋 Avaliação de Desempenho Docente (Onda 19)
Ciclos de observação da coordenação com instrumento de critérios ponderados. Dado sensível (parecer e observações criptografados). Detalhes em `docs/avaliacao_desempenho_docente_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `AutoavaliacaoDocente` | O professor se avalia nos critérios do ciclo; rascunho privado, envio único, comparativo com as observações |
| `PlanoDesenvolvimentoDocente` | Plano individual nascido de uma avaliação finalizada, com ações sugeridas, ciência e andamento pelo professor |

---

### 20. 🏟️ Reserva de Espaços (Onda 21)
Agendamento pontual de auditório, quadra e laboratório, com checagem de conflito contra reservas e contra a grade fixa. Detalhes em `docs/reserva_espacos_roadmap.md`.

---

### 21. 🖼️ Portfólio do Aluno e Parecer Descritivo (Onda 30)
Evidências de aprendizagem com arquivos privados, parecer em rascunho/revisão/publicação e página no Portal da Família. Rascunho por IA desligado por padrão e sem dados pessoais. Detalhes em `docs/portfolio_parecer_descritivo_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `PortfolioItem` | Evidência por matrícula, com habilidades, anexos privados e compartilhamento com a família (nunca com material de outros alunos) |
| `ParecerDescritivo` | Um por aluno e etapa; o professor escreve, a coordenação publica |

---

### 22. 🛠️ Manutenção Preventiva (Onda 39)
Planos recorrentes de bens e espaços que abrem ordens de serviço no prazo (`manutencao:gerar-ordens`). Detalhes em `docs/manutencao_preventiva_roadmap.md`.

---

### 23. 🍽️ Cardápio e Alergias (Onda 40)
Cardápio por dia e refeição com alérgenos declarados, cruzamento com a ficha médica em três níveis de certeza e aviso no Portal só para a restrição do próprio filho. Detalhes em `docs/cardapio_alergias_roadmap.md`.

---

### 24. ⏰ Ponto Eletrônico e Banco de Horas (Onda 41)
Registro de ponto íntegro (NSR por unidade, hash SHA-256 encadeado, só inserção), apuração com tolerância da CLT, espelho, ajustes com aprovação e banco de horas com prazo de compensação. **Opera como controle interno**: os modos REP-A e REP-P (Portaria MTP 671/2021) ficam bloqueados até os requisitos legais serem cumpridos. Detalhes, requisitos e como resolver em `docs/ponto_banco_horas_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `PontoRegistro` | Marcação com horário do servidor, NSR, hash e conferência da hora legal; imutável (model e gatilhos) |
| `PontoTratamento` | Ajuste (incluir, desconsiderar, abonar) que não altera o original; quem pede não aprova |
| `PontoBancoHorasLancamento` | Razão do banco de horas com vencimento do crédito e expiração em horas a pagar |
| `PontoConfiguracao` | Modo e requisitos legais por unidade, com a lista do que falta |

---

### 25. 📈 Reajuste de Mensalidades e Informe Anual (Ondas 44 e 42)
Entrega unificada do ciclo financeiro anual da família. Detalhes em `docs/reajuste_e_informe_anual_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `CenarioReajuste` | Simulação por série a partir dos contratos do período de origem; aprovação, aplicação na campanha de rematrícula, alertas da Lei nº 9.870/1999 e lista de comunicação |
| `PeriodoRematriculaPreco` | Preço do novo contrato por série; sem ele, vale o valor único da campanha |
| **Informe Anual** | Pagamentos recebidos no ano por aluno, parte estimada de instrução e responsáveis; PDF individual, em lote e no Portal |

---

### 26. 🏫 Censo Escolar: Situação do Aluno (Onda 58)
A 2ª etapa do Censo Escolar, que completa a exportação da matrícula inicial. Detalhes em `docs/censo_escolar_situacao_aluno_roadmap.md`.

| Recurso | Descrição |
|---|---|
| **Censo Escolar (2ª etapa)** | Apura a situação de cada aluno (aprovado, reprovado, transferido...) e mostra o que impede o envio; gera os arquivos de Situação do Aluno (registros 89, 90 e 91) e de identificação no leiaute do Inep |
| Retorno do Educacenso | Importa o código Inep das turmas, o ID Inep dos alunos e o código das matrículas, sem sobrescrever o que já existe |
| `CensoSituacaoAluno` | Ajuste manual da situação pela secretaria, com justificativa cifrada |

---

### 27. 🎒 Lista de Material Escolar (Onda 59)
Detalhes em `docs/lista_material_escolar_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `ListaMaterialEscolar` | Uma lista por série e período, em rascunho até a secretaria publicar; cópia dos itens de outro período |
| Trava de uso coletivo | Barra material de uso coletivo e pede confirmação para o que pode ser individual ou coletivo (Lei nº 9.870/1999, art. 1º, § 7º); sem campo de marca ou loja |
| **Lista de Material** (Portal) | A família vê a lista publicada da série do aluno e baixa o PDF |

---

### 28. 📄 Cancelamento de Matrícula e Distrato (Onda 61)
Do pedido da família ao distrato assinado, com o destino de cada fatura. Detalhes em `docs/cancelamento_matricula_distrato_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `CancelamentoMatricula` | Pedido pelo Portal ou registrado pela secretaria; aprovar, recusar, retirar e concluir, com o motivo criptografado e fora do log |
| Proposta por fatura | Mantém a entrada e o que vence até o fim do mês da saída; cancela o que vence depois e não foi pago; devolve o pago adiantado. A secretaria troca cada decisão |
| **Distrato** (PDF) | Termo com as faturas, valores, multa rescisória (se houver) e a nota da Lei nº 9.870/1999; cópia assinada em área privada |
| Conclusão | Matrícula cancelada, faturas canceladas (cobrança tirada do gateway), rematrícula sem contrato cancelada e Declaração de Transferência opcional |

---

### 29. 🧮 Encargos por Atraso (Onda 62)
Multa e juros calculados na hora, com o teto legal. Detalhes em `docs/encargos_atraso_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `EncargosAtrasoService` | Multa de mora uma vez (teto de 2%, CDC art. 52, § 1º) e juros simples por dia; vencimento em fim de semana ou feriado vale no dia útil seguinte; carência opcional; percentuais por contrato |
| `Fatura::emAtraso()` | Atrasada ou pendente/parcial vencida. **Corrigiu o Relatório de Inadimplência**, que só listava o status `Atrasado`, que ninguém gravava |
| **Demonstrativo de débito** (PDF) | Faturas vencidas com multa, juros e total; no Portal da Família e na equipe |

---

### 30. 🧑‍💼 Admissão e Desligamento de Funcionários (Onda 65)
Lista de providências com prazo e comprovante, e retirada dos acessos na saída. Detalhes em `docs/admissao_desligamento_funcionarios_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `ProcessoFuncionario` | Admissão ou desligamento com a lista padrão de `config/rh_processos.php` (documentos, exame, contrato e eSocial, acessos, equipamentos, treinamento, financeiro) |
| Concluir | Exige as providências obrigatórias; no desligamento, a partir do último dia, grava a data e tira os papéis de equipe (mantém os de família; super admin só avisa) |
| Privacidade | Comprovantes em área privada, só para quem tem `View:ProcessoFuncionario`; motivo e observação cifrados e fora do log |

---

### 31. 🗓️ Grade Horária Assistida (Onda 64)
A grade de cada turma conferida contra a matriz curricular, com sugestão dos horários que faltam. Detalhes em `docs/grade_horaria_assistida_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `TempoAula` | O "sinal" de cada turno, com geração automática; um horário da grade vale tantas aulas quantos tempos couberem |
| `DisponibilidadeProfessor` | Janelas em que o professor pode ou não dar aula; sem janelas, está livre |
| **Grade Assistida** | Conferência com a matriz (aulas esperadas × na grade) e sugestão gulosa que respeita tempos livres, professor ocupado em outras turmas, disponibilidade e limite diário; nunca troca o que já existe |
| Conflito por período | O conflito de professor e sala só vale entre turmas de períodos que se cruzam; turma cancelada não ocupa ninguém |

---

### 32. ✍️ Assinatura Eletrônica de Documentos e Editor de Modelos (Onda 69)
Qualquer documento do sistema pode ir para assinatura pelo Assinafy, a partir de um modelo editável. Detalhes em `docs/assinatura_eletronica_documentos_roadmap.md`.

| Recurso | Descrição |
|---|---|
| **Modelos de Documentos** (ampliado) | Contextos de dados (aluno, cancelamento de matrícula, funcionário), catálogo único de variáveis (`MacrosDocumentoService`), guia dinâmico, **recusa de variável inexistente**, prévia com dados de um registro, histórico de alterações e modelos padrão (distrato, contrato de trabalho e termo de confidencialidade) que nascem inativos |
| `DocumentoAssinatura` | Documento avulso: cópia do texto, PDF com SHA-256, assinantes sugeridos (família e representante da escola, ou funcionário), envio retomável, situação por webhook, botão e rotina horária (`documentos:reconciliar-assinaturas`), certificado baixado e guardado |
| Reação ao documento assinado | O distrato assinado vira a cópia assinada do cancelamento; o contrato de trabalho ou termo conclui a providência de RH. Acontece uma vez e só com o certificado em mãos |
| Privacidade | PDFs em área privada, abertos para a equipe com permissão, para quem assina (pelo e-mail) e para a família do aluno; documento de RH só para quem vê Admissão e Desligamento; links de assinatura criptografados; log sem texto nem e-mails |
| Fluxo do contrato | Intocado: o webhook tenta o contrato primeiro e, se o documento não for dele, o avulso |

---

### 33. 📥 Mesa de Pendências por Papel (Onda 71)
Uma tela com o que espera ação do papel ativo, reunindo as filas dos módulos. Detalhes em `docs/mesa_pendencias_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `FontePendencia` | Contrato de uma fila (contar, atrasados, itens, permissão, tela de resolução); cada módulo registra a sua em `config/mesa_pendencias.php` |
| **Minha Mesa** | Nove filas iniciais: cancelamentos (analisar, concluir, devolver), documentos (enviar, assinaturas atrasadas), rematrículas sem turma, transferências em andamento, providências de RH vencidas e grades incompletas |
| Por papel | Só aparecem as filas que o papel ativo pode resolver; a página nem aparece para quem não tem nenhuma. Fila que falha não derruba a Mesa |
| Selo do menu | Atrasados em vermelho (ou o total em amarelo), guardado por 5 minutos por usuário e papel |
| Filas acrescentadas depois | Classificação contábil (73), seis do CRM (84), três de compras, estoque abaixo do mínimo, orçamento estourado, três do financeiro (78), turmas com resultado negativo (77) e projeção do próximo ano (76): lista completa em `docs/mesa_pendencias_roadmap.md` |

---

### 34. 📑 Relatórios Configuráveis e Agendados (Onda 72)
A pessoa monta o relatório (dados, colunas, filtros, ordem, formato), gera na hora ou agenda. Detalhes em `docs/relatorios_configuraveis_agendados_roadmap.md`.

| Recurso | Descrição |
|---|---|
| `FonteDeRelatorio` | Contrato de uma fonte de dados (colunas, filtros, permissão, linhas); o relatório nunca vira SQL livre. Seis fontes iniciais em `config/relatorios.php`: inadimplência, matrículas, contas a pagar, movimento bancário, faltas excessivas e documentos para assinatura |
| **Meus Relatórios** (grupo Relatórios) | Cada pessoa vê os seus; `VerTodos:RelatorioSalvo` vê os de todos. CSV (até 20.000 linhas) ou PDF (até 1.500), colunas com dado pessoal sinalizadas |
| Agendamento | Diário, semanal ou mensal (rodada de 5 em 5 minutos, `relatorios:executar-agendados`); aviso por e-mail e sino **só com o link**; três falhas seguidas desligam o agendamento |
| Permissão em todas as pontas | A da fonte é conferida ao montar, a cada execução, para cada destinatário e ao baixar; perder o acesso aos dados fecha o arquivo |
| Retenção | Arquivo apagado em 30 dias (`relatorios:limpar-arquivos`); o histórico da execução permanece |

---

### 35. 🧮 Exportação para a Contabilidade (Onda 73)
Lançamentos contábeis do período, em partidas dobradas, no plano de contas do escritório. Detalhes em `docs/exportacao_contabil_roadmap.md`.

| Recurso | Descrição |
|---|---|
| De-para | *Código no escritório* em cada conta do plano e *Conta no escritório* em cada banco; **Parâmetros contábeis** (clientes, fornecedores, receita e despesa padrão) |
| **Contabilidade** (grupo Financeiro) | Regime de **caixa** ou de **competência** (fatura e conta a pagar pelo vencimento, o banco baixa), conferência com totais, prévia, pendências e avisos |
| Pendências | Banco sem conta, conta do plano sem código ou padrão não definida **impedem o arquivo** |
| Arquivos | CSV de lançamentos e balancete de conferência; cada arquivo vira um lote com SHA-256, baixado por rota própria (`Exportar:ExportacaoContabil`) |
| Privacidade | Sem nome de aluno nem de responsável; texto do extrato passa pelo `CsvSanitizer`. Fila *Classificação contábil pendente* na Mesa |

---

### 36. 🔏 Declarações e Histórico com Assinatura Digital (Onda 74)
Cópia assinada digitalmente da declaração emitida e do histórico escolar. Detalhes em `docs/assinatura_declaracoes_historico_roadmap.md`.

| Recurso | Descrição |
|---|---|
| Dois caminhos | **Enviar para assinatura** (Assinafy, reaproveita a Onda 69) ou **Anexar PDF assinado** fora (ICP-Brasil, gov.br), com a permissão `AnexarAssinado:DocumentoAssinatura` |
| Leitura do PDF | `AssinaturaPdfInspector` conta as assinaturas, vê o formato e a marca da ICP-Brasil e registra o nível; **não valida** a cadeia nem o certificado (Verificador do ITI) |
| Proteção | Documento com cópia assinada não é emitido de novo, nem editado, excluído ou sincronizado; o anexo exige confirmar que é o documento do aluno |
| Onde aparece | Coluna e seção do nível em *Documentos para Assinatura*, Portal da família e página pública de verificação |

---

### 37. 📞 Mesa do Consultor e Próxima Ação Obrigatória (Onda 84)
O atendimento do CRM sempre diz o que fazer depois, e a Minha Mesa mostra ao consultor para quem ligar agora. Detalhes em `docs/mesa_consultor_proxima_acao_roadmap.md`.

| Recurso | Descrição |
|---|---|
| Próxima ação | Coluna `interessado.proxima_acao` (o quê) ao lado de `data_proximo_contato` (quando). **Registrar atendimento** pede os dois, exceto quando o resultado encerra o ciclo (*Efetuou matrícula* ou *Sem interesse*); botão **Próxima ação** na lista |
| Seis filas na Mesa | Contatos do dia, visitas de hoje e sem resultado, primeira resposta fora do prazo, leads sem próxima ação, leads quentes sem contato e leads sem consultor; visibilidade da lista de Interessados (a própria carteira e os sem dono) |
| Parâmetros | `config/crm.php` → `mesa` (carência, prazos de atraso e janela do SLA) |

---

### 38. 🛒 Compras e Cotações (Onda 38)
Do pedido do setor ao recebimento com a nota fiscal e a conta a pagar. Grupo **Suprimentos** do menu. Detalhes em `docs/compras_cotacoes_roadmap.md`.

| Recurso | Descrição |
|---|---|
| Fluxo | Requisição (rascunho) → cotação → pedido → aprovação → recebimento (total ou parcial) → conta a pagar; cancelamento e recusa com motivo |
| Cotação | Uma proposta por fornecedor com preço de **todos** os itens; **mapa comparativo** com o menor preço e o menor total, validade e fornecedor sinalizado por recebimento divergente; escolher a que não é a mais barata, ou com menos cotações que o mínimo da faixa, **exige justificativa** |
| Alçada | Três níveis pelo valor (R$ 1.000 e R$ 10.000 por padrão, configuráveis); a aprovação mostra o **saldo do orçamento** da conta (Onda 43) e pede justificativa se o pedido passar dele |
| Controle interno | Quem solicita não aprova e quem aprova não recebe (vale também para o super administrador) |
| Recebimento | Item de estoque dá entrada, bem durável vira cadastro no patrimônio (um por unidade) e nasce a conta a pagar com o prazo da cotação; nota fiscal em disco privado, entregue por rota própria |
| Visibilidade | Cada pessoa abre as próprias requisições e as que precisam dela (cotar, aprovar na sua alçada, receber); `VerTodas:RequisicaoCompra` abre as demais |
| Minha Mesa | *Compras para cotar*, *Pedidos para aprovar* e *Compras para receber* |

---

### 39. 📦 Estoque de Consumíveis (Onda 16, fase 2)
Material de limpeza, papelaria e descartáveis, com saldo, mínimo e histórico. Grupo **Suprimentos**. Detalhes em `docs/patrimonio_estoque_roadmap.md`.

| Recurso | Descrição |
|---|---|
| Movimentos | Entrada, saída (para um centro de custo) e ajuste de inventário; saldo negativo não existe; **custo médio móvel**; cada movimento grava o saldo depois dele |
| Trilha | Aba *Movimentações* em cada item; item com movimento não se exclui |
| Compras | O recebimento de um pedido com destino "estoque" dá a entrada sozinho |
| Mesa | *Estoque abaixo do mínimo* (item zerado é atrasado); permissão `Movimentar:ItemEstoque` à parte |

---

### 40. ⚖️ Orçamento Anual × Realizado (Onda 43)
O previsto por conta do plano e mês, contra o que aconteceu. Grupo **Financeiro**. Detalhes em `docs/orcamento_anual_realizado_roadmap.md`.

| Recurso | Descrição |
|---|---|
| **Orçamento Anual** | Planilha conta × 12 meses, importação CSV e cópia de outro orçamento com reajuste; rascunho → aprovado; o aprovado **não muda** (a revisão abre uma nova versão) |
| **Orçado × Realizado** | Previsto acumulado até o mês escolhido contra o realizado, com variação e semáforo; o realizado é o **mesmo do DRE**; avisa as saídas sem conta do plano |
| Integrações | Saldo para a aprovação de compras (Onda 38) e a fila *Orçamento estourado* na Mesa |

---

### 41. 🔁 Contas Recorrentes e Fluxo de Caixa Projetado (Onda 78)
Despesas que se repetem geram a conta a pagar sozinhas e o caixa dos próximos 90 dias é projetado. Grupo **Financeiro**. Detalhes em `docs/contas_recorrentes_fluxo_projetado_roadmap.md`.

| Recurso | Descrição |
|---|---|
| **Contas Recorrentes** | Periodicidade, dia, valor, vigência e antecedência; rotina diária `financeiro:gerar-contas-recorrentes` que **nunca duplica** a conta do mês e recupera 31 dias; valor estimado nasce "a revisar" e a baixa só sai depois; **Registrar reajuste** atualiza as contas abertas que seguem no valor antigo |
| **Fluxo Projetado** | 30 a 180 dias, dia a dia: saldo bancário + faturas abertas pelo cenário (média histórica, receber tudo, não receber nada) − contas a pagar − recorrentes ainda sem conta; mostra a primeira data negativa e a situação da conciliação |
| Mesa e relatórios | *Contas recorrentes a revisar*, *Reajustes de contrato* e *Caixa projetado negativo*; fonte nos relatórios configuráveis |

---

### 42. 📊 Resultado Realizado por Turma (Onda 77)
Quanto cada turma, série, curso e unidade rendeu e custou de fato no mês, ao lado do planejado. Grupo **Financeiro**. Detalhes em `docs/rentabilidade_por_turma_roadmap.md`.

| Recurso | Descrição |
|---|---|
| Receita | Faturas do mês líquidas de bolsa, com recebido e em atraso |
| Custos | Docente (apuração de hora-aula dividida pelas aulas do cronograma), direto (centro de custo vinculado à turma ou ao curso) e indireto **rateado por aluno, igual por turma ou por hora-aula** — o critério aparece ao lado do número |
| Comparações | Planejado (Controladoria de Turmas), mês anterior e mesmo mês do ano anterior; margem, custo e resultado por aluno |
| Centro de custo | Passa a poder ser vinculado a uma turma ou a um curso, ou marcado **fora do resultado das turmas** |
| Mesa e relatórios | *Turmas com resultado negativo*; fonte nos relatórios; `View:RentabilidadeRealizada` (dado sensível) |

---

### 43. 🧭 Painel da Direção (Onda 45)
A saúde da escola em uma tela, com meta, variação e semáforo. Grupo **Relatórios**. Detalhes em `docs/painel_direcao_roadmap.md`.

| Recurso | Descrição |
|---|---|
| Indicadores | 13 em quatro seções (alunos e vagas, financeiro, captação, pedagógico), cada um com **uma definição só** (`IndicadorDoPainel`, registrada em `config/painel_direcao.php`), reaproveitando o código dos relatórios de origem |
| Permissão por indicador | Cada pessoa vê só o que a permissão da tela de origem permite |
| Cartão | Valor, variação contra 30 dias atrás e contra o ano anterior, série de 90 dias, meta do ano e semáforo; filtro por unidade; lista *Atenção hoje* vinda da Minha Mesa |
| Metas | **Metas dos Indicadores** por indicador, ano e unidade, com tolerância |
| Fotografias | Rotina diária `painel:fotografar` guarda o valor de cada indicador; indicador que falha não derruba o painel |

---

### 44. 🧮 Simulador do Próximo Ano Letivo (Onda 76)
Quantos alunos, quantas turmas e se cada turma se paga, com os dados de hoje. É projeção, não compromisso. Grupo **Relatórios**. Detalhes em `docs/simulador_proximo_ano_letivo_roadmap.md`.

| Recurso | Descrição |
|---|---|
| Projeção | Por série de destino: rematrículas confirmadas, em andamento (pela taxa escolhida, sem quem tem risco alto de evasão), lista de espera até as vagas que sobram; quem ainda não escolheu a série entra à parte, só no total |
| "E se" | Taxas, corte de risco, reajuste, número de turmas (zero fecha a série), capacidade e abrir turma em outra série; vagas livres, excesso e **ponto de equilíbrio** por turma; sugere as taxas da campanha anterior |
| Cenários | Salvos com a fotografia do resultado e comparados lado a lado |
| Mesa e relatórios | *Projeção do próximo ano* (atrasada quando a campanha termina em até 15 dias); fonte nos relatórios |

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

   Usuários com papel `aluno` ou `responsavel` também podem entrar por um painel dedicado e simplificado (mesmo login/senha), com notas, frequência, horários, calendário, boletim, financeiro e documentos/contrato: `http://127.0.0.1:8000/portal` (detalhes em `docs/portal_familia_notas_frequencia_horarios.md`)

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
- Proteção contra mass assignment via `$fillable` explícito em cada Model.
- Senhas armazenadas com **bcrypt** (`password` cast `hashed`).
- Validação de campos críticos (CPF único, e-mail único) nos Schemas dos formulários.

## 🗺️ Roadmap — Próximas Melhorias

Propostas documentadas, mas **ainda não implementadas** (sem código, migration ou teste).
Cada uma tem um doc próprio com contexto, escopo e dependências, para quando for
autorizada:

| Tema | Doc |
|---|---|
| Provas online com correção automática | `docs/provas_online_roadmap.md` |
| Controle de saída de alunos — manual e por catraca | `docs/controle_saida_alunos_roadmap.md` |
| Transporte escolar (rotas/veículos/motoristas) | `docs/transporte_escolar_roadmap.md` |
| Excursões e passeios escolares | `docs/excursoes_passeios_roadmap.md` |
| Cantina escolar com saldo pré-pago | `docs/cantina_escolar_roadmap.md` |
| Repositório de conteúdo pedagógico digital | `docs/conteudo_pedagogico_digital_roadmap.md` |
| Atendimento Educacional Especializado (AEE) / Plano Individualizado | `docs/atendimento_educacional_especializado_roadmap.md` |
| Ondas 27 a 48 (16 propostas, 5 implementadas e 1 parcial), com ordem sugerida | `docs/ondas_27_a_48_indice.md` |
| NFS-e das mensalidades (parte ainda não feita da Onda 42) | `docs/nfse_informe_mensalidades_roadmap.md` |
| Ondas 49 a 60 (10 propostas e 2 implementadas), com as ampliações propostas em ondas existentes | `docs/ondas_49_a_60_indice.md` |
| Ondas 61 a 67 (2 propostas, 4 implementadas e 1 mesclada à Onda 35), com as ampliações propostas | `docs/ondas_61_a_67_indice.md` |
| Conferência de cláusulas dos modelos de contrato (Onda 66) | `docs/conferencia_clausulas_contrato_roadmap.md` |
| Loja escolar opcional: uniformes e livros (Onda 67) | `docs/loja_escolar_roadmap.md` |
| Ondas 68 a 71 (2 propostas e 2 implementadas), com as fusões sugeridas e as ampliações propostas | `docs/ondas_68_a_71_indice.md` |
| Gateway de pagamento real: Asaas e Efí (Onda 68) | `docs/gateway_pagamento_real_roadmap.md` |
| Central de avisos à família (Onda 70) | `docs/central_avisos_familia_roadmap.md` |
| Ondas 72 a 79 (2 propostas e 6 implementadas), com as fusões e as ampliações propostas | `docs/ondas_72_a_79_indice.md` |
| Conjuntos **Suprimentos** e **Resultado da escola** (ondas 16, 38, 43, 45, 76, 77, 78 e 84, implementadas em 2026-10-27) | `docs/conjuntos_suprimentos_resultado_indice.md` |

(A avaliação de desempenho docente e a reserva de espaços, que constavam aqui, já foram implementadas e
estão nas seções 19 e 20 dos módulos. O reajuste de mensalidades e o informe anual estão na seção 25; o
Censo Escolar e a lista de material, nas seções 26 e 27; o cancelamento de matrícula, os encargos por atraso,
a admissão e o desligamento e a grade horária assistida, nas seções 28 a 31; a assinatura eletrônica de documentos
e a mesa de pendências, nas seções 32 e 33; as ondas 72 a 74, nas seções 34 a 36; e as ondas 84, 38, 16 (estoque), 43, 78, 77, 45 e 76, nas
seções 37 a 44. Os documentos dessas duas ondas, 69 e 71, estão em
`docs/assinatura_eletronica_documentos_roadmap.md` e `docs/mesa_pendencias_roadmap.md`.)
