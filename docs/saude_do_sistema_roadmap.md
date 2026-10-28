# Saúde do Sistema (Onda 54) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda.

## Contexto

O sistema depende de peças que falham em silêncio: a fila de jobs, o agendador (régua de cobrança, risco
de evasão, avisos), o disco, o backup, o envio de e-mail e as integrações (Assinafy, gateways). Quando uma
delas para, ninguém é avisado; o problema aparece dias depois, quando a família reclama. A revisão de
segurança também aponta o ambiente de hospedagem (`APP_ENV`, `APP_DEBUG`, Debugbar) como o maior risco
de exposição (item A4 de `docs/seguranca_pendencias.md`).

> Parte da **engenharia de entrega**: a [Onda 89](paridade_esquema_testes_mariadb_roadmap.md) dá o portão de testes e a
> [Onda 90](publicacao_segura_deploy_roadmap.md) a publicação com backup e volta; esta onda mostra o estado depois. Ver o
> [índice das ondas 80 a 90](ondas_80_a_88_indice.md#fusões-sugeridas).

## O que já existe

- `QueueSupervisorWidget` (painel da fila) e o carimbo `queue_last_run_at` gravado a cada job processado.
- O agendador em `routes/console.php` com dezenas de comandos, sem painel que mostre se rodaram.
- `EmailLog` (e-mails enviados) e a tabela `failed_jobs` do Laravel.
- Os comandos agendados de reconciliação do Assinafy e da conciliação financeira, que hoje não guardam
  uma data de última execução bem-sucedida visível.
- **Falta:** uma tela única que junte tudo e um aviso quando algo fica vermelho.

## Escopo

### Página "Saúde do Sistema" (grupo Sistema e Segurança)
Cada verificação mostra verde, amarelo ou vermelho, a data da última prova de vida e o que fazer:

- **Fila:** último job processado, jobs falhos, jobs parados há mais de X minutos.
- **Agendador:** carimbo do último `schedule:run` e lista dos comandos agendados com a última execução.
- **Banco:** conexão e tempo de resposta.
- **Disco:** espaço livre em `storage` e no disco de documentos.
- **Backup:** data do último backup (comando `sistema:registrar-backup`, chamado pela rotina de backup).
- **Ambiente:** alerta se `APP_ENV=production` com `APP_DEBUG=true` ou com o Debugbar ligado.
- **E-mail:** envios recentes e falhas (o registro de falhas é parte da onda).
- **Integrações:** última reconciliação com sucesso (a data passa a ser gravada pelos comandos) e
  credenciais prestes a vencer.

### Aviso
- E-mail/push diário ao `super_admin` somente se houver item vermelho, com o nome do item (sem segredos).

## Regras e limites

- A página nunca mostra valores de `.env`, chaves ou senhas; só o estado (ok, atenção, falha).
- Acesso restrito ao `super_admin`.
- As verificações têm limite de tempo para a página não travar quando o recurso está fora do ar.

## Dependências

- Nenhuma. Cada verificação nova é uma classe pequena que implementa uma interface comum.

## Riscos e observações

- Não substitui monitoramento externo (uptime, alertas do provedor): complementa.
- Backup só é verificável se a rotina avisar o sistema.

## Esforço

Pequeno.
