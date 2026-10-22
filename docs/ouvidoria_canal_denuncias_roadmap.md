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

## Ampliação: programa de convivência escolar e prevenção ao bullying

> Esta seção reúne o que foi pensado como uma "Onda 63" separada. O canal, a fila restrita, a apuração e a
> devolutiva acima já cobrem o **recebimento e o tratamento do caso**; o que sobra é o **programa da escola**
> e o acompanhamento do aluno, que usa o mesmo cadastro em vez de abrir um segundo. Por isso foi mesclada aqui
> e não virou onda própria. Continua sendo proposta.

### Base legal (a conferir com o jurídico da escola)

- **Lei nº 13.185/2015**: institui o Programa de Combate à Intimidação Sistemática (bullying) e manda a escola
  agir na prevenção e no combate, sem aplicar punição de caráter pedagógico que não ajude a resolver.
- **LDB, art. 12, IX e X** (Lei nº 13.663/2018): o estabelecimento promove medidas de conscientização,
  prevenção e combate a todos os tipos de violência, especialmente o bullying, e ações de cultura de paz.
- **Lei nº 14.811/2024**: tipifica a intimidação sistemática (bullying) e a virtual (cyberbullying) no Código
  Penal (art. 146-A) e traz medidas de proteção à criança e ao adolescente nos estabelecimentos de ensino.
- **ECA, art. 13**, e **Lei nº 13.431/2017**: comunicação ao Conselho Tutelar nos casos de suspeita de violência.

### O que acrescentar ao canal

- **Categorias de convivência** no cadastro: bullying, cyberbullying, agressão física, discriminação,
  assédio, exposição de imagem. O mesmo caso pode ter vários envolvidos (vítima, autor, testemunha) com o
  papel de cada um.
- **Plano de ação** com prazo e responsável: acolhimento da vítima, conversa com o autor, contato com as
  famílias (registrado), mediação, encaminhamento à rede de proteção e ao Conselho Tutelar. Sem a
  indicação de punição como única resposta.
- **Acompanhamento** do aluno depois do encerramento (30 e 90 dias), com o registro de que o caso não se
  repetiu.
- **Prevenção** como ação registrada: rodas de conversa, campanhas e formação de professores, com data,
  turma e participantes, para a escola comprovar o programa.
- **Indicadores** sem identificar pessoas: casos por turma, por local e por horário, e o tempo até o primeiro
  contato. Um relatório anual para a direção e o conselho escolar.
- Ligar a **ocorrência escolar** (`OcorrenciaEscolar`) a um caso quando o registro disciplinar nasce de um
  episódio de convivência.

### Regras e limites

- Os dados do caso são sensíveis e de criança: acesso só do comitê de convivência; a família do aluno vê a
  devolutiva, nunca os dados do outro aluno envolvido.
- Os procedimentos locais (secretarias de educação, protocolo do Ministério Público) variam: o plano de ação é
  editável por modelo.

## Dependências já satisfeitas

- Portal, armazenamento privado, permissões por papel e log de atividades.

## Risco e observação

- Risco de uso indevido (denúncia falsa): o fluxo deve prever apuração e devolutiva, e o regulamento
  do canal precisa ser divulgado.
- Possível exigência de canal para prevenção ao assédio em empresas com CIPA (Lei nº 14.457/2022):
  conferir com o jurídico/RH se se aplica à escola.
