# Admissão e Desligamento de Funcionários (Onda 65)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda das telas e na seção 77 do
> `MANUAL_USUARIO.md`. Tela: `/admin/rh/admissao-desligamento` (grupo RH, com um número no menu para as
> providências vencidas) e a aba **Admissão e Desligamento** na página do funcionário.

## Contexto

O RH do sistema guardava o funcionário, o contrato de trabalho, as férias, o ponto e a avaliação, mas **não
guardava o processo de entrar e de sair**. Admissão e desligamento têm uma lista de providências com prazo e
comprovante (documentos, exame médico, contrato, registro no eSocial, acessos, equipamentos, treinamento,
pagamento da rescisão) e, no desligamento, um risco que o sistema não via: **a pessoa continuava com acesso ao
sistema** (notas, dados de alunos, financeiro) depois de sair.

Base legal e prática (confira com o contador e o jurídico, e ajuste `config/rh_processos.php`):

- **CLT, art. 477, § 6º**: as verbas rescisórias são pagas até 10 dias depois do fim do contrato.
- **Lei nº 12.506/2011**: aviso prévio de 30 dias, mais 3 dias por ano de serviço.
- **NR-7**: exame médico admissional (antes de começar) e demissional (quando exigido).
- **eSocial**: evento de admissão (S-2200) até o dia anterior ao início; evento de desligamento (S-2299) em até
  10 dias do término.
- **Lei nº 13.722/2018 (Lei Lucas)**: noções de primeiros socorros para professores e funcionários de escolas de
  educação básica.
- **LGPD**: os documentos do empregado são dado pessoal; ficam em área privada e só abrem para quem tem
  permissão.

## O que já existia

- `Funcionario` (cargo, `data_admissao`, `data_desligamento`, regime), `ContratoTrabalho`, férias, ponto,
  avaliação docente; usuários ligados à `Pessoa` e papéis (Spatie).
- Nada de lista de providências, comprovantes nem de retirada de acesso no desligamento.

## O que foi feito

### Processos e lista de providências
- `processos_funcionario`: um por admissão ou desligamento, com a data (primeiro ou último dia de trabalho), o
  motivo (desligamento), a situação (em andamento, concluído, cancelado) e a observação (**criptografada**).
- `itens_processo_funcionario`: as providências, com categoria (documentos, exame, contrato e registro,
  acessos, equipamentos, treinamento, financeiro, outros), orientação, se é **obrigatória**, o **prazo**,
  quem fez e quando, observação e comprovante.
- A lista padrão vem de `config/rh_processos.php` (16 itens na admissão, 12 no desligamento) com o prazo em
  dias a partir da data do processo. Cada escola ajusta o arquivo. Depois de iniciado, a lista do processo é
  independente: mudar o arquivo não mexe nos processos em andamento.
- Cada providência pode ser **marcada como feita** (com comprovante opcional), **dispensada** ("não se aplica",
  sempre com justificativa, inclusive as obrigatórias), **reaberta**, receber um **comprovante** a qualquer
  hora, e a escola pode **acrescentar** providências novas.
- Um processo em andamento por tipo e funcionário; admissão e desligamento convivem. O desligamento só abre
  para quem está ativo e exige o motivo.

### Concluir
Exige as providências **obrigatórias** resolvidas (a mensagem diz quais faltam).

- **Admissão:** encerra o processo e guarda o histórico. O cadastro do funcionário não muda (a data de admissão
  já é obrigatória ao cadastrar).
- **Desligamento:** só a partir do **último dia de trabalho** (o acesso segue até lá, no aviso prévio
  trabalhado). Grava `data_desligamento` e **retira o acesso da pessoa ao sistema**:
  - os usuários ligados à pessoa perdem todo papel que **não seja de família** (`responsavel` e `aluno`
    ficam), e as permissões dadas direto ao usuário;
  - sem nenhum papel sobrando, a conta é **desativada**. Quem também é pai, mãe ou aluno da escola continua
    com a conta, só sem os papéis de equipe;
  - **super administrador nunca é alterado em silêncio:** vira aviso para o administrador resolver;
  - aulas na grade horária com esse professor viram aviso (reatribuir em Grade Assistida).

Cancelar o processo não muda o cadastro nem os acessos.

### Privacidade e acesso
- Permissões `ViewAny/View/Create/Update/Concluir:ProcessoFuncionario`: só **administração** (como o resto do
  RH). Concluir tem permissão própria porque tira acessos.
- Comprovantes em `processos_funcionario/{processo}/` no disco privado, com o tipo detectado pelo conteúdo, e
  abrem **só com `View:ProcessoFuncionario`**. Secretaria e coordenação ficam de fora, diferente dos arquivos
  gerais.
- O log (`rh`) guarda o tipo, o funcionário e as contagens, **nunca** o motivo do desligamento nem a observação.
- O número no menu conta as providências com prazo vencido em processos em andamento (guardado por 10 minutos
  e esquecido a cada mudança).

## Decisões e limites

- **Não calcula a rescisão, não gera o TRCT e não envia nada ao eSocial:** é uma lista de controle com prazo e
  comprovante. O pagamento e os eventos são feitos no sistema da contabilidade.
- A lista é única por tipo (sem modelos por cargo) e pensada para escola privada com contratação pela CLT.
- Sem lembrete automático por e-mail das providências vencidas: elas aparecem no número do menu e na fila *Providências de RH vencidas* da Mesa de Pendências (Onda 71).
- O contrato de trabalho e o termo de confidencialidade podem ser colhidos pela **assinatura eletrônica** (Onda 69): a ação *Assinatura eletrônica* da providência monta o documento do funcionário a partir de um modelo ativo, e a providência é concluída sozinha quando todos assinam. Veja [assinatura_eletronica_documentos_roadmap.md](assinatura_eletronica_documentos_roadmap.md).
- Os papéis que saem são todos os que não são de família; escolas com papéis próprios de família devem
  ajustar `ProcessoFuncionarioService::PAPEIS_DA_FAMILIA`.

## Testes

`tests/Feature/ProcessoFuncionarioTest.php` (34 testes: lista e prazos, regras de início, comprovantes, não se
aplica, conclusão, retirada de acessos com e sem outros papéis, super admin, data do desligamento, aulas na
grade, telas, selo do menu e autorização dos arquivos).
