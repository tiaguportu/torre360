# Alertas de Estagnação e Mensagens Rápidas de WhatsApp

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
  prazo desde a criação.
- `Interessado::diasSemInteracao()`: dias desde a última interação registrada
  (`ultimoHistorico()->data_contato`) ou desde a criação do lead, se nunca
  houve contato.
- `Interessado::estaEstagnado(int $dias = 7)`: atalho booleano usado na UI.

O comando busca primeiro os leads atrasados (`precisaContato()`) e, entre os
demais, os estagnados (`estagnados()`), evitando notificar o mesmo lead duas
vezes no mesmo disparo. Para cada lead com consultor responsável, ele:

1. Envia e-mail (`AcompanhamentoInteressadoNotification` para atraso,
   `LeadEstagnadoNotification` para estagnação).
2. Envia notificação para o sino do Filament
   (`Notification::make()->sendToDatabase($consultor)`), com botão "Ver Lead".
3. Registra um evento no activity log (`log name` `crm`).

> Notificações do sino do Filament (`Filament\Notifications\DatabaseNotification`)
> implementam `ShouldQueue` — elas só aparecem depois que a fila for
> processada (`queue:work`, já agendado a cada minuto em `routes/console.php`).

Na tabela de Interessados (`InteressadosTable`), há uma coluna "Sem Interação"
(dias desde a última interação, com destaque vermelho quando estagnado) e um
filtro "Estagnado (7+ dias sem interação)", ambos usando os mesmos
scopes/métodos do model — não há lógica duplicada entre backend e UI.

## 2. Mensagens rápidas de WhatsApp

Modelos de mensagem ficam na tabela `mensagem_whatsapp_template`
(`nome`, `conteudo`, `ativo`), gerenciáveis pelo Filament em
**CRM / Comercial → Modelos de WhatsApp**. O texto do modelo aceita três
variáveis, substituídas automaticamente no envio:

- `[Nome do Responsável]` → `interessado.pessoa.nome`
- `[Nome do Aluno]` → `nome_crianca` do dependente selecionado (ou o único
  dependente, se houver apenas um)
- `[Horário de Visita Agendada]` → `interessado.data_proximo_contato`
  formatado (`d/m/Y \à\s H:i`), ou "a definir" se não houver data marcada

A ação "WhatsApp" na tabela de Interessados (`InteressadosTable`, ao lado de
"Atendimento") abre um formulário para escolher o modelo (e o aluno, se o
lead tiver mais de um dependente cadastrado) e, ao confirmar, monta a URL
`https://wa.me/<telefone>?text=<mensagem>` e abre em nova aba — não há
integração com API de WhatsApp Business, é o mesmo padrão de link `wa.me` já
usado no restante do sistema (landing page, formulário de captação). O
telefone é normalizado (somente dígitos) e recebe o DDI `55` quando tem 11
dígitos ou menos (DDD + número).

A ação só aparece para leads com telefone cadastrado
(`filled($record->pessoa?->telefone)`).
