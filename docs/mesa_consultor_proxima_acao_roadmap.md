# Mesa do Consultor e Próxima Ação Obrigatória (Onda 84)

> **Status: implementada** (2026-10-27). Estende a Minha Mesa de Pendências (Onda 71) com as filas do CRM e torna a próxima ação
> do lead obrigatória em cada atendimento.
>
> **Atualização (2026-10-28):** o SLA de 1ª resposta e a fila *Primeira resposta fora do prazo* foram **retirados** (registro em
> [crm_sla_primeira_resposta_retirado.md](crm_sla_primeira_resposta_retirado.md)). A Mesa tem **cinco** filas do CRM; as
> menções ao SLA abaixo descrevem o que existia na época da entrega.

## Contexto

O consultor do CRM trabalha com a lista de Interessados: abas (*Precisa de contato*, *Quentes*, *Estagnados*, *Sem consultor*), selo de SLA (retirado) e
um alerta por e-mail. A pergunta do dia — "para quem eu ligo agora?" — exigia abrir três abas e lembrar. Pior: o atendimento podia ser
registrado **sem dizer o que fazer depois**, e o lead sem data de próximo contato simplesmente sumia dos alertas (`data_proximo_contato`
vazia não entra em *Precisa de contato*).

## O que já existia

- `Interessado` com `data_proximo_contato` (quando), as abas e escopos `ativos`, `precisaContato`, `estagnados`, `naoAtribuidos` e `visivelPara`.
- `LeadSlaService` (SLA de primeira resposta em horário comercial) e o comando `crm:verificar-sla-estourado` (retirados em 2026-10-28).
- `VisitaInteressado` e a régua de visita (agradecimento e pesquisa de satisfação saem na "visita realizada").
- A Minha Mesa (Onda 71): contrato `FontePendencia`, registro em `config/mesa_pendencias.php`.

## O que foi implementado

### Próxima ação obrigatória

- Nova coluna **`interessado.proxima_acao`** (até 160 caracteres): *o quê*, ao lado do *quando* (`data_proximo_contato`). Migration `add_proxima_acao_to_interessado_table`.
- **Registrar atendimento** (botão da lista e aba *Histórico* do lead) pede a **próxima ação e a data**, a menos que o resultado encerre o ciclo (**Efetuou matrícula** ou **Sem interesse**): nesse caso os dois campos são limpos. Atendimento com resultado que segue sem próxima ação é **recusado**.
- Botão **Próxima ação** na lista para marcar ou mudar sem registrar atendimento (`Update:Interessado`); some nos leads em status final.
- **Registro administrativo** no histórico (tipos "Formulário do site" e "Movimentação no Funil", que o sistema grava por conta própria) **não pede nem altera** a próxima ação: não é conversa com a família.
- A lista mostra a próxima ação sob o nome do lead. Toda a regra está em `ProximaAcaoLeadService`.

### Seis filas na Minha Mesa (cinco desde 2026-10-28)

A visibilidade é a da lista de Interessados: o consultor vê a **própria carteira e os leads sem dono**; quem tem `ViewAll:Interessado` vê todos. Cada fila usa os mesmos escopos das abas da lista, para a Mesa nunca contradizer a tela onde o lead se resolve.

| Fila | O que lista | Atrasado quando |
|---|---|---|
| **Contatos do dia** | próximo contato marcado para hoje ou já vencido | já passou da hora |
| **Visitas de hoje e sem resultado** | visitas agendadas para hoje ou passadas sem resultado | o horário passou há mais de 4 h sem resultado |
| ~~**Primeira resposta fora do prazo**~~ (retirada) | SLA de 1ª resposta estourado (horário comercial, últimos 30 dias) | sempre |
| **Leads sem próxima ação** | lead em andamento sem próximo contato (com carência para o recém-cadastrado) | sem próxima ação há mais de 2 dias |
| **Leads quentes sem contato** | temperatura quente ou score alto, sem interação humana há 3 dias | o dobro do prazo |
| **Leads sem consultor** (`Update:Interessado`) | lead em andamento sem responsável | passadas as horas do alerta de gestão |

### Parâmetros (`config/crm.php` → `mesa`)

| Parâmetro | Padrão | Variável de ambiente |
|---|---|---|
| Carência do lead recém-cadastrado em *Sem próxima ação* (h) | 2 | `CRM_MESA_CARENCIA_HORAS` |
| *Sem próxima ação* atrasado após (dias) | 2 | `CRM_MESA_SEM_PROXIMA_ACAO_DIAS` |
| Visita sem resultado atrasada após (h) | 4 | `CRM_MESA_VISITA_SEM_RESULTADO_HORAS` |
| Lead quente parado: entra após (dias; o dobro é atrasado) | 3 | `CRM_MESA_QUENTE_PARADO_DIAS` |
| ~~Janela de cadastro da fila de SLA (dias)~~ (retirado) | 30 | `CRM_MESA_SLA_JANELA_DIAS` |

### Permissões

Nenhuma nova: cada fila usa a permissão do módulo (`ViewAny:Interessado`; *Leads sem consultor*, `Update:Interessado`).

### Testes

`tests/Feature/MesaDoConsultorTest.php` (18) e ajustes em `AcoesCrmAutorizacaoTest` e `FunilAcoesInteressadosTest`, que registravam atendimento sem próxima ação.

## Comportamentos que mudaram

- Quem registrava atendimento com resultado "Retornar depois", "Agendou visita" ou "Outro" e **deixava a próxima ação em branco** agora é barrado. Lead que já existia sem próxima ação aparece na fila *Leads sem próxima ação* depois da carência.
- Resultado **Efetuou matrícula** ou **Sem interesse** limpa a próxima ação e a data do lead.

## Fora desta entrega

Distribuição automática de leads por rodízio ou carga (proposta na [Onda 85](distribuicao_leads_carteira_roadmap.md)), prioridade calculada dentro de cada fila (cada uma tem a sua ordem natural: horário, atraso ou score) e o resumo do dia por WhatsApp ao consultor (depende da [Onda 88](whatsapp_oficial_roadmap.md)). A sequência do CRM continua no [índice das ondas 80 a 88](ondas_80_a_88_indice.md).
