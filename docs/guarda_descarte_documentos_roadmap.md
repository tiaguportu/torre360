# Guarda e Descarte de Documentos Escolares (Onda 79) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade.

## Contexto

A escola guarda **tudo, para sempre**: documentos pessoais de alunos e candidatos, contratos, atestados, anexos de
atendimento, PDFs de declarações e de relatórios. A LGPD pede o contrário do "tudo para sempre": dado pessoal que cumpriu a
finalidade e não tem mais obrigação legal de guarda deve ser eliminado. E há documentos que **não podem** ser eliminados
(o histórico escolar costuma ter guarda permanente). Falta a regra que separa uma coisa da outra e a rotina que a aplica.

## O que já existe

- Armazenamento privado por pasta (`documentos_alunos/`, `documentos_candidatos/`, `matriculas_online/`,
  `atendimentos/anexos/`, `documentos_emitidos/`, `documentos_assinatura/`, `processos_funcionario/`,
  `ponto/atestados/`...) e o `VisualizarDocumentoController`, que já conhece a regra de acesso de cada pasta.
- Purga de rascunhos de pré-matrícula, de logs (180 dias) e dos arquivos de OMR e de relatórios (Onda 72): cada uma com o seu
  comando; não há política única.
- Documentos da escola com validade (Onda 60) e o **Portal do Titular** proposto (Onda 34), que recebe o pedido de
  eliminação.
- Histórico, boletim e declaração com QR Code (autenticidade pública).

## Escopo proposto

1. **Tabela de temporalidade**: para cada tipo de documento, prazo de guarda, o marco que o inicia (saída do aluno, fim do
   contrato, emissão), a destinação (eliminar, guardar permanentemente, anonimizar) e a base legal. Editável pela escola,
   com o texto de partida revisado por quem entende de arquivo e de direito educacional.
2. **Fila de elegíveis**: o que passou do prazo, por tipo, para a secretaria revisar e aprovar a eliminação.
3. **Eliminação registrada**: o arquivo sai do disco, o registro fica com o **termo de eliminação** (quem, quando, base) sem o
   conteúdo. Bloqueio se houver pendência (processo, litígio, pedido do titular em curso).
4. **Reflexo no Portal do Titular**: o pedido de eliminação consulta a tabela e responde o que pode ser apagado agora e o
   que a escola precisa guardar, com o fundamento.
5. **Relatório** de guarda e eliminação pela Onda 72, para auditoria.

## Base legal

LGPD, art. 15 (fim do tratamento) e art. 16 (conservação para cumprimento de obrigação legal ou regulatória e uso exclusivo
do controlador, anonimizado), art. 18, VI (eliminação a pedido); Lei nº 8.159/1991 (política nacional de arquivos). Os
prazos por tipo de documento dependem das normas do **sistema de ensino da escola** e do contrato de cada relação: não
inventar prazo, confirmar com o órgão da rede e o jurídico.

## Dependências e riscos

- **Eliminar é irreversível**: a aprovação é humana, em duas etapas, e o primeiro ciclo roda só em modo de listagem.
- Backup guarda cópia do que foi eliminado: a política de retenção dos backups entra no mesmo documento.
- **Fusão sugerida com a Onda 34 (Portal do Titular):** o pedido do titular e a rotina de guarda usam a mesma tabela.
- Ordem: 34 → 79, ou juntas.
