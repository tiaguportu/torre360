# Portfólio do Aluno e Parecer Descritivo (Onda 30)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda das telas (**Portfólio do Aluno** e **Pareceres
> Descritivos**, grupo **Acadêmico** do menu; **Portfólio e Pareceres** no Portal da Família) e na
> seção 64 do `MANUAL_USUARIO.md`.

## Contexto

Na Educação Infantil e nos anos iniciais, a avaliação é **descritiva**: em vez de nota, a escola
registra o que a criança já consegue fazer e o que está em desenvolvimento, e acompanha isso com
evidências (trabalhos, fotos, registros). O sistema já tinha conceitos por habilidade
(`NotaHabilidade`), mas não havia onde guardar as evidências nem um texto de parecer com revisão e
publicação para a família.

## O que já existia (ponto de partida)

- `Habilidade`, `AvaliacaoHabilidade`, `NotaHabilidade` e `ConceitoHabilidade` (realiza bem, em
  desenvolvimento, não realiza, não observado): a base do parecer.
- `EtapaAvaliativa` por turma: o parecer é por aluno e etapa.
- Portal da Família com seletor de aluno (`InteractsWithMatriculaSelecionada`).
- Copiloto Pedagógico (`GeminiAgentService`, flags desligadas por padrão até a pendência M9).

## O que foi implementado

### Dados

- `portfolio_itens`: evidência de uma matrícula (título, descrição, data, anexos em JSON, `mostra_outros_alunos`,
  `visivel_familia`, quem registrou). Tabela de ligação `portfolio_item_habilidade` com as
  habilidades demonstradas.
- `pareceres_descritivos`: um por (`matricula_id`, `etapa_avaliativa_id`), com `status` (rascunho,
  em revisão, publicado), `texto`, `gerado_com_ia`, autor, quem publicou e quando.

### Regras

- **Material com outros alunos nunca vai para a família** de um aluno só: se `mostra_outros_alunos`,
  `visivel_familia` é forçado a `false` (no model e no formulário). É a imagem de outras crianças.
- **Arquivos no disco privado** (`local`), entregues apenas por `portfolio.anexo`, que autoriza cada
  acesso: equipe pela policy (com o recorte do professor) e família só para item **compartilhado**
  de aluno acessível a ela. Cabeçalhos seguros (`nosniff`, `no-store`); só imagem e PDF abrem no
  navegador. Apagar o item apaga os arquivos.
- **Recorte do professor** (`EscopoProfessor`): quem atua como professor só alcança alunos das turmas
  em que é conselheiro ou tem disciplina. Coordenação, secretaria e administração não são recortadas.
- **Fluxo do parecer:** o professor escreve (rascunho) e envia para revisão; **a coordenação
  publica** (`Publicar:ParecerDescritivo`). Publicado não é editado: precisa ser reaberto. O texto
  mínimo é 80 caracteres e não pode conter o marcador `[ALUNO]`.
- **Log de atividade** registra o fluxo e **nunca o texto** do parecer.

### Rascunho por IA (desligado por padrão)

- Flag `COPILOTO_PEDAGOGICO_PARECER_IA_HABILITADO` (padrão `false`, pendência M9).
- **Minimização de dados (LGPD, art. 6º, III):** o aluno vai como `[ALUNO]` (o primeiro nome é
  recolocado no servidor depois da resposta), só o gênero gramatical (do cadastro) é enviado, sem
  sobrenome, documento, contato nem dado de saúde; telefones, e-mails e CPFs digitados em texto livre
  são ocultados (`CrmIaVendasService::ocultarDadosPessoais`); evidências com outros alunos ficam de
  fora; o conteúdo vai delimitado como **dado não confiável** (`delimitarEntradaSegura`).
- O rascunho volta para o formulário como **rascunho**: nunca é publicado sozinho.

### Telas

- **Portfólio do Aluno** (Acadêmico): cadastro com upload (até 10 arquivos de 20 MB: imagem, PDF,
  áudio MP3/WAV, vídeo MP4), habilidades e compartilhamento.
- **Pareceres Descritivos** (Acadêmico): criação por aluno e etapa (abre o existente se já houver),
  edição, enviar para revisão, publicar, reabrir, rascunhar com IA.
- **Portfólio e Pareceres** (Portal da Família): pareceres **publicados** e evidências
  **compartilhadas** do aluno selecionado, com miniaturas.

### Permissões

`ViewAny/View/Create/Update/Delete/DeleteAny:PortfolioItem`;
`ViewAny/View/Create/Update/Delete/DeleteAny/Publicar/GerarComIa:ParecerDescritivo`. O professor
cria, edita e envia para revisão; só a coordenação e a administração publicam.

## Limitações e próximos passos

- Sem transcodificação nem miniaturas de vídeo e áudio (abrem como arquivo).
- Sem exportação do portfólio em PDF único para o histórico do aluno.
- Sem comentários da família nem "curtidas" nas evidências (decisão de produto: o portal é de leitura).
- Pareceres por disciplina (anos finais) não foram modelados: o parecer é por aluno e etapa.

## Riscos e observações

- **Imagem de criança:** vale a autorização de uso de imagem (veja `autorizacao_uso_imagem_roadmap.md`).
  O item pode ser marcado como compartilhado sem essa autorização; hoje a checagem é do professor.
- O rascunho por IA só deve ser ligado depois do contrato com o provedor sem retenção para treino.
