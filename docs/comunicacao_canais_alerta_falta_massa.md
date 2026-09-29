# Comunicação: Canais, Alerta de Falta e Disparo em Massa

Onda 3 do roadmap de funcionalidades (ver `docs/crm_lead_score.md`,
`docs/crm_followup_whatsapp.md` para o CRM já existente).

## 0. Escopo e decisão sobre o chat escola–família

O plano original desta onda incluía um chat escola–família. Ao investigar antes de
implementar, esse item já havia sido entregue por outro trabalho na `main`
(commit `feat: implementa comunicacao escolar com eventos rsvp e central de
atendimento`): a **Central de Atendimento** (`AtendimentoChamado`,
`AtendimentoMensagem`, `AtendimentoSetor`), com chamados por protocolo, mensagens
encadeadas e telas no portal (`/portal/atendimento`) e no admin. Por cobrir o mesmo
propósito, esta onda **não** duplica esse recurso; os itens abaixo são os três
restantes do plano.

## 1. Canais de mensagem (`App\Contracts\CanalMensagem`)

Abstração para enviar uma mensagem a uma `Pessoa` por um canal escolhido, sem o
remetente precisar saber os detalhes de cada provedor.

```php
interface CanalMensagem
{
    public function chave(): string;                              // 'email', 'fcm'
    public function rotulo(): string;                              // rótulo amigável
    public function disponivelPara(Pessoa $pessoa): bool;           // tem e-mail? tem token?
    public function enviar(Pessoa $pessoa, string $assunto, string $corpo): bool;
}
```

- **`App\Services\Canais\EmailCanal`**: envia via `App\Mail\MensagemGenericaMail`
  (assunto e corpo HTML livres) e registra em `EmailLog`, mesmo padrão já usado por
  `AgradecimentoInteresseMail`. Indisponível se a pessoa não tem e-mail.
- **`App\Services\Canais\FcmCanal`**: envia push (via `FcmService`) para **todos** os
  usuários de app vinculados à pessoa (`Pessoa::users`) que tiverem `fcm_token`.
  Indisponível se nenhum usuário vinculado tiver token.
- **`App\Services\CanalMensagemManager`**: registro central (`chave => classe`),
  com `resolver(string $chave)` e `opcoes()` (para popular `Select` em formulários).
  Adicionar WhatsApp/SMS no futuro é só implementar `CanalMensagem` e registrar aqui.

> `MensagemGenericaMail` implementa `ShouldQueue` (mesmo padrão dos demais Mailables
> do projeto), então `Mail::to(...)->send($mailable)` na verdade **enfileira** o
> envio. Em testes, use `Mail::assertQueued(...)`, não `assertSent(...)`.

Este contrato é a base para os canais de e-mail/push da régua de cobrança (Onda 6)
e do convite de matrícula online (Onda 7) — não são reimplementados a cada onda.

## 2. Alerta de falta ao responsável

Model: `App\Models\FrequenciaEscolar` — agora com `#[ObservedBy(FrequenciaFaltaObserver::class)]`.
Observer: `App\Observers\FrequenciaFaltaObserver`.
Notificação: `App\Notifications\FrequenciaAusenciaNotification` (mail + sino + push,
mesmo formato de `OcorrenciaRegistradaNotification`).

- Dispara em `created` e em `updated` quando `situacao` **passa a ser** `'ausente'`
  (`getOriginal('situacao') !== 'ausente'`). Marcar presença não notifica; manter
  a falta já registrada (sem mudança de valor) não reenvia.
- **Guarda de vigência:** não notifica se a data da aula (`cronograma_aula.data`)
  for anterior a `matricula.data_ativacao` ou igual/posterior a
  `matricula.data_desativacao` — evita alarme por lançamento retroativo de uma
  matrícula ainda não ativa, ou de um aluno já desligado.
- Destinatários: todos os usuários (`User`) vinculados aos responsáveis
  (`Pessoa::responsaveis` → `Pessoa::users`) do aluno da matrícula. Sem
  responsável com usuário vinculado, não há o que notificar.
- Reaproveita o padrão de `OcorrenciaEscolar` (que faz o mesmo via
  `enviarNotificacaoResponsaveis()` no próprio model); aqui a lógica fica no
  Observer porque a notificação é ligada à mudança do campo, não a uma ação
  explícita da secretaria.

## 3. Disparo em massa por e-mail (CRM)

Model: `App\Models\ComunicacaoEmMassa` (tabela `comunicacao_em_massa`).
Enums: `App\Enums\TipoPublicoComunicacao`, `App\Enums\StatusComunicacaoEmMassa`.
Serviço: `App\Services\ComunicacaoEmMassaService`.
Job: `App\Jobs\EnviarComunicacaoEmMassaJob`.
Resource: `App\Filament\Resources\ComunicacaoEmMassas\ComunicacaoEmMassaResource`
(menu **CRM / Comercial → Comunicação em Massa**).

### Dois pontos de entrada

1. **Ação em lote na tabela de Interessados** (`InteressadosTable`, botão **Enviar
   Comunicação por E-mail**): seleciona leads específicos, pede assunto e corpo,
   cria a `ComunicacaoEmMassa` com `tipo_publico = interessados` e
   `filtros.interessado_ids` = ids selecionados, e despacha o job na hora.
2. **Tela de segmentação** (Resource): cria um rascunho escolhendo o público:
   - `interessados`: filtra por `status_interessado_ids` e/ou
     `origem_interessado_ids` (pelo menos um dos dois é exigido pelo formulário e
     pelo serviço — sem filtro nem seleção explícita, `destinatarios()` devolve
     vazio em vez de "todos os leads").
   - `responsaveis_turma`: `filtros.turma_ids` — responsáveis dos alunos com
     `Matricula.situacao = ativa` nessas turmas (via pivô `aluno_responsavel`).
   - A lista mostra a contagem de destinatários (calculada ao vivo enquanto o
     status é `rascunho`) e o botão **Enviar** confirma mostrando quantas pessoas
     vão receber antes do envio.

### Regras de segmentação e envio (`ComunicacaoEmMassaService` / `EnviarComunicacaoEmMassaJob`)

- `destinatarios()` sempre exclui quem não tem e-mail e quem tem
  `Pessoa.aceita_comunicacao = false` (opt-out LGPD, ver seção 4).
- O job só roda se `podeSerEnviada()` (status `rascunho` ou `falhou`); uma
  comunicação `concluida` não é reenviada mesmo se o job for despachado de novo.
- `[Nome]` no assunto e no corpo é substituído pelo primeiro nome de cada
  destinatário (mesmo padrão de variáveis dos modelos de WhatsApp do CRM).
- Ao terminar, grava `total_destinatarios`/`total_enviados`/`total_falhas`,
  `enviado_em`, muda o status para `concluida` e avisa quem criou o envio pelo
  sino do Filament. Falhas de envio individuais não interrompem o lote nem geram
  status `falhou` — esse status é reservado para uma exceção não tratada durante
  o processamento (`Job::failed()`).
- Permissão dedicada `Enviar:ComunicacaoEmMassa` (além das de
  ViewAny/View/Create/Update/Delete/DeleteAny), concedida por padrão a
  `super_admin`, `admin` e `secretaria`.

## 4. Opt-out de comunicação (LGPD)

Coluna `pessoa.aceita_comunicacao` (boolean, padrão `true`), com toggle **"Aceita
receber comunicações da escola"** no formulário de Pessoa (`PessoaForm`). Quando
desativado, a pessoa é excluída de qualquer `ComunicacaoEmMassa` (segmentada ou
seleção explícita), mas continua recebendo as notificações individuais obrigatórias
do sistema (boletim, ocorrências, documentos pendentes, alerta de falta etc.), que
não passam por `CanalMensagemManager`.

## 5. Migrations

| Migration | O que faz |
|---|---|
| `add_aceita_comunicacao_to_pessoa_table` | Coluna `aceita_comunicacao` em `pessoa`. |
| `create_comunicacao_em_massa_table` | Tabela `comunicacao_em_massa`. |
| `create_comunicacao_em_massa_permissions` | Permissões (incluindo `Enviar`) para super_admin/admin/secretaria. |

## 6. Testes

`CanalMensagemTest`, `FrequenciaAusenciaNotificationTest`, `ComunicacaoEmMassaTest`
(segmentação + job), `ComunicacaoEmMassaResourceTest` e
`InteressadoComunicacaoBulkActionTest`.
