# Exportação para a Folha de Pagamento (Onda 97) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

A escola controla o ponto (Onda 41), apura a hora-aula do professor (Onda 50), registra férias e admissões, mas **cada mês alguém digita tudo isso de novo** na
folha do escritório contábil: faltas, atrasos, horas extras, banco de horas, aulas dadas, reposições. É trabalho repetido, sujeito a erro e fora do sistema.

> **O que esta onda não é.** Não calcula salário, INSS, IRRF, FGTS nem gera eventos do eSocial. Isso é do sistema de folha do escritório. A onda entrega os
> **eventos variáveis do mês, conferidos**, num arquivo que o escritório importa.

## O que já existe (conferido no código)

- **Ponto:** `PontoRegistro`, `PontoApuracaoService` (apuração diária com tolerância), `PontoTratamento` (ajustes com aprovação), banco de horas
  (`PontoBancoHorasLancamento`, `BancoHorasService`), espelho em PDF e a página *Espelho de Ponto*.
- **Hora-aula** (`ApuracaoHoraAula`, por pessoa e por mês): aulas previstas e dadas, reposições (Onda 49), horas-atividade e DSR (CLT, art. 320).
- **Contrato de trabalho** (`ContratoTrabalho`: vigência e salário), `Funcionario`, `PeriodoFerias` e as providências de admissão e desligamento (Onda 65).
- **Exportação contábil** (Onda 73, `ExportacaoContabilService`, página *Contabilidade*): o padrão de "gerar arquivo do período para o escritório" já existe.
  Os **leiautes por sistema** e a importação do plano do escritório são ampliações ainda propostas, então o reuso é do padrão, não de um motor de leiautes pronto.
- Fila *Providências do RH vencidas* na Minha Mesa. Não há conceito de competência fechada de RH.
- Nenhuma menção a arquivo de folha no código (a palavra aparece só em itens de checklist de admissão).

## Escopo proposto

### Competência de RH
- `CompetenciaFolha` (ano e mês, situação: aberta, em conferência, fechada), por unidade.
- **Conferência** antes de exportar: ponto com batida ímpar ou ajuste pendente, apuração de hora-aula sem aprovar, banco de horas a expirar, funcionário sem
  contrato vigente, admissão ou desligamento no mês sem providência concluída. Cada pendência com link e responsável.

### Eventos do mês, por funcionário
- Dias trabalhados e **faltas** (justificadas e injustificadas), **atrasos e saídas antecipadas** em minutos, **horas extras** e saldo do **banco de horas**
  (lançamentos e vencimentos), **aulas dadas, reposições e faltas de aula** do professor, DSR, **férias** no período, afastamentos e datas de admissão e de desligamento.
- Visão em tela para conferência, e mudança de um número exige o ajuste no ponto (nada se edita direto no arquivo).

### Arquivo
- Exportação em **CSV e XLSX** com colunas e códigos de evento **configuráveis por escritório** (mapa "evento do sistema → código do escritório"), no padrão do
  mapa de contas da Onda 73. Gera um registro do arquivo (quem, quando, hash) e permite reexportar a mesma competência com versão.
- Fechar a competência trava o ajuste de ponto e a apuração do mês (reabrir pede permissão e justifica).

## Base legal

- CLT, arts. 317 a 324 (professor) e art. 58 e seguintes (jornada), já aplicados nas ondas 41 e 50; Portaria MTP nº 671/2021 para o ponto.
- LGPD: arquivo com dado trabalhista é compartilhado com o escritório (operador). Acesso restrito, download registrado e expiração do arquivo gerado,
  como nos relatórios agendados.

## Dependências e riscos

- **Fonte única:** se a competência fechar com ponto pendente, o escritório recebe número errado. A conferência é a parte central, e não o arquivo.
- O escritório pode querer mais de um leiaute; começar por CSV genérico e um leiaute.
- Convenção coletiva do professor (valor da hora, hora-atividade) continua com o escritório; o sistema só entrega quantidades.
- Fechamento de RH e fechamento financeiro (Onda 105) são parentes: avaliar uma competência única.

## Esforço

Médio.

## Pacote

**P9 (Fechamento fiscal e contador)**, junto com o [Fechamento do mês (Onda 105)](fechamento_mes_financeiro_roadmap.md). Ver a
[proposta de unificação](unificacao_de_ondas_proposta.md).
