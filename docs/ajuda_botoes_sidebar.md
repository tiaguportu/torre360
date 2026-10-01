# Cobertura do botão de Ajuda na barra lateral

Legenda: ✅ tem botão de Ajuda · ⏳ pendente. Plano em fases; cada fase = 1 commit.
Design/uso: `docs/ajuda_modal_design.md`.

| Fase | Grupo | Telas pendentes | Status |
|---|---|---|---|
| 0 | Base | Modal redesenhado, `HelpContent`, `HasAjudaAction`, ListCursos migrado | ✅ |
| 1 | CRM / Comercial | Campanhas de Marketing, Comunicação em Massa, Leads da Landing, Modelos de WhatsApp | ✅ |
| 2 | Secretaria + Acadêmico | Coordenadores, Frequências Escolares, Planos de Aula, Salas, Fechamento do Ciclo Letivo | ✅ |
| 3 | Avaliações + Currículo | Notas, Habilidades | ✅ |
| 4 | Preceptoria + Calendário | Ciclos, Relatórios, Templates de Relatório, Dias Não Letivos | ✅ |
| 5 | Financeiro + Operacional | Fornecedores, Transações Bancárias, Ordens de Serviço | ✅ |
| 6 | Configurações | Bancos, Cidades, Estados, Códigos BACEN, Centros de Custo, Plano de Contas, Turnos, Tipos de Vínculo, Tributações dos Cursos, Etapas Avaliativas, Categorias de Avaliação, Categorias de OS, Áreas de Conhecimento, Campos de Experiência, Configurações, Endereços (as pastas CategoriaAprendizagemResource e CategoriaNecessidadeEspecialResource estão vazias, sem tela na sidebar) | ✅ |
| 7 | Sistema + Dashboard | Logs de Atividade, E-mails Enviados, Início (Dashboard do painel, agora uma subclasse em app/Filament/Pages/Dashboard.php) | ✅ |
| 8 | Portal | Início, Notas, Frequência, Horários, Calendário, Boletins, Documentos e Contratos, Solicitar Documentos Oficiais, Eventos e Atividades, Rematrícula Online, Financeiro, Ocorrências, Agendar Preceptoria, Central de Atendimento (14 páginas, textos em linguagem simples) | ✅ |
| 9 | Fechamento | Teste de varredura da navegação (tests/Feature/AjudaCoberturaNavegacaoTest.php), correção da tabela de grupos do MANUAL_USUARIO.md (seção 2) e seção 39. **Exceção:** Filament Shield → Roles (resource do pacote; exigiria publicar o resource com `shield:publish`) | ✅ |
