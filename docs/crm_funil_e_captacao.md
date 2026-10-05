# Funil, captação pública e segurança do CRM

Resultado dos Lotes A e B da revisão do módulo de CRM. Complementa
`docs/crm_captacao_campanhas_visitas.md`, `docs/crm_followup_whatsapp.md` e `docs/crm_lead_score.md`.

| Peça | Onde |
|---|---|
| Regras de movimentação do lead no funil | `App\Services\LeadFunilService` |
| Cadastro pelo formulário público (pessoa, lead, dependentes, reenvio) | `App\Services\CaptacaoInteressadoService` |
| "Família Indica Família" no formulário público | `App\Services\IndicacaoCaptacaoService` |
| Alertas diários, escalonamento e leads sem consultor | `App\Services\AlertaLeadsService` |
| Quem pode ser consultor / contas ativas | `User::consultoresCrm()`, `User::ativos()` |
| Etapas e tipos de contato do sistema | `StatusInteressado::inicial()/ganho()/perdido()`, `TipoContatoInteressado::porNome()` |
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
- Testes que passam por IA devem usar `Queue::fake()`/`Http::fake()` e `Http::preventStrayRequests()`: o `.env`
  local tem a chave real e o `phpunit.xml` não a sobrescreve.

## 8. Portal de documentos do candidato

Link público por token (`/admissao/{token}`): `Interessado::comTokenDocumentosValido()` — o link **expira**
(`token_documentos_expira_em`, 90 dias, renovados toda vez que a equipe gera/copia o link; link vencido ou
inexistente responde 410 com a mesma tela, sem revelar se existiu). O dependente enviado precisa pertencer ao
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
| `permissao_consultor` | `Update:Interessado` |

## 10. Deploy e testes

Migrations novas (rodam no "Git Pull" do painel, que executa `migrate --force`): ver tabela em
`docs/crm_captacao_campanhas_visitas.md`, seção 7. Nada exige configuração manual; as variáveis de ambiente
acima são opcionais.

Testes: `LeadFunilServiceTest`, `FunilAcoesInteressadosTest`, `CaptacaoReenvioTest`, `CaptacaoIndicacaoTest`,
`CaptacaoRecaptchaTest`, `MatriculaOnlineConversaoCrmTest`, `AlertaLeadsTest`, `ConsultoresCrmTest`,
`InteracoesAutomaticasTest`, `KanbanInteressadosAutorizacaoTest`, `CrmIaSegurancaTest`, `CrmIaDossieCacheTest`,
`PortalDocumentosCandidatoSegurancaTest`.

## 11. Fora do escopo destes lotes

Desempenho do Kanban/listagem e índices (Lote C); distribuição automática de leads, WhatsApp como canal da
régua, funil analítico e demais upgrades (Lote D); LGPD de consentimento no formulário, hash/expurgo do
rascunho de pré-matrícula e minimização de dados enviados à IA; régua: janela de recuperação nos gatilhos por
data e teto de mensagens por lead; importação por IA (série padrão, taxonomia livre, deduplicação);
termômetro de vagas por período letivo.
