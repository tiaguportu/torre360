# Roteiro de qualificação do lead (SDR com IA): Lote 1

Primeiro passo para um SDR comercial (responder rápido, qualificar, marcar a visita e passar ao consultor com contexto) com o
Gemini. Antes dele o CRM era um **copiloto do consultor**: a IA gerava texto ou JSON quando alguém clicava, e o que a família
respondia ficava em texto livre (`observacoes`), sem como filtrar, pontuar ou perguntar o que falta. Este lote entrega o
**alicerce**: o roteiro de qualificação como dado estruturado. As etapas seguintes (Copiloto "Conhecer a família", extração na
Síntese de Conversa, distribuição, WhatsApp oficial e agente) estão na seção 9.

| Peça | Onde |
|---|---|
| Lista fechada de campos, ordem da conversa e opções | `App\Support\Qualificacao\Roteiro` e `CampoQualificacao` |
| Regras: estados, lacunas, próximas perguntas, gravação, gate, alertas | `App\Services\Qualificacao\QualificacaoService` |
| Respostas da família (um registro por lead) | `App\Models\InteressadoQualificacao`, tabela `interessado_qualificacao` |
| Respostas por aluno (situação atual, escola atual) | `InteressadoDependente` (colunas novas) |
| Aba da ficha e painel | `InteressadoForm` (aba **Roteiro de qualificação**), view `roteiro-qualificacao.blade.php` |
| Fila da Minha Mesa | `App\Services\Pendencias\Fontes\QualificacaoIncompleta` |
| Recalcular leads antigos | `php artisan crm:reavaliar-qualificacao` |
| Configuração | `config/crm.php` (`qualificacao.*` e `mesa.qualificacao_incompleta_dias`) |

## 1. O roteiro

Doze campos, na ordem em que a conversa deve percorrê-los: **encaixe** (série, nascimento, turno, bairro) → **descoberta**
(situação atual, escola atual, motivo da busca, escolas em comparação) → **decisão** (quem decide, se vai à visita, prazo) →
**dinheiro** por último. Os campos com ★ formam o *gate*: bastam para marcar a visita.

| Campo | Nível | Dimensão | Gate | Onde fica |
|---|---|---|---|---|
| Série pretendida | aluno | encaixe | ★ | `interessado_dependente.serie_id` (já existia) |
| Data de nascimento | aluno | encaixe | ★ | `interessado_dependente.data_nascimento` (já existia) |
| Turno | aluno | encaixe | ★ | `interessado_dependente.turno_preferencia` (já existia) |
| Bairro | família | encaixe | ★ | `interessado_qualificacao.bairro` |
| Situação atual do aluno | aluno | necessidade | ★ | `interessado_dependente.situacao_atual` |
| Escola atual | aluno | necessidade | | `interessado_dependente.escola_atual` (só se estuda em outra escola) |
| Motivo da busca | família | necessidade | ★ | `interessado_qualificacao.motivo_busca` |
| Escolas em comparação | família | necessidade | | `interessado_qualificacao.escolas_em_comparacao` (lista de nomes) |
| Quem decide | família | autoridade | ★ | `interessado_qualificacao.decisor_tipo` |
| Quem decide vai à visita | família | autoridade | ★ | `interessado_qualificacao.decisor_vai_visita` |
| Prazo de decisão | família | urgência | ★ | `interessado_qualificacao.prazo_decisao` |
| Faixa de mensalidade | família | dinheiro | | `interessado_qualificacao.faixa_mensalidade` |

Por que nesta ordem: perguntar preço antes de a família ver valor a afasta, então a mensalidade vem por último, **nunca é
exigida** e **nunca entra sozinha** na lista de próximas perguntas (`perguntarAutomaticamente = false`).

**A lista é fechada de propósito.** Renda exata, saúde e necessidades especiais da criança **não** são campos de qualificação
(dado sensível, LGPD art. 11); quem preenche o roteiro (consultor ou, nas próximas etapas, a IA) só grava o que está em
`Roteiro::campos()`. `QualificacaoService::registrar()` lança `InvalidArgumentException` para qualquer outra chave.

### Opções fechadas

| Campo | Valores |
|---|---|
| `decisor_tipo` | sozinho, com_conjuge, outro_familiar, empresa_convenio |
| `decisor_vai_visita` | sim, nao, a_definir |
| `motivo_busca` | primeira_escola, insatisfacao_pedagogica, insatisfacao_atendimento, preco_escola_atual, mudanca, horario_jornada, seguranca_estrutura, **serie_seguinte_indisponivel** (a escola atual não oferece a série seguinte), indicacao, outro |
| `prazo_decisao` | esta_semana, este_mes, antes_do_inicio_das_aulas, sem_prazo |
| `situacao_atual` (aluno) | primeira_escola, em_outra_escola, em_casa_ou_cuidador |
| `faixa_mensalidade` | configurável em `crm.qualificacao.faixas_mensalidade` (lista vazia esconde o campo) |

Os valores moram em constantes dos modelos (`InteressadoQualificacao::DECISOR_TIPOS` etc., no mesmo estilo de
`Interessado::MOTIVOS_PERDA` e `InteressadoDependente::TURNOS_PREFERENCIA`) e não em enums: o estado de um `Select` com enum
chega ao Filament como objeto, e a lista é fechada e pequena.

## 2. Estado de cada campo

Cada campo está em um de quatro estados (mais "não se aplica"): **não perguntado**, **perguntado** (a escola já fez a pergunta
e espera a resposta), **respondido** e **recusou** (a família preferiu não informar). Regras:

- **Respondido** é "o campo tem valor". A procedência (`fonte`: formulário, consultor, IA confirmada, importação), o trecho que
  justifica, a data e quem registrou ficam em `interessado_qualificacao.campos` (JSON por campo) e, para os campos de aluno,
  em `interessado_dependente.qualificacao_meta`.
- **Campo de aluno** só está respondido quando **todos** os alunos do lead têm o valor. Com vários alunos e nenhum valor, vale
  a situação dos que faltam.
- **Escola atual** só se aplica quando algum aluno estuda em outra escola; senão aparece como "não se aplica" e fica fora do
  gate e das perguntas.
- **Editar pela ficha** grava a procedência "consultor" sozinho (gancho `saving` do modelo). Limpar o campo o devolve a "não
  perguntado".
- **Recusou** encerra a pergunta: não volta às próximas perguntas e **conta como atendido no gate** (a escola perguntou e a
  família preferiu não dizer; não faz sentido travar o lead para sempre).

## 3. Resultado do roteiro

`QualificacaoService::avaliar()` devolve, sem gravar nada, o resultado: **não iniciada** (nenhum campo respondido nem
perguntado), **em qualificação** ou **qualificado para a visita** (todos os campos do gate atendidos). `reavaliar()` guarda o
resultado em `interessado_qualificacao.resultado` e a data em `qualificado_em`.

Quando é recalculado: ao salvar a ficha (`EditInteressado::afterSave`), a cada gravação de `registrar`/`marcarPerguntado`/
`marcarRecusado` e **a cada alteração ou exclusão de um aluno** (gancho em `InteressadoDependente`, que cobre o formulário
público, a importação por IA e a ficha). Esse gancho é um cálculo derivado e **nunca impede o cadastro do aluno**: qualquer
falha é só registrada em log. Lead que ainda não respondeu nada **não ganha linha** na tabela de qualificação.

### Perguntas e gravação

- `proximasPerguntas($lead, $max)`: as lacunas na ordem do roteiro, no máximo `crm.qualificacao.max_perguntas_por_mensagem`
  (2); quem nunca foi perguntado vem antes de quem já foi perguntado e segue sem resposta; a mensalidade nunca entra.
- `registrar(...)`: grava a resposta com procedência e evidência. **Nunca sobrescreve** uma resposta existente: devolve
  `conflito`, a menos que `sobrescrever: true` (confirmação explícita de quem revisa). Devolve `gravado`, `conflito`,
  `invalido` (valor fora da lista, data impossível, série inexistente) ou `sem_aluno` (campo de aluno com mais de um aluno e
  nenhum indicado).
- `marcarPerguntado` e `marcarRecusado`: não mexem em quem já respondeu ou recusou.

### Avisos objetivos (`alertas`)

Mostrados na aba e **nunca desqualificam o lead sozinhos** (isso é decisão de quem atende, pelo fluxo de *Perdido* com os
motivos "Vagas Esgotadas" ou "Distância") e **nunca olham a capacidade de pagamento**:

- série sem vagas, pelo termômetro de vagas;
- idade abaixo de `Serie.idade_minima` na **data de corte de 31/03** do próximo ingresso (até 31/03 vale o ano em curso; depois,
  o ano seguinte). Premissa: `idade_minima` é em anos completos nessa data. O campo só era editável na tela de séries e nenhuma
  regra o usava até aqui;
- mais de 10 km da escola.

### Sugestão de etapa

`crm.qualificacao.etapa_qualificado` (`CRM_ETAPA_QUALIFICADO`) é o **nome** de uma etapa já cadastrada em *Status de
Interessado*. Quando o lead fica qualificado e a etapa existe, não é final e está à frente da atual, a ficha mostra a ação
**Mover para "X"** (`LeadFunilService::moverParaEtapaAtiva`). Vazio (padrão) = sem sugestão. **Nada é criado por migration e o
lead nunca é movido sozinho**: as etapas são cadastradas pela escola.

## 4. Telas

- **Ficha do lead → aba "Roteiro de qualificação"** (só na edição). No topo, o painel com o resultado, a barra de progresso, a
  sugestão de etapa, os avisos e o estado de cada campo por dimensão (✔ respondido, … perguntado, ⊘ recusou, ○ não perguntado;
  campos do gate em destaque). Abaixo, a seção **O que a família disse** com bairro, motivo, quem decide, se vai à visita, prazo,
  mensalidade e escolas em comparação; cada campo mostra a procedência. Os campos de aluno ficam na aba **Dependentes**
  (*Situação atual*, *Escola atual* e, se a escola atual é um concorrente cadastrado, a escolha dele).
- **Resumo do Lead**: cartão "Roteiro de qualificação" com `n/9` campos-chave.
- **Lista de leads**: coluna **Roteiro** (badge; alternável) e filtro **Roteiro de qualificação**. Lead sem linha conta como
  "não iniciada".
- A seção **Qualificação** que já existia (distância, transporte, valor estimado: "entram no Lead Score") **não mudou**. O
  Lead Score mede encaixe e engajamento; o roteiro mede o que já se sabe da família. São dois indicadores, de propósito.

## 5. Minha Mesa: "Leads com qualificação incompleta"

`QualificacaoIncompleta` (área *CRM / Comercial*, permissão `ViewAny:Interessado`, mesma visibilidade da lista de leads): lead
em andamento **com primeiro contato humano** (`data_primeiro_contato`) cujo roteiro ainda não está qualificado. Antes do
primeiro contato a pendência é atender o lead, que as outras filas já mostram. Cada item cita até três campos que faltam
("Falta: Série pretendida, Data de nascimento, Turno e mais 5"). Fica **atrasado** depois de
`crm.mesa.qualificacao_incompleta_dias` (padrão 3) dias do primeiro contato.

## 6. Configuração

| Chave | Padrão |
|---|---|
| `crm.qualificacao.etapa_qualificado` (`CRM_ETAPA_QUALIFICADO`) | vazio (sem sugestão) |
| `crm.qualificacao.max_perguntas_por_mensagem` (`CRM_QUALIFICACAO_MAX_PERGUNTAS`) | 2 |
| `crm.qualificacao.faixas_mensalidade` | quatro faixas de exemplo (até R$ 1.000 ... acima de R$ 2.500): **ajustar à tabela de preços** |
| `crm.mesa.qualificacao_incompleta_dias` (`CRM_MESA_QUALIFICACAO_INCOMPLETA_DIAS`) | 3 |

## 7. Deploy

1. **Migration** `2026_11_05_130000_create_interessado_qualificacao`: cria a tabela e quatro colunas em
   `interessado_dependente`. Idempotente (só cria o que não existe) e reversível. Roda no *Git Pull* do painel.
2. **Leads que já existiam**: rodar uma vez `php artisan crm:reavaliar-qualificacao` (só leads em andamento; `--todos` inclui os
   encerrados). Sem isso, o resultado só aparece quando alguém mexer no lead ou nos alunos.
3. Opcional: definir `CRM_ETAPA_QUALIFICADO` e ajustar `crm.qualificacao.faixas_mensalidade`.
4. Não há permissão nova: a aba e a fila usam as de *Interessado*.

## 8. Limites conhecidos

- A **mesclagem de leads** (`LeadMesclagemService`) não transfere a linha de qualificação do lead de origem: ao excluir a origem,
  ela é apagada junto (cascata), e o lead de destino mantém a própria. Os alunos movidos entram no cálculo do destino.
- A lista de opções (motivos, prazos, faixas) ainda não passou pela secretaria: são configuráveis em código/config, e a tela de
  edição do roteiro (texto, ordem e obrigatoriedade das perguntas) é o Lote 2.
- `escolas_em_comparacao` guarda nomes (com sugestão a partir dos concorrentes cadastrados), não ids.
- A checagem de idade usa a premissa da seção 3 sobre `idade_minima`; é só um aviso.
- O `bairro` é texto livre. O endereço da pessoa em produção é a pivot `endereco_pessoa` (a coluna `pessoa.endereco_id` só existe no
  esquema dos testes SQLite), por isso o roteiro tem coluna própria; a tabela de bairros por unidade que preencheria
  `faixa_distancia_escola` sozinha é a Fase 2.

## 9. O que vem depois (plano completo)

| Fase | O quê | Situação |
|---|---|---|
| **1, Lote 1** | Campos, estados, aba da ficha, fila na Mesa (este documento) | **Entregue** |
| 1, Lote 2 | Tela para a secretaria editar o texto, a ordem e a obrigatoriedade das perguntas; objetivo **"Conhecer a família"** no Copiloto IA (o PHP escolhe as 1–2 perguntas; a IA só reescreve; "perguntado" é marcado ao abrir o WhatsApp) | Planejado |
| 1, Lote 3 | A **Síntese IA de Conversa** passa a propor os valores dos campos, com o trecho que os justifica; o consultor revisa antes de gravar (nada sobrescreve; evidência conferida contra o texto colado) | Planejado |
| 2 | Campos de segunda onda (critérios, dor, início desejado, bolsa...), tabela de bairros, 2 perguntas opcionais nos formulários públicos, calibragem do gate com `FunilAnaliticoService` | Planejado |
| 3 | Entrada e distribuição de leads (Onda 85: rodízio, prazo de primeira resposta, webhook de anúncios) | Depende de autorização |
| 4 | Canal WhatsApp oficial (Onda 88) | Depende de provedor e número |
| 5 | Agente SDR com Gemini (function calling, modo sugestão primeiro, governança) | Pesquisa antes de código |

Fases 3 e 4 continuam sendo as ondas descritas em [distribuicao_leads_carteira_roadmap.md](distribuicao_leads_carteira_roadmap.md) e
[whatsapp_oficial_roadmap.md](whatsapp_oficial_roadmap.md). **A agenda de visitas com disponibilidade (Onda 86) e a proposta com
aceite (Onda 87) já existem**: o agente da Fase 5 reaproveitará `AgendaVisitaService::horariosLivres/reservar`.

Decisão registrada para a Fase 5: hoje todo registro com `automatico = true` **não conta como interação** (não tira o lead de
"estagnado", não pontua e não vira primeiro contato). Uma conversa conduzida pela IA com a família precisará de um tipo
próprio, ou o lead mais bem atendido apareceria como parado.

## 10. Testes

`QualificacaoServiceTest` (estados, gravação sem sobrescrever, gate, recusa, perguntas, alertas, corte etário, sugestão de
etapa, painel), `QualificacaoFichaTest` (aba, salvar, painel, Resumo, ação de mover, filtro da lista, comando) e
`QualificacaoMesaTest` (fila, atraso, carteira, Mesa).
