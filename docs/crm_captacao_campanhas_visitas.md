# CRM: Campanhas, UTM, Visitas e Conversão em Matrícula

Onda 1 do roadmap de captação. Complementa `docs/crm_lead_score.md` e
`docs/crm_followup_whatsapp.md`.

## 1. Campanhas de marketing e rastreamento UTM

Model: `App\Models\CampanhaMarketing` (tabela `campanha_marketing`)
Resource: `App\Filament\Resources\CampanhaMarketings\CampanhaMarketingResource`
(menu **CRM / Comercial → Campanhas de Marketing**)
Serviço: `App\Services\UtmTracker`

| Campo | Uso |
|---|---|
| `nome`, `canal` | Identificação. `canal` usa `CampanhaMarketing::CANAIS` (Google Ads, Meta Ads, Instagram, e-mail, WhatsApp, indicação, evento, mídia offline, outro). |
| `codigo_utm` | Valor esperado em `utm_campaign`. Único; gravado sempre em minúsculas e sem espaços (mutator do model). |
| `data_inicio`, `data_fim`, `ativa` | Vigência. Só campanhas **ativas** recebem atribuição automática. |
| `custo` | Investimento total, base do custo por lead e por matrícula. |

### Como o lead é atribuído

1. O visitante acessa `/quero-matricular?utm_source=instagram&utm_medium=cpc&utm_campaign=CODIGO`.
   `CaptacaoInteressadoController::show()` chama `UtmTracker::capturar()`, que guarda
   `utm_source`, `utm_medium` e `utm_campaign` (minúsculas, máx. 191 caracteres) na
   sessão. Uma nova visita com UTM sobrescreve a anterior na mesma sessão.
2. Ao enviar o formulário, `UtmTracker::atribuicao()` combina a sessão com UTM enviados
   no próprio POST (o POST tem prioridade) e, se `utm_campaign` bate com o `codigo_utm`
   de uma campanha ativa, inclui o `campanha_marketing_id`.
3. `registrarAtribuicao()` grava em `interessado` (`utm_source`, `utm_medium`,
   `utm_campaign`, `campanha_marketing_id`) **apenas se o lead ainda não tiver
   atribuição** (first touch). Um novo envio do mesmo contato não reescreve a origem.
4. UTM sem campanha correspondente (ou campanha inativa) é preservado nos campos
   `utm_*`; só o `campanha_marketing_id` fica vazio.

A campanha pode ser ajustada manualmente na ficha do lead (aba **Dados do Negócio**).
Na tabela de Interessados há a coluna (oculta por padrão) e o filtro **Campanha**.

> O cadastro em si (pessoa, lead, dependentes, reenvio do formulário) é feito por
> `App\Services\CaptacaoInteressadoService`; o `registrarAtribuicao()` acima vive nele. As regras de
> reconhecimento da família e de reenvio estão em `docs/crm_funil_e_captacao.md`.

## 2. Indicadores de conversão

Serviço: `App\Services\CrmConversaoService`. "Matriculado" é o lead com `data_conversao`
preenchida.

- `porCampanha()`: leads, matrículas, taxa de conversão (%), custo, custo por lead e
  custo por matrícula (`null` quando o divisor é zero).
- `porOrigem()`: leads, matrículas e taxa por `origem_interessado`.
- `resumoCampanhas()`: totais agregados.

Widgets do dashboard (`TableWidget`, dados via `records()`):
`ConversaoCampanhaWidget` e `ConversaoOrigemWidget`. A lista de campanhas também exibe
leads, matrículas e conversão por linha.

## 3. Visitas à escola

Model: `App\Models\VisitaInteressado` (tabela `visita_interessado`)
Enum: `App\Enums\StatusVisitaInteressado` (`agendada`, `realizada`, `faltou`, `cancelada`)
Serviço: `App\Services\VisitaInteressadoService`

Campos: `interessado_id`, `interessado_dependente_id` (opcional, "toda a família" quando
vazio), `usuario_id` (consultor), `data_hora`, `status`, `observacoes`,
`lembrete_enviado_em`.

Onde usar:

- Ação **Agendar Visita** na tabela de Interessados (oculta em leads com status final).
- Aba **Visitas à Escola** na ficha do lead (`VisitasRelationManager`): criar, editar,
  marcar como **Realizada** ou **Não compareceu**.
- `CrmFollowUpCalendarWidget` exibe as visitas agendadas (cor roxa, título
  `Visita: <nome>`, vermelha se atrasada) com o mesmo escopo de consultor dos
  follow-ups: o consultor vê as visitas em que é responsável pela visita **ou** pelo lead;
  `admin` e `super_admin` veem todas.

Regras (`VisitaInteressadoService`):

- `agendar()` cria a visita (consultor padrão = consultor do lead), sincroniza o
  "próximo contato" e recalcula o lead score.
- `sincronizarProximoContato()` leva `data_proximo_contato` para a próxima visita
  futura agendada **somente** se o lead não tem retorno marcado, se o retorno já passou
  ou se a visita vier antes dele. Um retorno já marcado para antes da visita não é
  adiado.
- A variável `[Horário de Visita Agendada]` dos modelos de WhatsApp usa
  `Interessado::proximaVisita` (a visita agendada futura mais próxima), com fallback para
  `data_proximo_contato`.

### Lembretes

`crm:notificar-pendentes` (diário às 8h, `routes/console.php`) também chama
`VisitaInteressadoService::enviarLembretes()`: avisa o consultor (sino do painel, com
botão "Ver Lead") das visitas **agendadas** que ocorrem nas próximas 24 horas e ainda
não tiveram lembrete, e grava `lembrete_enviado_em` para não repetir. O lembrete de
visita é apenas no sino; não há e-mail. Como as notificações do sino são enfileiradas,
dependem do `queue:work` já agendado a cada minuto.

## 4. Conversão de lead em matrícula

Serviço: `App\Services\InteressadoMatriculaService`

A ação **Matricular** da tabela de Interessados abre o Assistente de Matrícula com
`?interessado={id}` (visível a quem tem `View:EnrollmentWizard`). Quem não tem acesso ao
assistente vê **Marcar matriculado**, atalho que agora aplica a **mesma conversão** do assistente
(`LeadFunilService::marcarMatriculado()` → `registrarConversao()`: status de ganho, data, indicação,
documentos, limpeza do rascunho). Se não existir etapa de ganho cadastrada, a ação avisa em vez de
deixar o lead sem status (antes gravava `status_interessado_id = null`).

**No Kanban**, arrastar um card para uma etapa de ganho não converte o lead por conta própria: quem
tem acesso ao assistente é levado a ele (com o lead pré-preenchido); os demais usam o atalho acima.
Um lead já matriculado não pode ser arrastado de volta a uma etapa ativa nem para perda. Na edição em
lote, etapas de ganho não são oferecidas e, quando a etapa é alterada, leads já matriculados são pulados.

`EnrollmentWizard::mount()` pré-preenche o formulário com `dadosParaWizard()`:

- **Alunos:** um item por dependente (nome e data de nascimento).
- **Responsável:** o contato do lead entra como responsável financeiro (100%) com nome,
  CPF, e-mail e telefone. Exceção: se o nome do contato for igual ao de um dependente, o
  lead é o próprio aluno; o contato vai para esse aluno e nenhum responsável é
  presumido.
- **Curso e unidade:** derivados da série do primeiro dependente que tiver série.
- Turma, vínculo e demais campos continuam sendo escolhidos pela secretaria.

Ao finalizar, `registrarConversao()` marca o lead de origem (`interessadoId`, propriedade
Livewire `#[Locked]`): define `data_conversao` se ainda vazia, muda o status para
"Matriculado" (ou o primeiro status `is_ganho`) e recalcula o score. É idempotente. O
mesmo serviço passou a ser usado pelo vínculo antigo por `pessoa_id`
(`marcarConversaoCRM`), que antes só preenchia `data_conversao` e deixava o status
inalterado.

### Matrícula 100% online (`MatriculaOnlineService`)

Ao concluir uma matrícula pelo fluxo externo, o lead de origem também é convertido por
`registrarConversao($lead, [$matricula])`. Antes, só `data_conversao` era preenchida: o lead seguia
"ativo" (recebendo régua e alertas), a indicação não virava `matriculado` e o rascunho de pré-matrícula
(CPF/endereço) ficava no banco. O lead é localizado assim (`localizarLeadParaConversao`):

1. lead cujo contato é o **responsável** ou o **próprio aluno** (mesmo cadastro de `Pessoa`);
2. senão, lead com **dependente de nome idêntico** (sem caixa, acento ou espaços repetidos) **e** cujo
   contato tem o mesmo e-mail ou CPF do responsável.

Nunca por nome parcial: a busca anterior (`like %nome%`) convertia o lead de outra família quando o
nome do aluno era parte do nome de outra criança ("Ana" × "Mariana"). Havendo mais de um candidato,
prefere-se um lead ainda não convertido.

### Correção no assistente

O campo oculto `pessoa_id_existente` não chegava ao estado devolvido por
`getState()`, e `save()` lia a chave sem `??`, o que gerava "Undefined array key" (a
exceção era capturada e exibida como "Erro ao realizar matrícula"). O campo agora usa
`dehydratedWhenHidden()` e as duas leituras usam `?? null`.

## 5. Leads da landing page

Model: `App\Models\LandingLead` (tabela `landing_leads`)
Resource: `App\Filament\Resources\LandingLeads\LandingLeadResource`
(**CRM / Comercial → Leads da Landing Page**)

Os leads da landing (`/`, formulário "Solicitar demonstração") são **escolas interessadas
no sistema Torre360** (B2B), não famílias interessadas em matrícula. Por isso são
mantidos separados de `Interessado` e **não** têm ação de conversão. A tela permite
consultar, marcar como **Em contato**, **Descartar** e **Reabrir**, e excluir. Status:
`novo` (padrão), `em_contato`, `descartado`. A criação pelo painel é desabilitada.

## 6. Permissões

Migration `2026_09_28_084500_create_crm_captacao_permissions`:

| Entidade / widget | Ações | Papéis concedidos |
|---|---|---|
| `CampanhaMarketing` | ViewAny, View, Create, Update, Delete, DeleteAny | super_admin, admin, secretaria |
| `VisitaInteressado` | idem | super_admin, admin, secretaria |
| `LandingLead` | idem | super_admin, admin |
| `View:ConversaoCampanhaWidget`, `View:ConversaoOrigemWidget` | visualização do widget | super_admin, admin, secretaria |

Policies em `app/Policies` (`CampanhaMarketingPolicy`, `VisitaInteressadoPolicy`,
`LandingLeadPolicy`) delegam para essas permissões. Ajuste fino continua possível pelo
Shield.

## 6.1 Máscara de telefone na ficha do lead

Em `InteressadoForm`, o campo `pessoa_telefone` usa máscara dinâmica (Alpine mask via
`Filament\Support\RawJs`): com até 10 dígitos aplica `(99) 9999-9999` (fixo) e com 11
dígitos `(99) 99999-9999` (celular), sempre com DDD. O valor é gravado em `pessoa.telefone`
já formatado (o botão de WhatsApp da tabela remove os não-dígitos antes de montar o link).
Não há validação de quantidade de dígitos, para não bloquear leads antigos com telefone em
outro formato.

A mesma máscara vale na criação do interessado (mesmo formulário) e no cadastro de Pessoa
(`PessoaForm`), inclusive no modal "criar pessoa" aberto a partir do select de Pessoa.

## 7. Migrations

| Migration | O que faz |
|---|---|
| `create_campanha_marketing_table` | Tabela `campanha_marketing`. |
| `create_visita_interessado_table` | Tabela `visita_interessado` (FK para `interessado`, `interessado_dependente`, `users`). |
| `add_campanha_e_utm_to_interessado_table` | `campanha_marketing_id` (FK, `nullOnDelete`), `utm_source`, `utm_medium`, `utm_campaign` em `interessado`. |
| `create_crm_captacao_permissions` | Permissões e concessões da seção 6. |
| `2026_10_04_210000_add_expiracao_token_documentos_to_interessado_table` | `interessado.token_documentos_expira_em` (validade do link do portal de documentos; links já emitidos ganham 90 dias). |
| `2026_10_04_210100_add_automatico_to_historico_contato_table` | `historico_contato.automatico` + índice `(interessado_id, automatico, data_contato)`; classifica os registros automáticos antigos pelo texto que o sistema grava. |
| `2026_10_04_230000_add_unidade_e_turno_to_interessado_dependente_table` | `interessado_dependente.unidade_id` (FK, `nullOnDelete`) e `turno_preferencia`. |
| `2026_10_04_230100_add_ultimo_alerta_em_to_interessado_table` | `interessado.ultimo_alerta_em` (controle do intervalo entre alertas). |

## 8. Testes

`CampanhaUtmCaptacaoTest`, `ConversaoCrmTest`, `VisitaInteressadoTest`,
`InteressadoMatriculaWizardTest`, `LandingLeadResourceTest`, `CrmCaptacaoPermissoesTest`
e a lista de widgets em `ShieldWidgetsTest`. Cobertura do funil e da captação:
`CaptacaoReenvioTest`, `CaptacaoIndicacaoTest`, `CaptacaoRecaptchaTest`, `MatriculaOnlineConversaoCrmTest`,
`LeadFunilServiceTest`, `FunilAcoesInteressadosTest`, `AlertaLeadsTest`, `ConsultoresCrmTest`,
`CrmIaDossieCacheTest` (ver `docs/crm_funil_e_captacao.md`).
