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

(A avaliação de desempenho docente e a reserva de espaços, que constavam aqui, já foram implementadas e
estão nas seções 19 e 20 dos módulos. O reajuste de mensalidades e o informe anual estão na seção 25; o
Censo Escolar e a lista de material, nas seções 26 e 27; o cancelamento de matrícula, os encargos por atraso,
a admissão e o desligamento e a grade horária assistida, nas seções 28 a 31.)
