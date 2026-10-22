# Ondas 27 a 48 — Índice

Levantamento de funcionalidades para as próximas entregas, com o que já foi feito e o que
continua como proposta. Cada onda tem um documento próprio com contexto, o que já existe no
sistema, escopo e riscos.

- **Implementadas (5):** 30, 39, 40, 41 e 44.
- **Parcialmente implementada (1):** 42 (informe anual entregue; NFS-e proposta).
- **Propostas (16):** as demais. Não há código, migration nem teste delas.
- **Melhorias em ondas anteriores (2026-10-21):** alerta de férias na Onda 10 e autoavaliação com
  plano de desenvolvimento na Onda 19 (veja o fim deste índice).
- **Continuação:** as ondas 49 a 60 estão em [ondas_49_a_60_indice.md](ondas_49_a_60_indice.md).

## Visão geral

| Onda | Tema | Área | Status | Documento |
|---|---|---|---|---|
| 27 | Conselho de Classe e Atas | Pedagógico | Proposta | [conselho_classe_atas_roadmap.md](conselho_classe_atas_roadmap.md) |
| 28 | Reforço e Recuperação Paralela | Pedagógico | Proposta | [reforco_recuperacao_paralela_roadmap.md](reforco_recuperacao_paralela_roadmap.md) |
| 29 | Plano de Ensino Anual e Cobertura Curricular | Pedagógico | Proposta | [plano_ensino_anual_cobertura_roadmap.md](plano_ensino_anual_cobertura_roadmap.md) |
| 30 | Portfólio do Aluno e Parecer Descritivo | Pedagógico | **Implementada** | [portfolio_parecer_descritivo_roadmap.md](portfolio_parecer_descritivo_roadmap.md) |
| 31 | Atividades do Contraturno | Pedagógico / Financeiro | Proposta | [atividades_contraturno_roadmap.md](atividades_contraturno_roadmap.md) |
| 32 | Reuniões com Professores | RH / Pedagógico | Proposta | [reunioes_professores_roadmap.md](reunioes_professores_roadmap.md) |
| 33 | Comunicados com Ciência e Enquetes | Comunicação | Proposta | [comunicados_ciencia_enquetes_roadmap.md](comunicados_ciencia_enquetes_roadmap.md) |
| 34 | Portal do Titular LGPD | Conformidade | Proposta | [portal_titular_lgpd_roadmap.md](portal_titular_lgpd_roadmap.md) |
| 35 | Ouvidoria e Canal de Denúncias | Conformidade | Proposta | [ouvidoria_canal_denuncias_roadmap.md](ouvidoria_canal_denuncias_roadmap.md) |
| 36 | Egressos | Relacionamento | Proposta | [egressos_roadmap.md](egressos_roadmap.md) |
| 37 | Portaria Digital | Operação | Proposta | [portaria_digital_roadmap.md](portaria_digital_roadmap.md) |
| 38 | Compras e Cotações | Financeiro / Operação | Proposta | [compras_cotacoes_roadmap.md](compras_cotacoes_roadmap.md) |
| 39 | Manutenção Preventiva | Operação | **Implementada** | [manutencao_preventiva_roadmap.md](manutencao_preventiva_roadmap.md) |
| 40 | Cardápio e Alergias | Operação | **Implementada** | [cardapio_alergias_roadmap.md](cardapio_alergias_roadmap.md) |
| 41 | Ponto e Banco de Horas | RH | **Implementada** (controle interno; REP-A/REP-P bloqueados por requisitos legais) | [ponto_banco_horas_roadmap.md](ponto_banco_horas_roadmap.md) |
| 42 | NFS-e e Informe Anual de Mensalidades | Financeiro | **Parcial** (informe anual implementado junto com a 44; NFS-e proposta) | [nfse_informe_mensalidades_roadmap.md](nfse_informe_mensalidades_roadmap.md) · [entrega unificada](reajuste_e_informe_anual_roadmap.md) |
| 43 | Orçamento Anual × Realizado | Financeiro | Proposta | [orcamento_anual_realizado_roadmap.md](orcamento_anual_realizado_roadmap.md) |
| 44 | Reajuste de Mensalidades | Financeiro | **Implementada** (com o informe anual da 42) | [reajuste_mensalidades_roadmap.md](reajuste_mensalidades_roadmap.md) · [entrega unificada](reajuste_e_informe_anual_roadmap.md) |
| 45 | Painel da Direção | Gestão | Proposta | [painel_direcao_roadmap.md](painel_direcao_roadmap.md) |
| 46 | Segurança Reforçada (MFA, retenção de logs, criptografia de saúde) | Segurança | Proposta | [seguranca_reforcada_roadmap.md](seguranca_reforcada_roadmap.md) |
| 47 | Digest Diário por Papel | Comunicação | Proposta | [digest_diario_papel_roadmap.md](digest_diario_papel_roadmap.md) |
| 48 | Integrações: SSO, Google Classroom e Webhooks | Integração | Proposta | [integracoes_sso_classroom_webhooks_roadmap.md](integracoes_sso_classroom_webhooks_roadmap.md) |

Outras propostas anteriores a este levantamento continuam documentadas no `README.md`
(seção *Roadmap*), por exemplo o controle de saída de alunos (Onda 9), que a Portaria Digital
(Onda 37) deve reaproveitar.

## Dependências entre ondas

- **37 depende de 9** (controle de saída de alunos): é o mesmo fluxo visto da portaria.
- **38 conversa com 39 e 43**: a manutenção gera necessidade de compra e o orçamento limita a aprovação.
- **43 e 44** usam o plano de contas e a planilha da Lei das Mensalidades já existentes.
- **47 depende das fontes de pendência** de várias ondas (21, 30, 39, 41) e se beneficia da 33.
- **45 depende de dados confiáveis** das demais (financeiro, captação, frequência).
- **46** libera a **48** (SSO com segundo fator) e protege dados que a **30**, **40** e **41** já guardam.
- **27** usa o parecer da **30**; **28** usa os conceitos por habilidade já existentes.
- **31** reaproveita a reserva de espaços (Onda 21) e o faturamento recorrente.

## Ordem sugerida das propostas

A ordem considera valor para a escola, risco e dependências, nesta sequência:

1. **Conformidade primeiro:** 34 (LGPD do titular) e 46 (MFA e retenção). Reduzem risco e já têm
   pendências abertas na revisão de segurança.
2. **Receita e controle financeiro:** 43 e a NFS-e da 42 (o reajuste da 44 e o informe anual já
   foram entregues).
3. **Pedagógico:** 27, 28 e 29, que aproveitam o que a Onda 30 e as habilidades já entregam.
4. **Comunicação:** 33 e 47.
5. **Operação:** 37 (junto com a Onda 9), 38 e 31.
6. **Gestão e integração:** 45 (quando as fontes estiverem confiáveis), 35, 36, 32 e 48.

A Onda 41 pede uma decisão própria: se a escola tem mais de 20 trabalhadores e precisa de ponto
**oficial**, a via mais curta é um REP certificado ou um REP-P de fornecedor que já tenha registro
no INPI e Atestado Técnico (veja o documento da onda).

## Melhorias em ondas anteriores

| Onda | Melhoria | Documento |
|---|---|---|
| 10 | Painel e aviso semanal de férias vencidas, com prazo acabando ou sem registro (CLT, arts. 134 e 137) | [rh_funcionarios_roadmap.md](rh_funcionarios_roadmap.md) |
| 19 | Autoavaliação do professor, comparativo com as observações e plano de desenvolvimento individual | [avaliacao_desempenho_docente_roadmap.md](avaliacao_desempenho_docente_roadmap.md) |
