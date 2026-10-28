# SLA de 1ª resposta dos leads (retirado)

**Situação:** retirado em 28/10/2026. Este documento registra como a funcionalidade era, por que saiu, o que ficou no
sistema e como recuperá-la. Ela nasceu no Lote D4 e foi ampliada nos Lotes F4 e G5 (leads sem consultor) e na Onda 84
(Minha Mesa do consultor).

**Último commit com o código:** `89b7743b`. Para ler qualquer peça removida:
`git show 89b7743b:app/Services/LeadSlaService.php`.

## Por que saiu

A escola não usava o prazo e o selo "SLA 1ª Resposta" ocupava espaço na lista de Interessados e na ficha do lead. O
que o SLA dizia (quem ainda não recebeu o primeiro contato) continua visível por outros caminhos (ver "O que mudou").

## O que era

O prazo para o primeiro contato da escola com a família corria em **minutos comerciais**: só contavam os dias úteis e o
expediente da escola.

### Cálculo (`App\Services\LeadSlaService`)

- **Início:** `created_at` do lead. Cadastro fora do expediente (noite, fim de semana) só começava a correr no primeiro
  minuto útil seguinte. Exemplo: lead criado na sexta às 17h com 120 min vencia na segunda às 09h.
- **Prazo:** `interessado.sla_primeira_resposta_minutos` (por lead; ex.: 30 min para lead quente, 240 min para campanha
  geral) ou, quando nulo, `crm.sla.primeira_resposta_minutos_padrao` (120).
- **Expediente:** `crm.sla.horario_comercial` (`inicio` 08:00, `fim` 18:00, `dias_uteis` `[1,2,3,4,5]`). **Feriados não
  eram considerados.**
- **Atendido:** `data_primeiro_contato` preenchida. **Estourado:** sem essa data, fora de etapa final e com o agora
  maior que o limite.
- **`resumoSla()`** devolvia `respondido`, `estourado`, `no_prazo` (`bool|null`), `limite`, `prazo_minutos`, `texto`,
  `cor` e `tooltip`, em cinco estados avaliados nesta ordem:

| Estado | Condição | Texto do selo | Cor |
|---|---|---|---|
| Respondido no prazo | data do 1º contato `<=` limite (inclusive no instante exato) | `Respondido no prazo (dd/mm HH:mm)` | verde |
| Respondido com atraso | data do 1º contato `>` limite | `Respondido com atraso (dd/mm HH:mm)` | âmbar |
| Finalizado | sem resposta e etapa final (matriculado/perdido) | `Finalizado` | cinza |
| Estourado | sem resposta e agora `>` limite | `SLA Estourado há XhYYmin` (ou `há N min`) | vermelho |
| Em curso | sem resposta e dentro do prazo | `SLA: resta XhYYmin útil` (ou `resta N min útil`) | âmbar |

O veredito "no prazo" × "com atraso" não era gravado: era refeito a cada exibição com o limite atual. O tooltip trazia
`Limite comercial: dd/mm/aaaa HH:mm`, precedido de "Primeiro contato dentro/depois do prazo." nos leads respondidos.

### Alertas (`crm:verificar-sla-estourado`, a cada 15 min)

Comando `VerificarSlaPrimeiraRespostaCommand`, agendado em `routes/console.php` com
`everyFifteenMinutes()->withoutOverlapping(10)`. Só entravam leads sem 1º contato e fora de etapa final.

- **Lead com consultor:** notificação no sino do consultor (*SLA Estourado: [nome] sem 1ª resposta*) com o botão
  **Ver Lead**. Marcava `sla_estouro_notificado_em`; no máximo um aviso por lead.
- **Lead sem consultor** (formulário público, landing): **uma** notificação agregada por rodada aos usuários ativos com
  papel `coordenador`, `admin` ou `super_admin`, com a contagem, até 5 nomes e o botão **Ver Leads Sem Consultor**
  (`/admin/interessados?tableFilters[sem_consultor][value]=1`). Marcava `sla_gestao_notificado_em` (Lote F4).
- **Sem gestor ativo (Lote G5):** nada era marcado nem contado; o sistema registrava `Log::warning` e o lead ficava
  pendente para a rodada seguinte.
- Reabrir o SLA (por exemplo, excluir o único 1º contato) não repetia aviso já enviado.

### Telas

- **Lista de Interessados:** coluna `sla_status` ("SLA 1ª Resposta", selo, opcional no seletor de colunas, visível por
  padrão).
- **Ficha do lead:** campo "SLA 1ª Resposta (minutos úteis)" na seção "Prazo e redes sociais", com texto de apoio que
  mostrava o estado e o limite. A seção agora se chama **Redes sociais**.
- **Ajuda da página (`EditInteressado`):** item "SLA de 1ª Resposta Comercial".
- **Minha Mesa (Onda 84):** fila `PrimeiraRespostaEstourada` ("Primeira resposta fora do prazo", toda atrasada, só
  leads cadastrados nos últimos `crm.mesa.sla_janela_dias` = 30 dias, permissão `ViewAny:Interessado`).
- **Modelo `Interessado`:** métodos `limiteSla()` e `slaEstourado()`.

### Parâmetros

| Onde | Chave | Padrão |
|---|---|---|
| `config/crm.php` | `sla.primeira_resposta_minutos_padrao` (`CRM_SLA_PRIMEIRA_RESPOSTA_PADRAO`) | 120 |
| `config/crm.php` | `sla.horario_comercial.inicio` (`CRM_HORARIO_COMERCIAL_INICIO`) | `08:00` |
| `config/crm.php` | `sla.horario_comercial.fim` (`CRM_HORARIO_COMERCIAL_FIM`) | `18:00` |
| `config/crm.php` | `sla.horario_comercial.dias_uteis` | `[1,2,3,4,5]` |
| `config/crm.php` | `mesa.sla_janela_dias` (`CRM_MESA_SLA_JANELA_DIAS`) | 30 |

## O que mudou para quem usa

| Antes | Agora |
|---|---|
| Selo "SLA 1ª Resposta" na lista e na ficha | Sem selo; a coluna sai do seletor de colunas |
| Prazo personalizado por lead | Não existe mais |
| Alerta de estouro no sino (consultor e gestão) | Não existe mais |
| Fila "Primeira resposta fora do prazo" na Minha Mesa | A Mesa do consultor passou de seis para cinco filas |

Quem ainda cobre o lead novo sem atendimento: a fila **Sem próxima ação** da Mesa (depois da carência de
`crm.mesa.carencia_sem_proxima_acao_horas`, 2 h), a aba **Precisa de contato** da lista, e o resumo diário à gestão de
lead ativo sem consultor há mais de `crm.alertas.sem_consultor_apos_horas` (24 h), do comando `crm:notificar-pendentes`.
A diferença real: deixou de existir um aviso em minutos comerciais, no momento em que o prazo vence.

## O que ficou

- **`interessado.data_primeiro_contato`**: continua sendo gravada, em silêncio, pelo primeiro atendimento humano da
  escola à família. Nada na tela mostra mais o "respondido"/"estourado"; o dado alimenta o relatório **Desempenho por
  Consultor** (tempo médio até a 1ª resposta, `CrmRelatoriosService`) e a mesclagem de leads
  (`LeadMesclagemService`: vale a data mais antiga). As regras estão em
  `docs/crm_funil_e_captacao.md`, seção 17.
- **`php artisan crm:preencher-primeiro-contato`**: backfill da data, só simula sem `--aplicar`.
- **Colunas do banco sem uso, mantidas de propósito** (nenhuma migration foi criada, para não tocar na produção e não
  perder os prazos personalizados e as datas de aviso já registrados):
  `interessado.sla_primeira_resposta_minutos`, `interessado.sla_estouro_notificado_em`,
  `interessado.sla_gestao_notificado_em` e os índices `idx_interessado_sla_pendente` e `idx_interessado_sla_gestao`.
  Nenhum código as lê ou grava; saíram do `$fillable` e dos `casts` de `Interessado`. As migrations de origem
  (`2026_10_10_204700_add_sla_fields_to_interessado_table`,
  `2026_10_11_130000_add_sla_gestao_notificado_em_to_interessado_table`) continuam no repositório como histórico. Para
  removê-las no futuro, uma migration nova com `dropIndex` e `dropColumn` protegida por `Schema::hasColumn`.

## Como recuperar

1. Restaurar do commit `89b7743b`: `LeadSlaService`, `VerificarSlaPrimeiraRespostaCommand`, `PrimeiraRespostaEstourada`
   e os testes `CrmSla*`.
2. Recolocar o agendamento em `routes/console.php`, o bloco `sla` e `mesa.sla_janela_dias` em `config/crm.php`, a fila em
   `config/mesa_pendencias.php`, a coluna em `InteressadosTable`, o campo em `InteressadoForm`, os métodos em
   `Interessado` e as 3 colunas em `$fillable` e `casts`.
3. As colunas do banco já existem, com os dados antigos.

Quem quiser reaproveitar só a **conta de horário comercial** (por exemplo, o SLA de chamados proposto em
`docs/atendimento_unificado_roadmap.md`) encontra o algoritmo em `calcularDataLimite()` e
`ajustarParaProximoExpediente()` do commit acima.

## Arquivos removidos

- `app/Services/LeadSlaService.php`
- `app/Console/Commands/VerificarSlaPrimeiraRespostaCommand.php`
- `app/Services/Pendencias/Fontes/PrimeiraRespostaEstourada.php`
- Testes: `CrmSlaPrimeiraRespostaTest`, `CrmSlaGestaoSemConsultorLoteF4Test`, `CrmSlaGestaoSemDestinatarioLoteG5Test`,
  `CrmSlaRespostaNoPrazoOuComAtrasoTest`.
- Testes mantidos e renomeados (cobrem a manutenção de `data_primeiro_contato`, sem o serviço):
  `CrmSlaPrimeiroContatoPelaFichaTest` → `PrimeiroContatoPelaFichaTest`,
  `CrmSlaPrimeiroContatoReavaliacaoTest` → `PrimeiroContatoReavaliacaoTest`.
