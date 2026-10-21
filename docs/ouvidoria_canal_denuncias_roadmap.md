# Ouvidoria e Canal de Denúncias (Onda 35) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Famílias, alunos e funcionários precisam de um canal para relatar situações sensíveis — bullying,
assédio, discriminação, desvio de conduta, violência — com a possibilidade de **sigilo ou
anonimato** e acompanhamento por protocolo. A **Central de Atendimento** atual serve a dúvidas e
solicitações comuns e não protege a identidade de quem relata.

## O que já existe (ponto de partida)

- `AtendimentoChamado`, `AtendimentoSetor`, `AtendimentoMensagem` e a página **Central de
  Atendimento** do portal: chamados por setor com troca de mensagens.
- `OcorrenciaEscolar`/`TipoOcorrencia`: registro disciplinar **feito pela escola** sobre o aluno.
- `OrdemServico`: fluxo de tarefa para a equipe.
- Políticas de acesso a dado sensível (`docs/lgpd_saude_policies.md`).
- Não existe denúncia anônima, protocolo consultável sem login, fluxo de apuração restrito nem
  prazos de resposta.

## Escopo

### Modelos novos
- `Denuncia`: protocolo e código de acompanhamento (hash, sem vínculo com usuário quando anônima),
  categoria, descrição, local/data, envolvidos (texto), anônima ou identificada, status (recebida,
  em apuração, concluída, arquivada), prazo.
- `DenunciaAnexo` (disco privado) e `DenunciaMovimentacao` (parecer interno, devolutiva ao autor).
- `DenunciaComite`: pessoas autorizadas a ver denúncias de uma categoria.

### Telas
- Formulário público e no portal, com opção de anonimato; ao enviar, mostra **protocolo e código**
  para consulta posterior sem login.
- Fila restrita (comitê/direção), com triagem, apuração, devolutiva e encerramento.
- Relatório agregado por categoria e período, sem identificar pessoas.

### Regras
- Anonimato real: não guardar IP nem user agent da denúncia anônima; o código de acompanhamento é
  aleatório e só o hash fica no banco.
- Denúncia que envolva **suspeita de violência contra criança ou adolescente** tem fluxo próprio
  de comunicação ao Conselho Tutelar (ECA, art. 13; Lei nº 13.431/2017) — o procedimento exato deve
  ser definido com o jurídico da escola.
- Quem é citado na denúncia não pode participar da apuração.

## Dependências já satisfeitas

- Portal, armazenamento privado, permissões por papel e log de atividades.

## Risco e observação

- Risco de uso indevido (denúncia falsa): o fluxo deve prever apuração e devolutiva, e o regulamento
  do canal precisa ser divulgado.
- Possível exigência de canal para prevenção ao assédio em empresas com CIPA (Lei nº 14.457/2022):
  conferir com o jurídico/RH se se aplica à escola.
