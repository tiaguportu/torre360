# Alertas de Estagnação, Mensagens Rápidas de WhatsApp e Repasse ao Consultor

Model: `App\Models\Interessado` (tabela `interessado`)
Comando: `App\Console\Commands\NotificarInteressadosPendentesCommand`
(`crm:notificar-pendentes`)
Model: `App\Models\MensagemWhatsappTemplate` (tabela `mensagem_whatsapp_template`)
Resource: `App\Filament\Resources\MensagemWhatsappTemplates\MensagemWhatsappTemplateResource`

## 1. Alertas de estagnação e follow-up

O comando `crm:notificar-pendentes` (agendado **todo dia às 8h** em
`routes/console.php`) já notificava o consultor responsável quando a
`data_proximo_contato` de um lead vencia (`Interessado::scopePrecisaContato()`).
Essa lógica foi mantida e passou a ser complementada por um segundo tipo de
alerta: **estagnação**, quando um lead ativo fica **7 dias ou mais sem
qualquer interação registrada**, independentemente de ter ou não uma data de
próximo contato agendada.

- `Interessado::scopeEstagnados(int $dias = 7)`: filtra leads sem
  `historico_contato` nos últimos N dias — considerando tanto quem já teve
  contato antes (mas parou) quanto quem nunca teve contato e já passou desse
  prazo desde a criação. **Registros automáticos** (`historico_contato.automatico`:
  e-mails da régua, análises de IA) não contam como contato — ver
  `docs/crm_funil_e_captacao.md`.
- `Interessado::diasSemInteracao()`: dias desde a última interação registrada
  (`ultimoHistorico()->data_contato`, que também ignora automáticos) ou desde a
  criação do lead, se nunca houve contato.
- `Interessado::estaEstagnado(int $dias = 7)`: atalho booleano usado na UI.

A lógica vive em `App\Services\AlertaLeadsService` (o comando só a chama e imprime o resumo).
Ela busca primeiro os leads atrasados (`precisaContato()`) e, entre os
demais, os estagnados (`estagnados()`), evitando notificar o mesmo lead duas
vezes no mesmo disparo. Para cada lead com consultor responsável, ela:

1. Envia e-mail (`AcompanhamentoInteressadoNotification` para atraso,
   `LeadEstagnadoNotification` para estagnação).
2. Envia notificação para o sino do Filament
   (`Notification::make()->sendToDatabase($consultor)`), com botão "Ver Lead".
3. Registra um evento no activity log (`log name` `crm`).
4. Grava `interessado.ultimo_alerta_em` (por `DB::table`, sem eventos do Eloquent: não gera
   activity log de atualização nem muda `updated_at`).

> Notificações do sino do Filament (`Filament\Notifications\DatabaseNotification`)
> implementam `ShouldQueue` — elas só aparecem depois que a fila for
> processada (`queue:work`, já agendado a cada minuto em `routes/console.php`).

### Sem repetição diária, escalonamento e leads sem consultor

Antes, o mesmo aviso era reenviado todo dia enquanto o lead continuasse atrasado/estagnado, e leads
**sem consultor** (todos os que entram pelo formulário do site) nunca geravam alerta. Agora
(`config/crm.php`, bloco `alertas`):

| Regra | Padrão | Variável de ambiente |
|---|---|---|
| Intervalo mínimo entre dois avisos do mesmo lead ao consultor (`ultimo_alerta_em`) | 3 dias | `CRM_ALERTA_INTERVALO_DIAS` |
| Lead atrasado (ou estagnado além do limite de 7 dias) há tantos dias vai também à gestão | 7 dias | `CRM_ALERTA_ESCALONAR_APOS_DIAS` |
| Lead ativo sem consultor há mais de X horas entra no resumo diário da gestão | 24 h | `CRM_ALERTA_SEM_CONSULTOR_HORAS` |

- **Gestão** = usuários com conta ativa (`User::ativos()`) e papel `admin` ou `super_admin`.
- O que vai à gestão é **um resumo por execução** (não um aviso por lead): "Leads parados exigem
  atenção da gestão" (com atalho para a aba *Precisa de contato*) e "Leads sem consultor responsável"
  (com atalho para a lista filtrada por **Sem consultor responsável**). No máximo um resumo por dia,
  mesmo que o comando rode mais de uma vez (`Cache::add('crm:alertas:gestao:{data}')`).
- A saída do comando informa quantos leads foram avisados (atrasados/estagnados), quantos foram
  levados à gestão e quantos estão sem consultor.
- Migration: `2026_10_04_230100_add_ultimo_alerta_em_to_interessado_table`.

O mesmo comando também envia, antes dos alertas acima, o **lembrete de visitas**
agendadas para as próximas 24 horas (só no sino, uma única vez por visita). Detalhes em
`docs/crm_captacao_campanhas_visitas.md`.

Na tabela de Interessados (`InteressadosTable`), há uma coluna "Sem Interação"
(dias desde a última interação, com destaque vermelho quando estagnado) e um
filtro "Estagnado (7+ dias sem interação)", ambos usando os mesmos
scopes/métodos do model — não há lógica duplicada entre backend e UI. O filtro
**Sem consultor responsável** lista os leads com `usuario_id` nulo.

### Alerta manual na ficha do lead

Na tela de edição (`InteressadoForm`), quando `Interessado::precisaDeContato()`
é verdadeiro, aparece o botão vermelho pulsante `alerta_contato`, que dispara na
hora a mesma `AcompanhamentoInteressadoNotification` (e-mail) e a notificação do
sino para o consultor responsável.

O modal de confirmação ("Enviar Alerta de Acompanhamento?") mostra **para qual
e-mail a mensagem será enviada**: o `email` do usuário consultor
(`Interessado::usuario`), já que a notificação usa o canal `mail` padrão e o
`User` não sobrescreve `routeNotificationForMail`. O texto vem de
`InteressadoForm::descricaoDoAlerta()`, que escapa nome e e-mail e trata dois
casos:

- consultor **sem e-mail** cadastrado: o modal avisa que só a notificação no
  sistema será enviada (o canal `mail` ignora a notificação quando não há
  endereço);
- lead **sem consultor**: o modal avisa que não há para quem enviar.

Além do e-mail, o modal traz o link **"Abrir o WhatsApp de {consultor} com a
mensagem pronta"** (`target="_blank"`), montado por
`ConsultorWhatsappService::urlParaInteressado()`: é a mesma mensagem da ação
"Enviar ao consultor" (seção 3), com o contato direto do lead, o próximo contato
marcado como "(atrasado)" e o resumo dos últimos contatos. O link independe do
e-mail e do clique em "Sim, enviar alerta": serve para avisar também (ou só)
pelo WhatsApp. Se o consultor não tem telefone cadastrado, o link continua
disponível, abre o WhatsApp sem destinatário e o modal avisa isso.

O bloco de ações recebeu a chave `alertaContato` para a ação poder ser montada
em testes (`TestAction::make('alerta_contato')->schemaComponent('alertaContato')`).

## 2. Mensagens rápidas de WhatsApp e Integração com Copiloto IA (Gemini)

Modelos de mensagem ficam na tabela `mensagem_whatsapp_template`
(`nome`, `conteudo`, `instrucoes_ia`, `ativo`), gerenciáveis pelo Filament em
**CRM / Comercial → Modelos de WhatsApp**.

### Propósito dos Modelos vs. Copiloto IA
Os modelos oficiais e o Copiloto IA atuam de forma **complementar**:
1. **Modelos Oficiais (0s de latência & custo zero):** Ideais para mensagens transacionais, comunicados que exigem conformidade jurídica rígida e envio de links dinâmicos e rastreáveis (como a Pesquisa de Satisfação Pós-Visita com NPS).
2. **Copiloto IA (Personalização contextual):** Ideal para follow-ups consultivos, quebra de objeções específicas (preço, metodologia) e reativação calorosa de famílias estagnadas.
3. **Sinergia Híbrida (Modelo + IA):** No modal do *Copiloto WhatsApp IA*, o consultor pode escolher um **Modelo Institucional de Referência (Opcional)** para que a IA adapte e enriqueça o comunicado oficial com o perfil e momento da família. Da mesma forma, na ação rápida "WhatsApp" da tabela, há a opção **Personalizar com Copiloto IA (Gemini) ✨** para humanizar o modelo escolhido antes de abrir o mensageiro.
4. **Contingência Inteligente (Fallback):** Se a API do Gemini estiver instável ou a cota esgotar, o fallback do serviço automaticamente pré-preenche as variáveis dinâmicas do modelo base, garantindo que o atendimento nunca seja interrompido.

### Comportamento do Copiloto IA (editável sem deploy)

Antes, persona, diretrizes, descrição dos objetivos/tons e parâmetros do Gemini
ficavam escritos em `CrmIaVendasService::gerarMensagemCopiloto()`. Agora a equipe
ajusta isso em dois níveis:

1. **Regras gerais** — página `ConfiguracaoCopilotoIa`
   (`/admin/crm/comportamento-copiloto-ia`), aberta pelo botão **Comportamento do
   Copiloto IA** na lista de Modelos de WhatsApp. Permissão Shield:
   `View:ConfiguracaoCopilotoIa` (concedida a `super_admin`/`admin` pela migration
   `2026_10_07_210002_grant_configuracao_copiloto_ia_permissions`). Edita:
   - `persona` e `diretrizes` (lista numerada automaticamente);
   - `mencionar` / `evitar` (texto livre, uma ideia por linha);
   - descrição dos 5 `objetivos` e dos 3 `tons` (as **chaves são fixas**, pois os
     selects do Copiloto e o envio rápido dependem delas; só a descrição muda);
   - `gemini.temperature` (0 a 1) e `gemini.max_output_tokens` (200 a 2000).

   Os padrões ficam em `config/copiloto_ia.php`. As alterações são gravadas em
   `copiloto_ia_configuracoes` (linha mais recente vale, cache em
   `CopilotoIaConfiguracao::CACHE_KEY`); **Restaurar padrão** apaga as linhas.
   **Pré-visualizar prompt** mostra o system instruction com o conteúdo atual do
   formulário (mesmo não salvo), sem chamar o Gemini.

2. **Instruções por modelo** — campo `instrucoes_ia` (opcional, até 1500
   caracteres) no formulário do Modelo de WhatsApp. É somado às regras gerais
   **somente quando o modelo é usado como base do Copiloto** (modal *Copiloto
   WhatsApp IA* e opção *Personalizar com Copiloto IA* do envio rápido); não afeta
   o envio direto do modelo.

`CrmIaVendasService::montarSystemInstructionCopiloto()` monta o prompt na ordem:
persona → diretrizes numeradas → linha do objetivo → linha do tom → regras fixas
→ (regra do modelo base, se houver) → "Sempre mencione…" → "Nunca mencione…" →
"Instruções específicas do modelo…" → `REGRA_DADOS_NAO_CONFIAVEIS`.

**Não editável (fica no código de propósito):** a linha do objetivo/tom, a
proibição de links e o "retorne apenas o texto puro" (o pós-processamento
`removerLinks()` depende deles) e a regra de segurança contra prompt injection,
sempre anexada por último. Editar a configuração não desliga essas proteções.

Testes: `tests/Feature/CopilotoIaConfiguracaoTest.php`.

### Variáveis dinâmicas substituídas automaticamente:
- `[Nome do Responsável]` → `interessado.pessoa.nome`
- `[Primeiro Nome]` → primeiro nome do responsável
- `[Nome do Aluno]` → `nome_crianca` do dependente selecionado (ou o primeiro da lista)
- `[Horário de Visita Agendada]` (ou `[Data da Visita]`) → data e hora da próxima visita agendada do lead (`Interessado::proximaVisita`), com fallback para `interessado.data_proximo_contato` formatada como `d/m/Y às H:i h`, ou "a definir" se não houver
- `[Link da Pesquisa da Visita]` (ou `[Link da Pesquisa]`, `[Link]`) → URL pública da pesquisa de satisfação da visita (`PesquisaSatisfacaoVisita`)
- `[Nome da Escola]` (ou `[Escola]`) → Nome oficial da instituição

A ação "WhatsApp" na tabela de Interessados (`InteressadosTable`, ao lado de
"Atendimento") abre um formulário para escolher o modelo (e o aluno, se o
lead tiver mais de um dependente cadastrado) e, ao confirmar, monta a URL
canônica `https://api.whatsapp.com/send?phone=<telefone>&text=<mensagem>` e abre em nova aba — utiliza
codificação RFC 3986 (`PHP_QUERY_RFC3986`) para preservar 100% dos emojis UTF-8 sem risco de corrupção. O
telefone é normalizado (somente dígitos) e recebe o DDI `55` quando tem 11
dígitos ou menos (DDD + número).

A ação só aparece para leads com telefone cadastrado
(`filled($record->pessoa?->telefone)`).

## 3. Repasse do lead ao consultor por WhatsApp

Quem opera o CRM (secretaria/gestão) pode encaminhar um lead ao consultor
responsável (`Interessado::usuario`) por um link `https://api.whatsapp.com/send`, com o contato direto
do interessado e um resumo do que já foi conversado. Toda a regra fica em
`App\Services\ConsultorWhatsappService`.

**Telefone do consultor.** O `User` não tem telefone próprio: o número vem da
`Pessoa` vinculada ao usuário (`pessoa_user`), preferindo a Pessoa cujo
`user_id` é o próprio consultor e, na falta dela, a primeira vinculada que tenha
telefone. Sem telefone, o link vira `https://api.whatsapp.com/send?text=...` (o WhatsApp abre
com a mensagem pronta e quem envia escolhe o contato) e a interface sinaliza em
amarelo. Para o envio ir direto ao consultor, basta preencher o telefone da
Pessoa dele.

**Ação por linha — "Enviar ao consultor"** (`enviarAoConsultor`, ao lado de
"WhatsApp"): link nativo em nova aba (`->url()->openUrlInNewTab()`, sem modal,
então não sofre bloqueio de pop-up). Só aparece para leads com consultor. A
mensagem traz:

- link `https://wa.me/<telefone do lead>` (o consultor só clica para conversar);
- alunos (com a série, quando houver), status, origem, temperatura e score;
- próximo contato (com "(atrasado)" quando já passou) e visita agendada;
- "Resumo do que já foi conversado": os **3 contatos mais recentes** do
  `HistoricoContato` (data, tipo, relato em uma linha truncada em 160
  caracteres e resultado), ou "Ainda sem contatos registrados";
- observações do lead (truncadas em 300 caracteres).

**Ação em lote — "Enviar aos consultores (WhatsApp)"** (`enviarAosConsultores`):
agrupa os leads selecionados por consultor e mostra, num modal, um botão do
WhatsApp por consultor com todos os leads dele numa só mensagem compacta (uma
linha por lead, sem histórico). Leads sem consultor aparecem num aviso e ficam
fora das mensagens (use "Atribuir Consultor").

**Limite de tamanho e Codificação de Emojis.** A mensagem é limitada a 1.500 caracteres antes da
codificação RFC 3986 (`PHP_QUERY_RFC3986`), evitando estourar a URL e mantendo emojis intactos: na mensagem
de um lead, os contatos mais antigos são descartados primeiro; na do lote, os
últimos leads são omitidos com o aviso "... e mais N lead(s)".

**Eager loading.** A mensagem é montada no render de cada linha, então a tabela
carrega as relações de `ConsultorWhatsappService::RELACOES` (mais
`ultimoHistorico`, usado no destaque da linha) com `modifyQueryUsing`. Com
`Model::preventLazyLoading` ativo fora de produção, qualquer relação não
carregada quebraria a listagem em dev/teste.

Não há registro automático de `HistoricoContato` ao enviar: o link nativo não
passa pelo servidor. Quem fala com o interessado registra o atendimento pela
ação "Atendimento".
