# Funil, captação pública e segurança do CRM

Resultado dos Lotes A, B, C e E da revisão do módulo de CRM (o Lote C, de desempenho e escala, está na seção 10; o
Lote E, que fecha os itens 6, 10, 13 e 15 da auditoria — LGPD, régua, importação por IA e termômetro de vagas —, na
seção 11). Complementa
`docs/crm_captacao_campanhas_visitas.md`, `docs/crm_followup_whatsapp.md` e `docs/crm_lead_score.md`.

| Peça | Onde |
|---|---|
| Regras de movimentação do lead no funil | `App\Services\LeadFunilService` |
| Cadastro pelo formulário público (pessoa, lead, dependentes, reenvio) | `App\Services\CaptacaoInteressadoService` |
| "Família Indica Família" no formulário público | `App\Services\IndicacaoCaptacaoService` |
| Alertas diários, escalonamento e leads sem consultor | `App\Services\AlertaLeadsService` |
| Quem pode ser consultor / contas ativas | `User::consultoresCrm()`, `User::ativos()` |
| Etapas e tipos de contato do sistema | `StatusInteressado::inicial()/ganho()/perdido()`, `TipoContatoInteressado::porNome()` |
| Contadores em cache (abas e selo do menu) | `App\Services\ContadoresCrm` |
| Configuração | `config/crm.php` |

## 1. `LeadFunilService`: uma só regra para mover o lead

Antes, o Kanban, a ação de linha da tabela e a edição em lote tinham cada um a sua cópia da lógica de
"perdido" (com motivos e textos diferentes) e a edição em lote mandava leads para *Perdido*/*Matriculado*
sem passar por nenhuma trava. Agora todos chamam o serviço; violações lançam `DomainException` com
mensagem pronta para a tela.

| Método | Regra |
|---|---|
| `moverParaEtapaAtiva($lead, $status, $usuarioId)` | Só etapas **não finais** (nem de ganho, nem de perda). Recusa se o lead já está matriculado. Mover para a mesma etapa não faz nada (`false`). Sair de uma etapa de perda limpa `motivo_perda` e grava "Lead reativado no Funil de Vendas…" (`resultado = retornar`). |
| `marcarComoPerdido($lead, $status, $motivo, $concorrente, $observacoes, $usuarioId, $atributosExtras, $relatoExtra)` | Exige etapa de perda e motivo em `Interessado::MOTIVOS_PERDA`. Recusa lead matriculado. Em *Concorrência*, o nome da escola vai em `motivo_perda` ("Concorrência: Colégio X"). Registra na linha do tempo (`resultado = sem_interesse`). Retorna o motivo como gravado. |
| `marcarMatriculado($lead)` | Atalho para quem não acessa o Assistente de Matrícula: chama `InteressadoMatriculaService::registrarConversao()` (status de ganho, data, indicação, documentos, limpeza do rascunho). Sem etapa de ganho cadastrada, lança exceção (antes gravava `status = null`). |
| `registrarAtendimento($lead, $dados, $usuarioId)` | Grava o contato (data real do contato opcional), reagenda o próximo contato e preenche `data_primeiro_contato` **só no primeiro atendimento**. |
| `motivoPerdaFormatado()` / `motivoBase()` | `motivoBase('Concorrência: Colégio X')` → `Concorrência`, para agregar relatórios por motivo. |

**Ponto de extensão:** `marcarComoPerdido()` aceita `$atributosExtras` (colunas gravadas junto com a perda) e
`$relatoExtra` (trecho do relato). É por aí que dados de inteligência competitiva (escola concorrente
escolhida como chave estrangeira, fator decisivo) devem entrar, sem duplicar o fluxo.

**Registros do funil** usam o tipo de contato `Movimentação no Funil` (`TipoContatoInteressado::FUNIL`), e não
mais `like '%Presencial%' ?? 1`, que os fazia passar por visita presencial.

### Etapas do funil sem nomes e ids fixos

`StatusInteressado::inicial()` (a etapa "Novo" ou, se renomeada, a primeira ativa por `ordem`),
`::ganho()` ("Matriculado" ou a primeira com `is_ganho`) e `::perdido()` ("Perdido" ou a primeira final que
não é de ganho). O formulário público não usa mais `?? 1` (id fixo) como fallback.

### Kanban e tabela

- **Arrastar para uma etapa de ganho não converte o lead.** Quem acessa o Assistente de Matrícula é
  levado a ele (`?interessado={id}`); os demais usam o atalho `marcarMatriculado()`.
- Lead matriculado não é arrastado de volta a etapa ativa nem para perda (a matrícula continua existindo).
- Soltar o card na mesma coluna não gera movimentação.
- `updateRecordStatus` e `confirmarPerda` checam a policy `update`; `leadPerdaId`, `statusPerdaId` e os
  demais estados do modal de perda são `#[Locked]`; só quem pode editar consegue arrastar.
- **Edição em lote:** etapas de ganho não são oferecidas; etapa de perda exige o motivo; "motivo" sem mudar
  a etapa só corrige leads que **já** estão perdidos; ao **alterar a etapa**, leads já matriculados são
  pulados (a notificação informa quantos e eles não recebem nenhuma outra alteração do lote). Os campos
  simples (consultor, temperatura, origem…) seguem como antes quando a etapa não muda.
- **Atribuir consultor:** só aceita usuários de `User::consultoresCrm()` e **avisa o consultor** pelo sino.
- **Atendimento:** o formulário aceita a data/hora real do contato e não oferece os tipos internos do sistema.
- **Link de pré-matrícula:** o token era gerado dentro do `form()` da ação, ou seja, a cada renderização do
  modal, invalidando o link recém-copiado. Agora é gerado uma única vez ao abrir (`mountUsing`) e **reaproveitado
  enquanto válido** (`ConviteMatriculaService::obterOuGerarConvite()`); a ação **Gerar novo link de
  pré-matrícula** (com confirmação) invalida o anterior de propósito.
- As ações que alteram o funil (`marcarPerdido`, `finalizarMatricula`, `regenerarConvite`, atribuição em lote)
  exigem a permissão `update` do lead.

## 2. Formulário público: `CaptacaoInteressadoService`

`POST /quero-matricular` fazia `updateOrCreate` por pessoa: quem reenviava o formulário tinha o lead
voltado para "Novo" (mesmo Matriculado/Perdido), as observações do consultor sobrescritas e os dependentes
apagados e recriados (perdendo o vínculo com visitas e documentos). Agora, dentro de uma transação
serializada por e-mail (`Cache::lock`, para que um duplo clique não crie dois leads):

1. **Reconhecimento da pessoa**: e-mail (sem caixa) → CPF válido → telefone normalizado (últimos 11 dígitos,
   só entre quem **já é lead**, para não atrelar o lead a um aluno que divide o número). Pessoa existente só
   tem campos **vazios** completados; CPF inválido é ignorado.
2. **Lead novo**: etapa inicial, `data_proximo_contato` em 1 dia, origem (*Indicação* se veio por link de
   indicação e a família não escolheu outra; senão *Site*), UTM first touch, indicação, score.
   `data_primeiro_contato` **não** é mais gravada no cadastro: é o primeiro contato da *escola* com a família,
   preenchida pelo primeiro atendimento registrado.
3. **Lead existente (reenvio)**: nada é sobrescrito. Vira um registro `Formulário do Site` na linha do tempo
   (não automático: conta como interação da família), o próximo contato é **antecipado, nunca adiado**, e
   **lead perdido é reaberto** na etapa inicial (`motivo_perda = null`). Lead matriculado não é reaberto
   (possível novo aluno), mas a equipe é avisada.
4. **Dependentes**: reconhecidos por nome (sem caixa/acento/espaços repetidos) e, quando as duas datas
   existem, pela data de nascimento. O existente só ganha o que estava vazio; os novos são criados; **nenhum é
   apagado**. `unidade_id` e `turno_preferencia` são colunas do dependente (antes ficavam só no texto de
   `observacoes`) e aparecem na ficha do lead, que também aceita aluno sem série (como o formulário público).
5. **Notificação da equipe**: título conforme o caso (novo / perdido voltou / família matriculada / reenvio) e
   o consultor do lead também é avisado. Não depende mais de a permissão `View:Interessado` existir
   (`PermissionDoesNotExist` cai para os administradores).
6. **E-mail de agradecimento**: no máximo um por pessoa a cada `crm.captacao.agradecimento_janela_horas`
   (24 h) — o formulário é público, e sem isso serviria para encher a caixa de entrada de terceiros.

### Família Indica Família (`IndicacaoCaptacaoService`)

`Pessoa::linkIndicacao()` gera `/quero-matricular?indicacao=CODIGO`, mas o parâmetro era ignorado. Agora
`show()` guarda o código (formato `[A-Z0-9-]{3,30}`) na sessão e o envio cria `indicacao_interessados` com
status `pendente`. O código no próprio POST tem prioridade sobre o da sessão. **Autoindicação** (mesma pessoa,
e-mail, CPF ou telefone) é ignorada; reenviar o formulário não duplica a indicação nem troca quem indicou.

### reCAPTCHA

`RecaptchaV3` é regra **implícita** (`$implicit = true`): antes, omitir `recaptcha_token` pulava a verificação
inteira. Sem as chaves configuradas o formulário continua funcionando (log "reCAPTCHA ignorado"), e falha de
rede com o Google não bloqueia a família.

## 3. Matrícula online converte o lead

Ver `docs/crm_captacao_campanhas_visitas.md` (seção 4): `MatriculaOnlineService` usa
`registrarConversao()` e localiza o lead por cadastro do responsável/aluno ou por dependente de nome idêntico
com o mesmo contato, nunca por nome parcial.

## 4. Interações automáticas (`historico_contato.automatico`)

Registros gerados pelo sistema/IA ficam na linha do tempo, mas **não contam como interação**: e-mail da régua
de follow-up, análise de documento por IA e dossiê da IA salvo no histórico. Eles não tiram o lead de
"estagnado", não somam no Lead Score (total, bem-sucedidas, recência), não entram no resumo repassado ao
consultor e não contam em "Contatos" da tabela. `HistoricoContato::scopeInteracoes()`,
`Interessado::interacoes()` e `ultimoHistorico()` aplicam a regra; o relation manager mostra a coluna
**Registro** (Manual/Automático). Ações da família contam como interação.

## 5. Alertas

`AlertaLeadsService` (resumo em `docs/crm_followup_whatsapp.md`): um aviso por lead a cada
`crm.alertas.intervalo_dias`, escalonamento à gestão e resumo diário de leads sem consultor.
`ReguaFollowUpService` também passou a usar `User::ativos()`: o canal "notificação do sistema" consultava a
coluna `is_active`, que **não existe** (foi substituída por `activated_at`/`deactivated_at`) e falhava para
todo lead sem consultor.

## 6. Quem é "consultor"

`User::consultoresCrm()`: contas **ativas** com a permissão `crm.permissao_consultor` (padrão
`Update:Interessado`), direta ou por papel, mais `admin` e `super_admin`. Contas de famílias, professores etc.
deixam de aparecer nas listas de consultor (ficha do lead — onde o consultor atual continua selecionável —,
filtro do Kanban, atribuição em lote, importação por IA). `User::ativos()` replica em SQL o acessor `is_active`.

## 7. IA: segurança e custo

- **Chave do Gemini no header** (`x-goog-api-key`), não na URL. Mensagens de erro de rede (cURL) traziam a URL
  completa — e, com ela, a chave — para o chat, o dossiê e os logs. `GeminiAgentService::sanitizarMensagemDeErro()`
  remove a chave configurada, `key=` e tokens `AIza…`; chat e contingências não devolvem mais o texto da exceção.
- **Texto de terceiros é dado, não comando:** dossiê, copiloto e resumo de conversa delimitam o conteúdo do lead
  (`<dados_do_lead>`, `<conversa>`), removem marcações iguais dentro do texto e instruem o modelo a ignorar
  instruções ali contidas. O copiloto **remove links** da mensagem gerada (phishing por injeção de prompt).
- **Saída escapada:** o cabeçalho do Dossiê IA passa por `e()`; o PDF usa `markdownSeguro()` (sem HTML cru).
- **Dossiê em cache** (`CrmIaVendasService::dossieDoLead()`, 15 min): o modal do Filament reconstrói o formulário
  a cada interação e gerar dentro dele chamava o Gemini de novo a cada clique. A chave é descartada quando o
  histórico do lead muda (`HistoricoContato` saved/deleted) e respostas de contingência (`fallback = true`)
  nunca são guardadas. O PDF reaproveita o mesmo cache.
- **Contexto do lead em texto puro:** `CrmIaVendasService::montarContextoLead()` monta o texto enviado ao Gemini
  (dossiê e copiloto). `VisitaInteressado::status` é um enum (`StatusVisitaInteressado`) e não pode ser
  interpolado em string — usar `getLabel()` ("Agendada", "Realizada", "Não compareceu", "Cancelada"). Interpolar
  o enum direto derrubava com `Object of class … could not be converted to string` qualquer lead que tivesse
  visita registrada, ao abrir o Dossiê IA ou o Copiloto WhatsApp IA. Ao incluir outro campo com cast de enum
  nesse contexto, converter da mesma forma.
- **Resumo de conversa com áudio** (`CrmIaVendasService::resumirConversaWhatsapp($lead, $texto, $audios)`): o
  consultor pode anexar até `MAX_AUDIOS_CONVERSA` (5) áudios na Action `ResumoConversaIaAction` (`FileUpload`
  `audios`, disco `local`, pasta `temp-conversa-audios`, 10 MB cada); o texto só é obrigatório sem áudio.
  - Cada arquivo segue ao Gemini como `inline_data` (base64), precedido do rótulo "Áudio N de M" e na ordem
    informada; o fechamento do prompt vem depois das mídias. O MIME é detectado no **conteúdo** do arquivo
    (`mime_content_type`) e só então o informado pelo navegador vale, traduzido por `MIMES_AUDIO_GEMINI` para os
    formatos que a API lista (WAV, MP3, AIFF, AAC, OGG, FLAC). Voz do WhatsApp é Opus em OGG → `audio/ogg`. M4A não
    consta na lista oficial e segue como `audio/aac` (melhor esforço, ainda não validado com arquivo real).
  - Limites validados **antes** de chamar a IA (`InvalidArgumentException`, mostrada como notificação): máx. 5
    arquivos, arquivo existente e não vazio, formato suportado e `BYTES_MAXIMOS_AUDIOS` (14 MB somados). A API limita a
    requisição inline a 20 MB e o base64 incha ~33%; acima disso seria preciso usar a Files API.
  - A fala também é **dado não confiável**: o prompt manda ignorar instruções ditas nos áudios. Sem texto colado, o
    bloco `<conversa>` não é enviado (só um aviso de que a conversa está nos áudios).
  - `GeminiAgentService::callGeminiApi($payload, $timeout = 45)`: com áudio o resumo usa 120 s por tentativa; sem
    áudio nada muda. Os arquivos temporários são apagados num `finally`, mesmo com erro.
  - **Falha da IA = contingência, não síntese:** o serviço devolve a resposta de contingência (`Fallback`, que cita
    "N áudio(s) anexado(s)" em vez de um trecho vazio) com `fallback = true`, como já faz o dossiê. A Action
    **não grava** nada na linha do tempo, **não altera** temperatura nem próximo contato e mostra uma notificação de
    erro ("Não foi possível resumir a conversa"). Antes, o texto de contingência era gravado como se fosse a síntese
    e a notificação dizia "Conversa resumida com sucesso!". Como os áudios já foram apagados, o consultor precisa
    anexá-los de novo ao tentar outra vez. Quem consumir `resumirConversaWhatsapp()` deve checar `fallback`.
  - O servidor precisa comportar o upload (`upload_max_filesize`/`post_max_size` ≥ 10 MB; o Livewire limita o
    temporário a 12 MB) e a análise mais demorada (`max_execution_time`).
  - Testes: `tests/Feature/ResumoConversaAudioTest.php` (WAV mínimo gerado em memória, Gemini simulado com Mockery).
- Testes que passam por IA devem usar `Queue::fake()`/`Http::fake()` e `Http::preventStrayRequests()`: o `.env`
  local tem a chave real e o `phpunit.xml` não a sobrescreve.

## 8. Portal de documentos do candidato

Link público por token (`/admissao/{token}`): `Interessado::comTokenDocumentosValido()` — o link **expira**
(`token_documentos_expira_em`, **7 dias** — `Interessado::DIAS_VALIDADE_TOKEN_DOCUMENTOS` —, renovados toda vez que a
equipe gera/copia/envia o link; a família abrir o link não renova. Token sem validade não é aceito; link vencido **não
é revivido** — a equipe recebe um token novo; link vencido, revogado ou inexistente responde 410 com a mesma tela, sem
revelar se existiu). **Revogação:** `LinkPortalAdmissaoService::gerarNovo()` ("Gerar novo link") troca o token do
portal e zera o convite legado, e a URL antiga passa a responder 410 na hora (registro automático na linha do tempo).
O convite legado `/quero-matricular/convite/{token}` só redireciona dentro da própria validade de 7 dias e sem
renovar o portal; o portal nunca aceita o token do convite diretamente. A migração
`2026_10_08_090000_limita_validade_dos_links_do_portal_a_sete_dias` reduz para 7 dias os links antigos de 90. O dependente enviado precisa pertencer ao
candidato; documento **verificado** não é substituído nem removido pela família; o arquivo antigo é apagado e
o parecer da IA zerado na substituição; documentos já migrados para uma matrícula não são tocados; limite de
30 envios/hora por candidato (cada arquivo gera uma análise paga por IA) além do `throttle` por IP; a página
sai com `noindex` e `no-referrer`. O token segue em texto puro de propósito: a equipe precisa reexibir o
mesmo link (guardá-lo com hash obrigaria a gerar outro a cada consulta).

## 9. Configuração (`config/crm.php`)

| Chave | Padrão |
|---|---|
| `alertas.intervalo_dias` (`CRM_ALERTA_INTERVALO_DIAS`) | 3 |
| `alertas.escalonar_apos_dias` (`CRM_ALERTA_ESCALONAR_APOS_DIAS`) | 7 |
| `alertas.sem_consultor_apos_horas` (`CRM_ALERTA_SEM_CONSULTOR_HORAS`) | 24 |
| `captacao.agradecimento_janela_horas` | 24 |
| `kanban.cards_por_coluna` (`CRM_KANBAN_CARDS_POR_COLUNA`) | 30 |
| `kanban.cards_maximo_por_coluna` | 300 |
| `kanban.dias_finalizados` (`CRM_KANBAN_DIAS_FINALIZADOS`) | 90 |
| `contadores.cache_segundos` (`CRM_CONTADORES_CACHE_SEGUNDOS`) | 60 |
| `calendario.janela_passado_dias` / `janela_futuro_dias` | 90 / 180 |
| `fila_ia` (`CRM_FILA_IA`) | `ia` |
| `regua.janela_recuperacao_dias` (`CRM_REGUA_JANELA_RECUPERACAO_DIAS`) | 2 |
| `regua.max_emails_por_lead_dia` (`CRM_REGUA_MAX_EMAILS_POR_LEAD_DIA`, 0 = sem limite) | 2 |
| `regua.max_tentativas_falha` | 3 |
| `lgpd.versao_consentimento` | `2026-10` |
| `lgpd.url_politica_privacidade` (`CRM_URL_POLITICA_PRIVACIDADE`) / `lgpd.contato_privacidade` (`CRM_CONTATO_PRIVACIDADE`) | vazios |
| `lgpd.retencao_rascunho_dias` (`CRM_RETENCAO_RASCUNHO_DIAS`) | 90 |
| `permissao_consultor` | `Update:Interessado` |

Gemini (`config/services.php`): `gemini.orcamento_segundos` (`GEMINI_ORCAMENTO_SEGUNDOS`, 60) para chamadas
feitas dentro de uma tela e `gemini.orcamento_documento_segundos` (`GEMINI_ORCAMENTO_DOCUMENTO_SEGUNDOS`, 70)
para a análise de documentos.

## 10. Desempenho e escala (Lote C)

Medidas para o CRM continuar rápido com milhares de leads. Nenhuma muda regra de negócio.

- **Kanban** (`KanbanInteressados::colunas()`): antes cada coluna carregava *todos* os leads, com 9 relações,
  a cada render (matriculados e perdidos de anos atrás incluídos). Agora: um agregado traz total e valor de
  todas as colunas; cada coluna busca só os ids dos primeiros `kanban.cards_por_coluna` cards (mais urgentes
  primeiro: `data_proximo_contato` crescente, sem data por último); uma única consulta carrega esses leads com
  as relações do card. O número de consultas **não depende** da quantidade de cards
  (`KanbanDesempenhoTest::test_numero_de_consultas_nao_depende_da_quantidade_de_cards`, com
  `ProibeLazyLoading`). O botão **Carregar mais** soma um lote até `kanban.cards_maximo_por_coluna`; acima
  disso, use a listagem com filtros. O total do cabeçalho da coluna continua sendo o real.
- **Colunas finais** (matriculado/perdido) mostram só leads **movidos nos últimos `dias_finalizados` dias**
  (`updated_at`); o restante segue na aba *Finalizados* da listagem. Etapas ativas não têm janela.
- **Contadores em cache** (`ContadoresCrm`): as 6 abas da listagem (11 `count()`, vários com `whereHas`
  aninhado) e o selo "Novo" do menu (uma consulta em *toda* página do painel) ficam em cache por
  `contadores.cache_segundos`. `ContadoresCrm::invalidar()` troca um token de versão (as chaves antigas
  simplesmente deixam de ser lidas) e é chamado quando `Interessado`, `HistoricoContato` ou `StatusInteressado`
  é salvo/excluído. Limites conhecidos: escrita direta no banco (`DB::table`, como o recálculo de score em
  lote) não invalida — vale o TTL; a chave inclui o id do usuário porque o escopo da listagem depende dele.
- **Busca** (`Pessoa::scopeBusca`): nome, e-mail, telefone **com ou sem máscara** (a comparação ignora
  `( ) - + e espaço`) e CPF (gravado só com dígitos). Menos de 3 dígitos não entram na busca de telefone/CPF
  (casariam com quase tudo). Usada na busca da listagem e nos selects de pessoa/lead.
- **Selects sem `preload()`**: *Pessoa / Interessado* da ficha do lead e *Família Indicadora*/*Lead Indicado*
  do cadastro de indicação carregavam todas as pessoas/leads a cada abertura. Agora a busca vem do servidor
  (`getSearchResultsUsing`, no máximo 50 resultados) e o rótulo do valor atual é buscado por id.
- **Filtro "Estagnado = não"** usa `whereNot` com a mesma subconsulta em vez de `NOT IN (todos os ids
  estagnados)`, que carregava a lista inteira em memória. O resumo de vagas da série é calculado **uma vez por
  linha** (antes 3: texto, cor e dica).
- **Índices** (migration `2026_10_05_090000_add_indices_de_desempenho_ao_crm`): `interessado` por
  `data_proximo_contato`, `(status, data_proximo_contato)`, `(status, updated_at)`, `(usuario, status)`,
  `lead_score`, `data_conversao` e `created_at`; `pessoa.email`; `visita_interessado (interessado, status)`.
  Idempotente (só cria o que não existe, nome fixo) e reversível.
- **Score em lote** (`LeadScoreService::recalcularLote`): o comando diário (`crm:recalcular-lead-score`) e o
  botão *Recalcular todos* da configuração processam em lotes de 200 carregando as relações do lote todo
  (uma consulta por relação) e a etapa máxima uma vez, em vez de `refresh()` + consultas por lead. Sobra um
  `UPDATE` por lead. O resultado é o mesmo do `recalcular()` individual (`CrmEscalaTest`).
- **Calendário** (`CrmFollowUpCalendarWidget`): só follow-ups e visitas dentro de `calendario.janela_*`
  (hoje −90/+180 dias). Contato atrasado há mais que isso continua na aba *Precisa de contato*.
- **IA em fila própria**: `ValidarDocumentoComIaJob` roda na fila `crm.fila_ia` (`ia`) para não atrasar e-mails
  e notificações da fila padrão. `routes/console.php` agenda um worker dedicado
  (`queue:work --queue=ia --timeout=85 --max-time=55`, a cada minuto, em segundo plano e sem sobreposição).
  O `$timeout` do job (85 s) fica **abaixo do `retry_after` da conexão (90 s)** — se passasse, a fila entregaria
  o job a outro worker com o primeiro ainda ativo e o documento seria analisado em dobro — e acima do
  orçamento de tempo do Gemini para documentos (70 s). Esgotadas as 3 tentativas, `failed()` avisa o consultor
  do lead pelo sino ("Análise por IA indisponível") em vez de deixar o documento "Não analisado" sem explicação.
- **Orçamento de tempo do Gemini** (`GeminiAgentService::callGeminiApi($payload, $timeout, $orcamentoSegundos)`):
  a cascata de 5 modelos em 2 rodadas, 45 s cada, podia segurar uma requisição (ou um worker) por mais de 7
  minutos. Agora há um tempo total (`services.gemini.orcamento_*`): esgotado, não se inicia nova tentativa, e
  cada tentativa usa no máximo o tempo restante (mínimo de 5 s) e nunca mais que `$timeout` (45 s por padrão;
  o resumo de conversa com áudio passa 120). Sem orçamento explícito vale o da configuração, mas nunca menos que
  `$timeout`, para que a tentativa longa do áudio não seja cortada pelos 60 s das telas. A análise de
  documentos passa o seu orçamento (70 s) por parâmetro nomeado.

**Não feito de propósito:** a padronização da visibilidade por consultor (hoje cada tela aplica o seu recorte)
depende de decisão de negócio (quem enxerga o quê). Os itens 6, 10, 13 e 15 da auditoria foram fechados no Lote E
(seção 11).

## 11. Lote E: LGPD, régua, importação por IA e termômetro de vagas (itens 6, 10, 13 e 15)

### 11.1 Termômetro de vagas (item 15) — `TermometroVagasService`

- **Período letivo de captação:** a série considera só as turmas abertas do período que recebe os novos leads: o
  **próximo a começar** entre os que têm turma aberta; sem nenhum futuro, o em curso; se todos terminaram, o mais
  recente. Antes somava a turma cheia do ano em curso com a do próximo (as duas "Ativa") e a escassez calculada não
  existia para quem ia se matricular. Dentro do período, turmas `Planejada` ainda têm preferência sobre as demais,
  como antes. O resultado traz `periodo_letivo_id` e `periodo_letivo_nome`; o modal do termômetro mostra "turmas de X".
- **Capacidade estimada:** série sem turma, ou com turma sem `vagas_maximas`, usa o padrão de 25 vagas por turma. O
  número é suposição, então a linha vem com `capacidade_estimada = true` e **não gera escassez**: o selo do Kanban
  some, o filtro "Vagas na série" não a classifica e `obterStatusParaLead()` devolve "Capacidade não definida".
- **Prompt de IA** (`gerarPromptEscassez`, usado pelo Dossiê e pelo Copiloto): séries com capacidade estimada ficam
  de fora (sem número confiável, sem bloco); cada linha cita o período; "Restam apenas N" só aparece quando há de
  fato escassez (antes aparecia até com 25 vagas livres); o bloco traz a data da posição e manda **não inventar
  prazo, desconto nem outro número de vagas**. Sem escassez, diz expressamente para não usar argumento de urgência.
  O texto antigo mandava "USAR ISSO NA ABORDAGEM" e podia levar o modelo a pressionar a família com urgência falsa.
- Testes: `TermometroVagasTest`.

### 11.2 Importação de lead por IA (item 13) — `ImportacaoLeadIaService`

`ImportarLeadIaAction::salvarLeadExtraido()` virou uma chamada a este serviço (a assinatura foi mantida). O JSON da
IA é texto livre de terceiros e só vira cadastro depois de validado:

- **Série:** nome igual (sem caixa/acento) ou uma **única** série que contenha o termo como palavra inteira (`1º Ano`
  não casa com `11º Ano`). Ambígua ou inexistente → o aluno fica **sem série**, com aviso. Antes caía em `Serie::first()`.
- **Origem e tipo de contato:** só existentes. Origem desconhecida usa a escolhida pelo consultor (ou "WhatsApp/IA")
  com aviso; canal desconhecido usa "Outro" e é citado no relato. Os quatro canais que o prompt oferece (Ligação,
  WhatsApp, E-mail, Presencial) são criados sob demanda. Antes a IA criava origens e tipos novos a cada importação.
- **Dados:** CPF validado pelos dígitos verificadores; e-mail com `FILTER_VALIDATE_EMAIL`; nascimento aceita
  `AAAA-MM-DD` e `DD/MM/AAAA` e descarta futuro, anterior a 1950 ou ilegível; vínculo fora de Pai/Mãe/Parente/Tutor
  vira vazio (o valor `Filho(a)` antigo violaria o enum). Cada descarte vira aviso na notificação e nas observações.
- **Deduplicação:** a pessoa é reconhecida por e-mail, CPF ou telefone (`Pessoa::scopeComTelefone`: ignora máscara e
  o `55`). Se já tem **lead ativo**, a conversa entra no histórico dele, o aluno repetido só é completado, nada do que
  está preenchido é sobrescrito e não nasce segundo lead; lead encerrado ganha um lead novo.
- **Transação:** pessoa, lead, alunos e histórico entram juntos ou nenhum.
- **Prompt:** lista as origens e séries cadastradas para a IA devolver nomes existentes e manda tratar o conteúdo
  recebido como dado, ignorando instruções escritas nele (texto, texto legível nas imagens e **fala nos áudios**).
- Testes: `ImportacaoLeadIaTest`, `GeminiLeadExtractionTest`.

#### Conversa exportada do WhatsApp (.zip) — `ConversaWhatsappZipService`

A ação aceita o `.zip` de "Exportar conversa" do WhatsApp (Android: `Conversa do WhatsApp com …​.txt`; iPhone:
`_chat.txt`), com ou sem mídia, sozinho ou somado ao texto colado e ao print.

- **Leitura defensiva** (o arquivo é de terceiros): nenhum nome de entrada vira caminho em disco (as mídias são gravadas
  como `audio-01.opus`, `imagem-01.jpg`… em `temp-lead-zip-midias/<uuid>`, o que elimina zip-slip); cada leitura tem teto
  de bytes pelo que é de fato lido, não pelo tamanho declarado (zip bomb); o tipo da mídia vem do **conteúdo**
  (`mime_content_type`), não da extensão; `__MACOSX`, `._*`, vídeos, documentos e figurinhas são ignorados.
- **Texto:** UTF-8 sem BOM, sem as marcas de direção (U+200E…) do WhatsApp; acima de 120 mil caracteres vira começo + fim
  (com aviso). O cabeçalho com o nome do arquivo ajuda a IA a achar o contato do outro lado.
- **Mídias:** na ordem em que o nome do arquivo aparece na conversa (`PTT-…opus (arquivo anexado)` / `<anexo: …>`), até
  `MAX_AUDIOS` (10), `MAX_IMAGENS` (10) e `BYTES_MAXIMOS_MIDIAS` (14 MB, descontado o print, porque a requisição inteira ao
  Gemini é limitada a 20 MB com o base64). O que passa dos limites ou tem formato sem suporte vira aviso na notificação.
- **Gemini:** `GeminiAgentService::extrairLead(..., array $midias)` envia cada mídia rotulada com o nome higienizado do
  arquivo, depois do texto; com áudio o timeout por tentativa sobe para 120 s (como no resumo de conversa) e a ação pede
  `set_time_limit(180)`. O prompt passou a dizer que o atendente da escola nunca é o lead e a usar a data/hora da última
  mensagem como `data_contato` quando o texto for uma conversa do WhatsApp (vale também para texto colado).
- **LGPD/retenção:** `.zip`, áudios e imagens são apagados no `finally` da ação; só o que a IA extrai (relato, dados
  cadastrais) entra no lead, como já era com texto e print. O texto bruto da conversa **não** é gravado.
- **Upload:** o campo aceita até 30 MB (`ImportarLeadIaAction::ZIP_KB_MAXIMOS`). O gate de upload temporário do Livewire
  (`config/livewire.php`, antes no padrão de 12 MB) foi elevado para 30 MB; `upload_max_filesize` e `post_max_size` do
  PHP do servidor precisam ser ≥ 30 MB.
- Testes: `ImportacaoLeadWhatsappZipTest`.

### 11.3 Régua de follow-up (item 10) — `ReguaFollowUpService`

- **Envio de verdade:** o e-mail sai com `sendNow`. `MensagemGenericaMail` é `ShouldQueue` (serve à comunicação em
  massa) e o `send()` só enfileirava: o log dizia "sucesso" e o contato entrava no histórico mesmo se o SMTP
  recusasse depois. Agora falha de transporte vira `status_envio = falha` (com o erro) e **não** vira contato.
- **Janela de recuperação** (`regua.janela_recuperacao_dias`, 2): os gatilhos pós-evento (cadastro, visita
  realizada, visita com falta, contato atrasado) olham de `data-alvo − janela` até `data-alvo`; um dia sem agendador
  não perde mais a mensagem. O **lembrete de visita não tem janela**: "amanhã" no dia da visita seria errado. Textos
  da régua que dizem "ontem" saem errados quando o envio atrasa — prefira `{{DATA_VISITA}}`.
- **Idempotência** (`jaProcessada`): visita e cadastro uma vez só; `ContatoAtrasado` uma vez por **ciclo** (recomeça
  quando o consultor reagenda a data); `LeadEstagnado` pela janela do offset. Falha: uma tentativa por dia e no
  máximo 3 por evento, para e-mail inválido não ser reenviado a cada execução.
- **Só lead em andamento** nos gatilhos de visita (lembrete, agradecimento e reagendamento para quem já matriculou
  ou desistiu era ruído).
- **Horário de disparo:** o comando agendado passou a rodar **de hora em hora** e cada regra sai na primeira
  execução a partir do seu `horario_envio` (padrão 08:00; 08:30 sai às 09:00). `--ignorar-horario` e `--data=`
  processam tudo. O campo já existia e era ignorado.
- **Teto diário:** no máximo `regua.max_emails_por_lead_dia` (2) e-mails da régua por lead por dia; o excedente sai
  nos dias seguintes (dentro da janela). Não vale para avisos internos nem para o envio manual ("Testar"). A
  simulação (`--dry-run`) respeita o teto.
- **Menos consultas por mensagem:** nome da escola e tipo de contato "E-mail" são buscados uma vez por execução;
  o link da pesquisa de visita usa uma consulta só e o enum `StatusVisitaInteressado::Realizada` (a comparação com
  `'Realizada'` só funcionava no MySQL, que ignora caixa).
- **Descadastro:** todo e-mail da régua ganha rodapé com link e os cabeçalhos `List-Unsubscribe`/`List-Unsubscribe-Post`
  (ver 11.4).
- Testes: `ReguaFollowUpEntregaTest`, `ReguaFollowUpTest`.

### 11.4 LGPD (item 6)

- **Consentimento no formulário público** (`/quero-matricular`): caixa obrigatória no último passo
  (`consentimento` → `accepted`). Fica na pessoa: `consentimento_em`, `consentimento_versao`
  (`lgpd.versao_consentimento`), `consentimento_origem` (`formulario_captacao` ou `pre_matricula`) e
  `consentimento_ip` (`Pessoa::registrarConsentimento()`). O aviso mostra o link da Política e o canal do titular
  quando `CRM_URL_POLITICA_PRIVACIDADE`/`CRM_CONTATO_PRIVACIDADE` estão preenchidos — **o sistema não escreve a
  política nem indica o Encarregado: isso é da escola**. Limites: não há confirmação por e-mail (double opt-in) e o
  aceite fica registrado em nome da pessoa encontrada pelo e-mail digitado. O envio não religa quem pediu
  descadastro (o formulário é público e qualquer um pode digitar o e-mail de outra pessoa).
- **Descadastro** (`GET|POST /comunicacao/descadastrar/{pessoa}`, URL assinada **sem validade**): o `GET` só mostra
  a confirmação (scanners de e-mail abrem links); o `POST` — também usado pelo "cancelar com um clique" dos
  provedores, por isso fora do CSRF — põe `aceita_comunicacao = false` e `descadastrado_em`, e deixa um registro
  automático na linha do tempo de cada lead da pessoa. Vale para a régua e para a comunicação em massa.
- **Rascunho de pré-matrícula cifrado:** `interessado.dados_pre_matricula` (CPF, endereço, responsáveis e alunos) é
  gravado com `Crypt` pelo cast `ArrayCriptografado`, que lê também JSON puro (código novo com dado antigo não
  quebra a ficha). A coluna passou de `json` para `longText` e a migration cifra os rascunhos existentes.
  `dados_pre_matricula_em` guarda a última atualização. O log de atividades não registra esse campo.
- **Retenção:** `crm:expurgar-rascunhos-pre-matricula` (diário, 03:15) apaga o rascunho parado há mais de
  `lgpd.retencao_rascunho_dias` (90) de lead **não convertido e sem interação** (humana ou da família) nesse
  período; registros automáticos (régua, IA) não contam como atividade. O aceite continua na pessoa. `--dry-run`
  mostra quantos seriam apagados. A conversão em matrícula já apagava o rascunho (agora também a data).
- **Minimização na IA** (Dossiê e Copiloto, `CrmIaVendasService::montarContextoLead`): saem telefone, e-mail,
  sobrenome do responsável e dos alunos (só o primeiro nome e a idade seguem); CPF, telefone e e-mail que a equipe
  digitou em observações, relatos e visitas viram `[CPF omitido]`/`[telefone omitido]`/`[e-mail omitido]`
  (`ocultarDadosPessoais`, por padrão de texto — rede de segurança, não garantia). O resumo de conversa e a análise
  de documentos continuam enviando o conteúdo que existem para analisar (conversa/documento).
- **Texto honesto sobre a IA:** a ajuda dos documentos dizia "endpoints corporativos efêmeros sem retenção para
  treino", mas o código usa a API pública do Gemini com chave. Agora diz que os dados vão ao Google e que, no plano
  gratuito do AI Studio, o conteúdo pode ser usado para melhorar produtos; os planos pagos têm outros termos.
  **Confirme o plano da chave configurada** antes de processar documentos de menores.
- **Fora do alcance do código:** conferir no servidor `APP_ENV=production` e `APP_DEBUG=false` (o `.env` desta
  estação tem `APP_DEBUG=true`); definir Política de Privacidade, Encarregado e canal do titular; contrato com o
  Google. Ver também `docs/seguranca_pendencias.md` (A4, A5, M9).
- Testes: `ConsentimentoLgpdCrmTest`, `DescadastroComunicacaoTest`, `RascunhoPreMatriculaCriptografadoTest`,
  `IaMinimizacaoDadosTest`.

## 12. Deploy e testes

Migrations novas (rodam no "Git Pull" do painel, que executa `migrate --force`): ver tabela em
`docs/crm_captacao_campanhas_visitas.md`, seção 7. A de índices do Lote C é segura para repetir. As do Lote E
(`2026_10_09_100000_add_consentimento_e_descadastro_to_pessoa_table` e
`2026_10_09_100100_criptografar_dados_pre_matricula_do_interessado`) também; a segunda **cifra com a `APP_KEY`
atual**: trocar a chave depois disso torna os rascunhos ilegíveis (o cast devolve vazio), então não gire a
`APP_KEY` sem reexportar os rascunhos.

**Lote E: o agendador precisa rodar a cada minuto** (como já era para a fila): a régua agora é horária. Quem
agendava a régua só às 08:00 por cron próprio precisa trocar para `schedule:run`.

**Lote C exige o worker da fila `ia`.** O agendador já o inicia (`schedule:run` a cada minuto, como o worker
da fila padrão); em produção com Supervisor/serviço próprio, rode também `php artisan queue:work --queue=ia
--tries=3 --timeout=85` e **não** reduza o `retry_after` da conexão abaixo de 90. O botão *Processar Fila Agora* do painel
(`QueueSupervisorWidget`) esvazia as duas filas (`default,ia`). Jobs de análise que já
estavam na fila padrão no momento do deploy terminam normalmente pelo worker antigo. Sem worker na fila `ia`
os documentos enviados ficam "Não analisados" até alguém processá-la.

As demais variáveis de ambiente são opcionais.

Testes: `LeadFunilServiceTest`, `FunilAcoesInteressadosTest`, `CaptacaoReenvioTest`, `CaptacaoIndicacaoTest`,
`CaptacaoRecaptchaTest`, `MatriculaOnlineConversaoCrmTest`, `AlertaLeadsTest`, `ConsultoresCrmTest`,
`InteracoesAutomaticasTest`, `KanbanInteressadosAutorizacaoTest`, `CrmIaSegurancaTest`, `CrmIaDossieCacheTest`,
`CrmIaVendasTest`, `PortalDocumentosCandidatoSegurancaTest`, `ResumoConversaAudioTest`; Lote C:
`KanbanDesempenhoTest`, `ContadoresCrmTest`, `CrmEscalaTest`, `FilaIaDocumentoTest`; Lote E: `TermometroVagasTest`,
`ImportacaoLeadIaTest`, `ReguaFollowUpEntregaTest`, `ConsentimentoLgpdCrmTest`, `DescadastroComunicacaoTest`,
`RascunhoPreMatriculaCriptografadoTest`, `IaMinimizacaoDadosTest`.

## 13. Fora do escopo destes lotes

Distribuição automática de leads, WhatsApp como canal da régua, funil analítico e demais upgrades (Lote D);
padronização da visibilidade por consultor; Política de Privacidade, Encarregado (DPO) e RIPD (a escola define);
confirmação do consentimento por e-mail (double opt-in); criptografia de outros dados pessoais do CRM
(`observacoes`, telefone e e-mail da pessoa); minimização dos dados enviados ao Gemini no resumo de conversa e na
análise de documentos (o conteúdo a analisar tem de ir).

## 14. Lote D1: Histórico de Etapas do Funil e Funil Analítico

O Lote D1 implementa rastreabilidade completa das mudanças de etapa de cada lead, alimentando relatórios gerenciais e métricas de desempenho comercial.

### 14.1 Tabela `interessado_status_historico`
- **Campos:** `interessado_id`, `status_anterior_id` (nullable), `status_novo_id`, `usuario_id` (nullable), `motivo_perda` (nullable), `data_transicao`, `estimada` (boolean).
- **Ponto único de gravação:**
  - `LeadFunilService::moverParaEtapaAtiva()`, `marcarComoPerdido()` e `marcarMatriculado()` gravam a transição com o usuário e motivo correspondente.
  - O model `Interessado` possui o observer `#[ObservedBy(InteressadoObserver::class)]`: quando o status é criado ou atualizado diretamente fora do serviço (formulário público, importação com IA, edições diretas ou matrícula online), o observer delega a gravação a `LeadFunilService::registrarTransicaoViaObserver()`.
  - A flag `$lead->transicaoRegistradaPorServico` impede qualquer duplicidade de linha no Kanban, tabela ou lote.
- **Backfill idempotente:** Leads antigos existentes recebem uma linha inicial correspondente à etapa atual e `created_at`, marcada com `estimada = true`. A execução subsequente não duplica linhas.

### 14.2 `FunilAnaliticoService`
Serviço analítico que fornece:
- `tempoMedioPorEtapa(array $filtros)`: calcula o tempo médio em dias e horas que os leads passam em cada etapa do funil antes de avançar.
- `conversaoEtapaAEtapa(array $filtros)`: apura a taxa de conversão etapa por etapa e a taxa de perda/descarte entre etapas ativas consecutivas.
- `conversaoPorDimensao(string $dimensao, array $filtros)`: calcula volume, ganhos, perdas e percentual de conversão agrupados por consultor, canal de origem ou campanha de marketing.

Testes: `tests/Feature/FunilHistoricoTransicaoTest.php`.
## 15. Lote D2: Relatórios Gerenciais, Previsão de Receita e Alertas de Detratores

O Lote D2 implementa a tela executiva de inteligência e relatórios do CRM (`/admin/crm/relatorios`), alertas imediatos de insatisfação em visitas e previsões financeiras de captação.

### 15.1 Relatórios e Inteligência Comercial
- **Página Filament:** `App\Filament\Pages\CrmRelatoriosPage` com agrupamento no grupo `CRM`, filtros reativos por período (Mês Atual, 30 Dias, 90 Dias, Ano Atual e datas manuais) e filtro por consultor.
- **Ajuda Contextual:** Header Action `ajuda` com conformidade ao Filament Shield (`getHelpContent()`), listando apenas as ações permitidas ao perfil logado.
- **Motivos de Perda e Concorrência:** Agrupamento por motivo base (`LeadFunilService::motivoBase`) e tabela com **coluna própria para Colégios Concorrentes**, listando volume de perdas e fator decisivo principal informado pela família.
- **Desempenho por Consultor:** Painel com tempo médio de primeira resposta (SLA), volume de leads atribuídos, contatos humanos registrados (`automatico = false`), visitas realizadas e percentual de conversão.
- **Previsão de Receita Ponderada:** Calculada sobre a carteira ativa utilizando o `valor_estimado` do lead (com fallback para `config('crm.previsao_receita.ticket_medio_padrao')`), multiplicada pela probabilidade configurada por etapa (`config('crm.previsao_receita.probabilidades_etapa')`) e comparada com a conversão histórica real apurada pelo `FunilAnaliticoService`.

### 15.2 Alertas de Detrator NPS em Visitas
- Coluna `visita_pesquisa_satisfacao.alerta_detrator_enviado_em`.
- Ao receber avaliação com NPS < 7 (Detrator), o sistema dispara notificação imediata no sino do painel para o **consultor responsável pelo lead** e para os **gestores** (`admin` e `super_admin`), permitindo intervenção rápida com a família.
- O alerta é disparado **uma única vez por pesquisa**, garantindo que reavaliações ou consultas não reenviem avisos repetidos.

Testes: `tests/Feature/CrmRelatoriosAlertasTest.php`.

## 16. Lote D3: Detecção de Duplicados e Mesclagem Segura

O Lote D3 implementa a inteligência de identificação de duplicidades e uma rotina de mesclagem atômica que consolida cadastros sem jamais perder informações de visitas, documentos, dependentes ou histórico.

### 16.1 Detector de Duplicados (`LeadDuplicadoDetectorService`)
- Avalia 4 critérios independentes com tolerância a formatações e acentuação:
  1. **Telefone Normalizado:** Compara os últimos 10 ou 11 dígitos contra outras pessoas com lead cadastrado;
  2. **CPF Numérico:** Compara os 11 dígitos numéricos do CPF;
  3. **E-mail Normalizado:** Compara e-mails em caixa baixa e sem espaços (`TRIM(LOWER(email))`);
  4. **Aluno Dependente em Comum:** Cruza `InteressadoDependente::nomeNormalizado()` e `data_nascimento` com dependentes de outros leads.
- Retorna lista estruturada de duplicados com os motivos e descrições humanas para exibição visual.
- Aviso em destaque na ficha do lead via componente Blade (`filament.crm.aviso-lead-duplicado`) integrado ao `InteressadoForm`.

### 16.2 Serviço de Mesclagem (`LeadMesclagemService`)
- **Regra de Vencedor:** Preserva o lead mais antigo por padrão (`created_at`), ou respeita a preferência explícita informada pelo usuário.
- **Transação Atômica (`DB::transaction`):** Qualquer inconsistência reverte integralmente todas as alterações.
- **Unificação de Dependentes:** Dependentes com o mesmo nome normalizado são unificados no destino; campos nulos (nascimento, série, unidade, turno) são completados e todas as visitas e documentos vinculados são redirecionados para o dependente unificado antes da exclusão do duplicado da origem.
- **Integridade Absoluta:** Move integralmente visitas (`visita_interessado`), pesquisas de satisfação (`visita_pesquisa_satisfacao`), documentos periciados (`documento_inserido`), históricos de contato (`historico_contato`), transições de status (`interessado_status_historico`), indicações (`indicacao_interessados`) e propostas comerciais.
- **Tokens e Pré-Matrícula:** Preserva tokens de convite e portal de documentos válidos, além de mesclar rascunhos de pré-matrícula criptografados.
- **Resolução de Conflitos:** Completa campos nulos no lead de destino e concatena observações de forma cronológica com identificação do lead absorvido.
- **Auditoria e Linha do Tempo:** Registra a mesclagem no `activity_log` e adiciona um evento na linha do tempo do lead consolidado.
- **Proteção e UI:** Ação "Mesclar Duplicados" disponível no cabeçalho de `EditInteressado` e na tabela de interessados (`InteressadosTable`), protegida pela permissão Shield `Update:Interessado`.

Testes: `tests/Feature/LeadMesclagemDuplicadosTest.php`.