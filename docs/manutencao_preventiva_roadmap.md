# Manutenção Preventiva (Onda 39)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda da tela **Manutenção Preventiva** (grupo
> **Operacional** do menu) e na seção 65 do `MANUAL_USUARIO.md`.

## Contexto

Bens e espaços da escola precisam de manutenção periódica (ar-condicionado, extintores, caixa
d'água, elevador, brinquedos do parque). Sem controle, ela só acontece depois da quebra. O sistema
já tinha **Bens Patrimoniais** (Onda 16) e **Ordens de Serviço**, mas não a recorrência.

## O que já existia

- `BemPatrimonial` (patrimônio) e `Sala` (espaços).
- `OrdemServico` com `CategoriaOs`, prioridade e status.
- Agendador (`routes/console.php`).

## O que foi implementado

### Dados

- `planos_manutencao`: título, descrição, alvo (bem, sala ou ambos), `periodicidade_dias`,
  `proxima_execucao`, `ultima_execucao`, `antecedencia_dias` (quando a ordem é aberta),
  prioridade, categoria da OS, checklist (JSON) e ativo.
- `execucoes_manutencao`: uma por vencimento (único por `plano_manutencao_id` + `data_prevista`),
  com status (**prevista** = ordem aberta e ainda não feita, **concluída**, **cancelada**), ordem de
  serviço e resultado do checklist.

### Regras

- **Situação do plano:** em dia, **a vencer** (até 30 dias) ou **vencido**. O menu mostra a quantidade
  de vencidos.
- **Abertura automática:** `manutencao:gerar-ordens` (06:15) abre a OS dos planos que entraram na
  janela de antecedência. A unicidade por vencimento impede abrir duas ordens para a mesma data,
  mesmo se a primeira for cancelada (o plano fica vencido até alguém reprogramar).
- **Executar** registra a data e o checklist, conclui a OS e **avança** o próximo vencimento
  (data de execução + periodicidade). Concluir a OS pela tela de Ordens de Serviço também fecha a
  execução (`sincronizarComOrdensDeServico`).
- **Reprogramar** move o vencimento e cancela a execução prevista em aberto (a ordem de serviço não
  é cancelada: quem a abriu decide). Plano inativo não registra execução, data futura é recusada e
  bem baixado não gera ordem.

### Telas e permissões

- **Manutenção Preventiva**: lista com situação, ações *Gerar OS agora*, *Registrar execução* e
  *Reprogramar*; relation manager com o histórico de execuções.
- `ViewAny/View/Create/Update/Delete/DeleteAny/GerarOrdem/RegistrarExecucao:PlanoManutencao`.

## Limitações e próximos passos

- Periodicidade só em dias (sem "primeira segunda-feira do mês" nem por horas de uso).
- Sem custo e sem fornecedor por execução (depende da Onda 38, Compras e Cotações).
- Sem anexos de laudo/ART na execução.
- Sem alerta ao responsável por e-mail ou push (hoje: selo no menu e OS aberta).
