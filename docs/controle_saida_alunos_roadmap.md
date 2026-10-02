# Controle de Saída de Alunos — Manual e por Catraca (Onda 9) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Item comum em sistemas de gestão escolar (controle de quem está autorizado a retirar o
aluno e registro de cada retirada), não coberto pela lista de marketing do Sponte nem pelas
Ondas 1-8, mas considerado relevante por ser uma preocupação de segurança frequente,
principalmente na Educação Infantil e no Ensino Fundamental I.

Inclui também o controle de acesso físico por catraca/leitor de crachá: na prática é o
mesmo registro de saída, só com origem diferente (lançado manualmente pela secretaria, ou
automático quando o aluno passa o crachá na catraca ao sair). Por isso as duas ideias viram
uma só proposta, em vez de dois sistemas que fariam a mesma coisa.

## O que já existe (ponto de partida)

- `aluno_responsavel` (pivot entre `Pessoa` e `Pessoa`, via `App\Models\AlunoResponsavel`)
  já tem a coluna **`permissao_retirada`** (boolean, padrão `false`) e `tipo_vinculo_id`
  (ex.: Pai, Mãe). Hoje é só um dado estático guardado no banco — não há tela dedicada que
  o exponha, nem qualquer registro de uso (quem efetivamente retirou o aluno e quando).
- `ContatoEmergencia` (ligado a `FichaMedica`) é um cadastro adjacente mas distinto: serve
  para "quem acionar numa emergência de saúde", não "quem pode retirar o aluno".
- `TemplateCracha`/`TemplateCrachaV3` já cuidam do **layout de impressão** do crachá
  (pessoas e turmas), mas não existe nenhuma leitura/evento de crachá no sistema — é só
  design e impressão, sem controle de acesso.

## Escopo

### Modelo novo
- `RegistroRetirada`: `matricula_id`, `pessoa_id` (quem retirou — nullable: preenchido
  quando um responsável retira o aluno, vazio quando é o próprio aluno saindo sozinho pela
  catraca), `origem` (enum: `manual` ou `catraca`), `horario_retirada`,
  `registrado_por_user_id` (nullable — só se aplica a registros manuais, feitos por um
  funcionário), `observacao` (nullable, para casos de exceção/autorização pontual).
- Quando `pessoa_id` está preenchido (retirada por responsável, manual ou pela própria
  catraca), valida que essa pessoa tem `permissao_retirada = true` na relação com o aluno.

### Integração com catraca/leitor de crachá
- Endpoint dedicado (ex.: `POST /api/webhooks/catraca`) que a controladora da catraca
  chama ao ler um crachá na saída, criando o `RegistroRetirada` com `origem = catraca` —
  mesma ideia de autenticação por segredo compartilhado já usada nos webhooks de
  pagamento/Assinafy (`WebhookSignatureValidator`).
- O crachá já impresso (`TemplateCracha`) precisa carregar o identificador que a catraca lê
  (código de barras, QR ou RFID, a depender do hardware escolhido) — ajuste pontual no
  template, não um sistema novo de crachás.
- A integração real com uma marca específica de catraca fica para quando a escola escolher
  o fornecedor/hardware; o endpoint e o modelo de dados já nascem prontos para receber o
  evento, independente da marca.

### Telas
- Resource/Relation manager para gerenciar `permissao_retirada` por responsável (hoje só
  existe no banco, sem UI).
- Ação "Registrar Retirada" na secretaria para o fluxo manual (busca o aluno, lista só os
  responsáveis com `permissao_retirada = true`, confirma o horário).
- Painel simples "quem já saiu hoje" filtrável por turma e por origem (manual/catraca) —
  útil no fim do turno.

## Dependências já satisfeitas

- `AlunoResponsavel` / `permissao_retirada` já existem na tabela `aluno_responsavel`.
- `TipoVinculo` já existe para qualificar a relação (Pai, Mãe, Responsável Legal etc.).
- `WebhookSignatureValidator` já existe e cobre exatamente o tipo de autenticação que um
  endpoint de catraca precisaria (segredo compartilhado, HMAC).
