# Ondas 49 a 60 — Índice

Continuação do [índice das ondas 27 a 48](ondas_27_a_48_indice.md). Cada onda tem um documento próprio com
contexto, o que já existe no sistema (conferido no código), escopo, base legal, dependências e riscos.

- **Implementadas (2):** 58 e 59.
- **Propostas (10):** 49, 50, 51, 52, 53, 54, 55, 56, 57 e 60. Não há código, migration nem teste delas.

## Visão geral

| Onda | Tema | Área | Status | Documento |
|---|---|---|---|---|
| 49 | Carga Letiva Mínima e Reposição de Aulas | Pedagógico | Proposta | [carga_letiva_minima_roadmap.md](carga_letiva_minima_roadmap.md) |
| 50 | Apuração de Hora-Aula Docente | RH / Financeiro | Proposta | [hora_aula_docente_roadmap.md](hora_aula_docente_roadmap.md) |
| 51 | Tarefas e Lições de Casa | Pedagógico | Proposta (evolui o campo `dever_casa` já existente) | [tarefas_licoes_casa_roadmap.md](tarefas_licoes_casa_roadmap.md) |
| 52 | Calendário de Avaliações e Limite de Provas por Dia | Pedagógico | Proposta | [calendario_avaliacoes_roadmap.md](calendario_avaliacoes_roadmap.md) |
| 53 | Risco de Inadimplência | Financeiro | Proposta | [risco_inadimplencia_roadmap.md](risco_inadimplencia_roadmap.md) |
| 54 | Saúde do Sistema | Operação / Segurança | Proposta | [saude_do_sistema_roadmap.md](saude_do_sistema_roadmap.md) |
| 55 | Medicação com Registro de Doses | Saúde | Proposta | [medicacao_registro_doses_roadmap.md](medicacao_registro_doses_roadmap.md) |
| 56 | Saúde e Segurança do Trabalhador | RH | Proposta | [saude_seguranca_trabalhador_roadmap.md](saude_seguranca_trabalhador_roadmap.md) |
| 57 | Faltas Excessivas e Comunicação ao Conselho Tutelar | Conformidade | Proposta | [faltas_conselho_tutelar_roadmap.md](faltas_conselho_tutelar_roadmap.md) |
| 58 | Censo Escolar: Situação do Aluno (2ª etapa) | Secretaria | **Implementada** | [censo_escolar_situacao_aluno_roadmap.md](censo_escolar_situacao_aluno_roadmap.md) |
| 59 | Lista de Material Escolar | Secretaria | **Implementada** | [lista_material_escolar_roadmap.md](lista_material_escolar_roadmap.md) |
| 60 | Documentos da Escola com Validade | Operação | Proposta | [documentos_escola_validade_roadmap.md](documentos_escola_validade_roadmap.md) |

## Ordem sugerida das propostas

1. **Obrigação legal e pequena:** 57 (aviso ao Conselho Tutelar). Usa dados que já existem.
2. **Vencimentos que fecham escola ou geram multa:** 60 (documentos da escola) e 56 (exames e Lei Lucas).
   Ambas copiam o painel e o aviso semanal das férias.
3. **Pequenas e independentes:** 52 (calendário de avaliações) e 54 (saúde do sistema).
4. **Base para outras:** 49 (carga letiva), que dá o total de aulas do ano para a 57 e a 50.
5. **Médias:** 50 (hora-aula), 53 (risco de inadimplência), 55 (doses) e 51 (tarefas).

## Ampliações propostas em ondas já existentes

Não são ondas novas: são melhorias de funcionalidades já implementadas ou documentadas.

| Onda | Ampliação | Base legal ou observação |
|---|---|---|
| 42 (financeiro) | **Declaração anual de quitação de débitos**, emitida para todos os contratos até a fatura de maio do ano seguinte. Hoje o modelo `DeclaracaoQuitacao` só sai sob demanda e só se tudo estiver em dia. | Lei nº 12.007/2009: cobre janeiro a dezembro pelo **vencimento** e substitui os recibos mensais. É outro documento, diferente do informe anual (que é por caixa). |
| 42 (NFS-e) | Integrar com o **padrão nacional da NFS-e**. | LC nº 214/2025, art. 62 (municípios no padrão nacional desde 2026); Resolução CGSN nº 189/2026 para ME e EPP do Simples (fonte secundária; confirmar com a contabilidade). |
| 44 (reajuste) | Publicar com data a **proposta de contrato, o valor e as vagas por turma**, 45 dias antes do fim da matrícula; comunicado com ciência (Onda 33); novo contrato com assinatura (Assinafy). | Lei nº 9.870/1999, art. 2º. A data publicada serve de prova do prazo. |
| 10 (RH) | Aviso de férias por escrito com 30 dias e recibo; pagamento até 2 dias antes; validação do fracionamento e da proibição de começar nos 2 dias antes de feriado ou folga; férias coletivas; abono automático no ponto. | CLT, arts. 134 (§§ 1º e 3º), 135, 139 e 145. |
| 19 (avaliação docente) | Devolutiva assinada e em PDF; evolução do professor entre ciclos; certificados de formação ligados às ações do plano. | |
| 30 (portfólio) | Checar o consentimento de uso de imagem (Onda 25) antes de "compartilhar com a família"; parecer por disciplina nos anos finais; PDF do portfólio. | LGPD e Onda 25. |
| 41 (ponto) | Fechamento mensal do espelho com aceite do trabalhador (Assinafy); turno noturno e feriados municipais; horários de entrada e saída (tolerância de 5 minutos por marcação); lembrete de marcação ímpar; abono automático por férias e afastamentos; importação de AFD de relógio físico. | Portaria MTP nº 671/2021. |
| 40 (cardápio) | Aviso por push ao publicar cardápio com o alérgeno do filho; restrições religiosas; ficha técnica com custo por refeição. | |
| 39 (manutenção) | Custo e fornecedor por execução; anexo de laudo ou ART; aviso ao responsável. Liga a Onda 38 (compras). | |
| 21 (reserva de espaços) | Recursos (projetor, notebook); reserva recorrente; exportação para o calendário do celular. | |
| 13 (evasão) | Transformar o score em ação: tarefa, contato registrado e medida de efetividade, ligando às Ondas 27 e 28. | |
| 16 (patrimônio) | Estoque de consumíveis (a "fase 2" documentada) e depreciação, que alimenta o DRE. | |
| 17 e 40 | O caixa da cantina avisaria se o produto tem o alérgeno da criança (reaproveita o cruzamento do cardápio). | |
| 9 e 37 | Controle de saída e portaria digital são o mesmo fluxo: fazer juntos, com o "quem está na escola agora" para simulado de evacuação. | |
| 8 (provas online) | Começar pelo banco de questões, que serve também ao OMR e ao plano de aula. | |
| 22 (AEE) | Unificar com o PEI do Copiloto em vez de criar um segundo cadastro. | |
| 58 (censo) | Tabela de regra de admissão e etapas equivalentes; turmas com mais de uma etapa no registro 91. | Veja o documento da Onda 58. |
| 59 (material) | Aviso às famílias quando a lista é publicada (Onda 33); lista por turma ou turno. | |
