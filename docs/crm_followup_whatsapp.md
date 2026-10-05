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

## 2. Mensagens rápidas de WhatsApp

Modelos de mensagem ficam na tabela `mensagem_whatsapp_template`
(`nome`, `conteudo`, `ativo`), gerenciáveis pelo Filament em
**CRM / Comercial → Modelos de WhatsApp**. O texto do modelo aceita três
variáveis, substituídas automaticamente no envio:

- `[Nome do Responsável]` → `interessado.pessoa.nome`
- `[Nome do Aluno]` → `nome_crianca` do dependente selecionado (ou o único
  dependente, se houver apenas um)
- `[Horário de Visita Agendada]` → data da próxima visita agendada do lead
  (`Interessado::proximaVisita`, ver `docs/crm_captacao_campanhas_visitas.md`),
  com fallback para `interessado.data_proximo_contato`, formatada como
  `d/m/Y às H:i h`, ou "a definir" se não houver nenhuma das duas

A ação "WhatsApp" na tabela de Interessados (`InteressadosTable`, ao lado de
"Atendimento") abre um formulário para escolher o modelo (e o aluno, se o
lead tiver mais de um dependente cadastrado) e, ao confirmar, monta a URL
canônica `https://api.whatsapp.com/send?phone=<telefone>&text=<mensagem>` e abre em nova aba — utiliza
codificação RFC 3986 (`PHP_QUERY_RFC3986`) para preservar 100% dos emojis UTF-8 sem risco de corrupção
(o encurtador `wa.me` sofre de bug na Meta ao fazer redirect 302 que corrompe emojis para ``). O
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
