# Ponto Eletrônico e Banco de Horas (Onda 41)

> **Status: implementado no modo "controle interno".** Os modos **REP-A** e **REP-P**
> (registro de ponto com valor legal perante a fiscalização do trabalho) **estão bloqueados por
> portões de conformidade**: dependem de requisitos jurídicos e técnicos que o sistema sozinho
> não cumpre. A seção [Conformidade com a legislação](#conformidade-com-a-legislação) lista
> cada requisito, o que já está feito e como resolver o que falta. Detalhes de uso ficam no texto
> de Ajuda das telas (grupo **RH** do menu) e na seção 67 do `MANUAL_USUARIO.md`.

## Resposta curta: a lei permite?

**Permite registrar e apurar a jornada por software**, e o sistema faz isso hoje. **Não permite
chamar esse registro de "ponto oficial" (REP-P) enquanto faltarem os requisitos abaixo**, e o
sistema impede essa declaração. Em resumo, o que falta para o modo REP-P:

| # | Requisito (Portaria MTP nº 671/2021) | Quem resolve | Situação |
|---|---|---|---|
| 1 | Certificado de registro do programa no **INPI** (art. 91) | Titular do software | Pendente |
| 2 | **Atestado Técnico e Termo de Responsabilidade** (art. 89, Anexo VII), com assinatura eletrônica qualificada de pessoa física; o empregador só pode usar o sistema se o tiver (art. 89, § 4º) | Desenvolvedor, depois de cumprir o Anexo IX | Pendente |
| 3 | **Assinatura eletrônica ICP-Brasil** do comprovante (art. 79) | Empresa (certificado) + desenvolvedor (implementação) | Pendente |
| 4 | **AFD** no leiaute oficial (art. 81, Anexo V) | Desenvolvedor | Pendente |
| 5 | **AEJ** no leiaute oficial (art. 83, Anexo VI) | Desenvolvedor | Pendente |
| 6 | Conformidade item a item com o **Anexo IX** (requisitos do REP-P) | Desenvolvedor | Pendente |

O que **já está atendido**: comprovante com NSR, empregador, trabalhador, data/hora e hash
SHA-256; relógio conferido com a Hora Legal Brasileira (variação máxima de 30 s); vedações do
art. 74; registros imutáveis; banco de horas conforme a CLT. Veja a tabela completa adiante.

## Contexto

O ponto eletrônico é uma funcionalidade clássica de RH, ligada ao módulo de Funcionários
(Onda 10). A legislação brasileira trata o registro eletrônico de ponto com rigor, porque ele é a
prova da jornada em ações trabalhistas. Por isso o módulo nasce com duas ideias:

1. **Registrar com integridade**, de modo que nenhuma marcação possa ser alterada ou apagada sem
   deixar rastro, e a correção seja sempre um novo registro (ajuste).
2. **Não declarar conformidade que não existe**: os modos oficiais só podem ser ativados quando os
   requisitos são cumpridos, e a tela mostra o que falta e como resolver.

## Base legal consultada

- **CLT, art. 74, § 2º**: o registro de entrada e saída é obrigatório para estabelecimentos com
  **mais de 20 trabalhadores** (redação da Lei nº 13.874/2019). Pode ser manual, mecânico ou
  eletrônico, conforme instruções do Ministério do Trabalho.
- **CLT, art. 58, § 1º**: variações de até 5 minutos por marcação, no máximo 10 minutos por dia,
  não são descontadas nem contadas como extra (TST, Súmula 366: ultrapassado o limite, conta-se
  o tempo todo).
- **CLT, art. 59**: banco de horas por acordo individual escrito, com compensação em até 6 meses
  (§ 5º); por acordo ou convenção coletiva, até 12 meses (§ 2º); compensação no mesmo mês por
  acordo individual, inclusive tácito (§ 6º). Horas não compensadas são pagas como extras (§ 3º).
- **CLT, arts. 66 e 71**: descanso mínimo de 11 horas entre jornadas; intervalo de 1 hora acima de
  6 horas de trabalho e de 15 minutos entre 4 e 6 horas.
- **Portaria MTP nº 671/2021** (Seção sobre registro eletrônico de ponto, arts. 74 a 97, DOU de
  11/11/2021, segundo a pesquisa feita para esta onda):
  - art. 74: vedações (restrição de horário à marcação, marcação automática por horário
    predeterminado, exigência de autorização prévia para sobrejornada, alteração de dados
    registrados pelo empregado); § 2º e arts. 93 e 94: ponto por exceção, só com acordo individual
    escrito, convenção ou acordo coletivo;
  - art. 75: os três tipos de registrador eletrônico (REP-C, REP-A, REP-P);
  - art. 76: **REP-C**, equipamento monolítico, certificado pelo INMETRO (art. 90);
  - art. 77: **REP-A**, conjunto de equipamentos e programas autorizado por acordo ou convenção
    coletiva, sem certificação do INMETRO, mas com acesso da fiscalização aos registros;
  - art. 78: **REP-P**, programa executado em servidor dedicado ou em nuvem, com certificado de
    registro de programa de computador no INPI (art. 91), sem INMETRO;
  - art. 79: comprovante de registro com NSR, identificação do empregador (CNPJ/CPF), nome e CPF
    do empregado, data e hora, **código hash SHA-256 (REP-P)** e assinatura eletrônica;
  - art. 80, parágrafo único, II: entrega eletrônica do comprovante após cada marcação;
  - art. 81: **AFD** (Arquivo Fonte de Dados, Anexo V), gerado por todos os tipos de REP;
  - art. 82, parágrafo único: o programa de tratamento deve tratar as movimentações do banco de
    horas;
  - art. 83: **AEJ** (Arquivo Eletrônico de Jornada, Anexo VI);
  - art. 85: acesso da fiscalização aos dados no prazo regulamentar;
  - art. 89: **Atestado Técnico e Termo de Responsabilidade** (modelo no Anexo VII), com assinatura
    eletrônica qualificada (Lei nº 14.063/2020, art. 4º, III) de pessoa física; § 4º: o empregador
    só pode usar o sistema se possuir o atestado;
  - art. 96: REP-C certificados antes continuam valendo; art. 97: prazo de um ano para os
    desenvolvedores se adequarem.
- **Anexo IX** (requisitos do REP-P): o texto integral **não foi obtido** na pesquisa (a página
  oficial consultada omite os anexos). Por fontes secundárias, ele exige, entre outros, relógio
  sincronizado com a Hora Legal Brasileira (Observatório Nacional) com variação máxima de 30
  segundos, coletores conectados ao REP-P (com envio posterior no caso excepcional de falta de
  conexão) e relógio não analógico com horas, minutos e segundos na marcação.
  **Conferir no texto oficial antes de qualquer declaração de conformidade.**
- **Constituição, art. 7º, XXIX**: prescrição trabalhista de 5 anos durante o contrato e até 2
  anos depois da extinção. Base da recomendação de guarda mínima dos registros (o sistema nunca
  apaga).

> Esta seção resume a pesquisa feita para esta onda e **não substitui parecer jurídico**.
> Os anexos V, VI, VII e IX da Portaria não foram lidos na íntegra.

## Conformidade com a legislação

### O que o sistema já cumpre

| Exigência | Como o sistema atende |
|---|---|
| Vedação de restringir horário de marcação (art. 74) | Qualquer horário é aceito. O único freio é um anti-duplo-clique de 10 s, que devolve a mesma marcação e não é restrição de horário |
| Vedação de marcação automática (art. 74) | Só existe marcação por ação do trabalhador. A apuração nunca cria marcações |
| Vedação de autorização prévia para sobrejornada (art. 74) | Não há esse fluxo. A hora excedente é apurada depois |
| Vedação de alterar dado registrado (art. 74) | `PontoRegistro` é só inserção: o model recusa `update`/`delete` e gatilhos do banco (MySQL/MariaDB e SQLite) também. Correção só por **tratamento** (ajuste), que fica ao lado do original |
| NSR | Sequencial **por unidade** (estabelecimento), alocado sob `lockForUpdate` na configuração |
| Hash SHA-256 encadeado | Cada registro guarda o hash do anterior; `ponto:verificar-integridade` detecta adulteração, lacuna de NSR, encadeamento rompido e registros removidos do fim da cadeia |
| Horário do servidor | O horário gravado é sempre o do servidor, nunca o do aparelho |
| Hora Legal Brasileira (≤ 30 s) | Consulta NTP.br (`NtpHoraLegalProvider`), com cache de 1 min. A marcação **nunca é bloqueada** pela falha: ela é sinalizada (`hora_legal_ok`) e vira alerta na configuração |
| Comprovante (art. 79) | NSR, empregador e CNPJ, trabalhador e CPF, data/hora com fuso, origem, hash. Entrega eletrônica por PDF logo após a marcação. **Sem assinatura** (faixa "NÃO ASSINADO") |
| Banco de horas (art. 82, p.ú.) | Razão de lançamentos (só inserção), com prazo por tipo de acordo e expiração em horas a pagar |
| Tolerância (CLT, art. 58, § 1º) | Saldo diário de até 10 min é zerado; acima, conta tudo |
| Dado pessoal | Motivo dos ajustes criptografado e fora do log; sem geolocalização nem biometria; consulta a dados de outra pessoa fica registrada |

### O que impede os modos REP-A e REP-P

#### 1. Registro do programa no INPI (art. 91) — Pendente

- **Por que importa:** é o que distingue um REP-P de um programa qualquer.
- **Quem resolve:** o titular dos direitos do software (a empresa que o desenvolve), não o cliente.
- **Como resolver:** protocolar o pedido de **registro de programa de computador** no INPI
  (Lei nº 9.609/1998) e, com o certificado, informar o número em *RH → Configuração do Ponto*.
  Não é possível ativar o REP-P sem esse número.

#### 2. Atestado Técnico e Termo de Responsabilidade (art. 89, Anexo VII) — Pendente

- **Por que importa:** o empregador **só pode usar** o sistema se tiver o atestado (art. 89, § 4º).
  Usar sem ele é o risco principal em uma fiscalização.
- **Quem resolve:** o desenvolvedor, que emite o atestado **depois** de comprovar o cumprimento do
  Anexo IX. O documento é um PDF com assinatura eletrônica qualificada de **pessoa física**
  (certificado ICP-Brasil, por exemplo e-CPF).
- **Como resolver:** (a) obter o texto oficial do Anexo VII e do Anexo IX; (b) concluir os itens
  4, 5 e 6 desta lista; (c) o responsável técnico assina o atestado; (d) o cliente envia o PDF em
  *Configuração do Ponto*, informa a data e **marca a conferência da assinatura** depois de validá-la
  em um verificador ICP-Brasil.

#### 3. Assinatura eletrônica ICP-Brasil do comprovante (art. 79) — Pendente

- **Situação no código:** existe a interface `AssinadorPonto`. A única implementação é
  `AssinadorNaoAssinado`, que devolve `null` e faz o comprovante sair com a faixa "NÃO ASSINADO".
  Mudar `PONTO_ASSINADOR` no `.env` **não** liga assinatura: qualquer valor cai em "não assina".
- **Como resolver:** a empresa adquire um **certificado digital ICP-Brasil** (A1 em arquivo ou A3 em
  token/HSM) em nome do estabelecimento; o desenvolvedor implementa `AssinadorPonto` com esse
  certificado (formato CAdES/PAdES, conforme o Anexo IX) e só então muda a flag
  `ponto.requisitos_desenvolvedor.assinatura_icp_brasil` para `true`. Guardar a chave privada fora
  do repositório e do banco (cofre de segredos ou HSM).

#### 4. AFD no leiaute oficial (art. 81, Anexo V) — Pendente

- **Como resolver:** obter o Anexo V e implementar o exportador; validar a saída contra o
  leiaute (campos, tipos de registro, codificação). O NSR, a data/hora e o CPF já existem no
  registro; faltam o cabeçalho, os tipos de registro e a assinatura do arquivo.
  Só depois, `afd_oficial` vira `true`.

#### 5. AEJ no leiaute oficial (art. 83, Anexo VI) — Pendente

- **Como resolver:** obter o Anexo VI e implementar o exportador a partir da apuração
  (`PontoApuracaoService`), incluindo jornadas contratadas, marcações, ajustes e banco de horas.
  Só depois, `aej_oficial` vira `true`.

#### 6. Anexo IX validado item a item — Pendente

- **Como resolver:** ler o Anexo IX inteiro e preencher uma matriz requisito × evidência. Pontos
  já identificados por fontes secundárias e ainda por confirmar: coletor *online* (com exceção
  *offline* e envio posterior das marcações), relógio com horas, minutos e segundos na tela de
  marcação (a tela mostra um relógio, mas o horário gravado é o do servidor), identificação do
  empregador e do trabalhador. Só depois, `anexo_ix_validado` vira `true`.

#### REP-A (alternativo)

- **Exige** acordo ou convenção coletiva de trabalho vigente que autorize o sistema (art. 77),
  além do AFD e do AEJ oficiais (itens 4 e 5). A configuração pede a referência e a data final de
  vigência do instrumento; vencido, o modo deixa de ser aceito.

#### REP-C (convencional)

- É um **equipamento** certificado pelo INMETRO (arts. 76 e 90). Software não é REP-C. Se a escola
  optar por relógio físico, o Torre360 pode continuar fazendo a apuração e o banco de horas a
  partir do AEJ/AFD, o que depende de importação (veja *Próximos passos*).

### Quem precisa disso

- **Até 20 trabalhadores no estabelecimento:** a CLT não obriga o registro da jornada. O modo
  *controle interno* atende como ferramenta de gestão e de prova complementar.
- **Mais de 20 trabalhadores:** o registro é obrigatório e precisa ser feito por REP-C, REP-A ou
  REP-P. O modo *controle interno* **não** cumpre a obrigação, e a configuração mostra um alerta
  quando isso ocorre.
- Em ambos os casos, o **banco de horas** só é válido com acordo (individual escrito ou
  coletivo); sem acordo, a hora excedente é paga como extra.

### Caminho recomendado

1. **Hoje:** usar *controle interno* para registrar e apurar (e o banco de horas), com acordos
   cadastrados. Mantém integridade e prova, sem prometer o que a lei não reconhece.
2. **Se a escola tem mais de 20 trabalhadores e precisa de ponto oficial agora:** a via mais rápida
   e segura é um **REP-C certificado** (relógio) ou um **REP-P de fornecedor que já tenha registro
   no INPI e Atestado Técnico**, mantendo o Torre360 para folha e banco de horas.
3. **Se a decisão é ter o REP-P próprio:** seguir os itens 1 a 6 acima, na ordem: Anexo IX → AFD e
   AEJ → assinatura ICP-Brasil → atestado → INPI. Estimar meses, não semanas.

## O que foi implementado

### Dados (6 tabelas)

- `ponto_configuracoes`: uma por unidade. Modo (`controle_interno`, `rep_a`, `rep_p`), ativo,
  requisitos declarados (INPI, atestado e data, conferência da assinatura, acordo coletivo e
  vigência), tolerância diária, `ultimo_nsr` e `hash_ultimo_registro` (o sequenciador).
- `ponto_registros`: **só inserção**. `unidade_id`, `funcionario_id` (ambos `restrict`), `nsr`,
  `momento`, `fuso`, `origem`, `ip`, `hora_legal_ok`, `desvio_segundos`, `hash_anterior`, `hash`.
  Único por (`unidade_id`, `nsr`).
- `ponto_tratamentos`: ajustes (`inclusao`, `desconsiderar`, `abono`) com `status` (pendente,
  aprovado, recusado). `motivo` criptografado.
- `ponto_jornadas`: minutos previstos por dia da semana, com vigência.
- `ponto_acordos_banco_horas`: tipo, vigência, prazo em meses (limitado pelo tipo), documento.
- `ponto_banco_horas_lancamentos`: razão **só inserção**, em minutos com sinal, com `vence_em`,
  `credito_origem_id` (na expiração) e origem (`apuracao_dia`, `compensacao`, `expiracao`,
  `ajuste_manual`).

Gatilhos `BEFORE UPDATE/DELETE` em `ponto_registros` e `ponto_banco_horas_lancamentos` (MySQL,
MariaDB e SQLite). Se o usuário do banco não puder criar gatilhos (privilégio ou log binário), a
migration segue e registra aviso: o model continua protegendo e a verificação de integridade
detecta adulteração. Validado em MariaDB 10.4 (criação, bloqueio de UPDATE/DELETE, FK `restrict`,
rollback e nova subida).

### Serviços (`App\Services\Ponto`)

- `PontoRegistroService`: marca o ponto (horário do servidor, NSR, hash, hora legal, anti-duplo-clique).
- `PontoIntegridadeService`: verifica a cadeia de uma unidade.
- `PontoApuracaoService`: apura o dia e o mês (pares entrada/saída, tolerância, feriado nacional,
  alertas de intervalo, interjornada, excesso e falta de jornada) e monta o espelho.
- `PontoTratamentoService`: pedido, aprovação e recusa de ajustes; quem pede não aprova.
- `BancoHorasService`: lançamento da apuração (idempotente, com lançamento corretivo), compensação,
  ajuste manual, posição por crédito (abate primeiro o que vence antes), expiração em horas a pagar.
- `ConformidadePontoService`: pendências por modo, com "como resolver", e alertas.
- `ComprovantePontoService`: dados do comprovante.
- Contratos trocáveis: `HoraLegalProvider` (`NtpHoraLegalProvider`, `HoraLegalDesativada`) e
  `AssinadorPonto` (`AssinadorNaoAssinado`).

### Telas (grupo RH)

- **Registrar Ponto**: botão, relógio de referência, marcações do dia e comprovante em PDF.
- **Espelho de Ponto**: mês a mês, com PDF; o RH escolhe o funcionário e a consulta fica no log.
- **Banco de Horas**: saldo, créditos a vencer, extrato; o RH registra folga e ajuste manual.
- **Ajustes de Ponto**: pedido e decisão; selo de pendentes no menu para quem decide.
- **Configuração do Ponto**: modo, requisitos, "Conformidade" (o que falta e como resolver) e
  "Verificar integridade".
- Abas **Jornada do Ponto** e **Acordos de banco de horas** na ficha do funcionário.

### Agendador

- `ponto:apurar-dia` (02:30): apura o dia anterior e lança no banco de horas.
- `ponto:expirar-banco-horas` (03:00): converte em horas a pagar o que passou do prazo.
- `ponto:verificar-integridade` (03:20): falha e grava erro crítico se a cadeia estiver quebrada.

### Permissões

`Registrar:PontoRegistro` (toda a equipe), `ViewAny/View:PontoRegistro`,
`VerEspelhoDeTodos:PontoRegistro`, `ViewAny/View/Create:PontoTratamento`,
`Aprovar:PontoTratamento`, `*:PontoConfiguracao`, `*:PontoJornada`, `*:PontoAcordoBancoHoras`,
`VerTodos/Ajustar/Compensar:PontoBancoHorasLancamento`. Quem não tem `VerEspelhoDeTodos` só vê o
próprio ponto, mesmo forjando parâmetros da tela.

### Configuração (`config/ponto.php` e `.env`)

`PONTO_HORA_LEGAL` (`ntp` ou `nenhuma`), `PONTO_SERVIDORES_NTP`, `PONTO_NTP_TIMEOUT`,
`PONTO_ASSINADOR` (hoje só `nenhum`). As quatro flags de `requisitos_desenvolvedor` ficam `false`
até a implementação real: **não as altere sem ter feito o trabalho correspondente**.

## Regras de apuração

- Marcações do dia formam pares entrada/saída em ordem; número ímpar deixa o dia **incompleto**,
  que não gera saldo até ser ajustado.
- Segundos são desprezados (vale o minuto).
- Saldo do dia = trabalhado + abono − previsto. Se `|saldo| ≤ 10 min`, o saldo é 0 (a unidade pode
  usar um limite menor, nunca maior).
- Dia sem marcação e com jornada prevista é **falta**; com abono suficiente, **abonado**.
- Feriado nacional tem jornada prevista zero (feriado municipal ou estadual não é reconhecido).
- Só entra no banco de horas o saldo de dias **ok** cobertos por acordo vigente; falta e dia
  incompleto exigem decisão do RH.
- Crédito vence em: data + prazo do acordo (6 ou 12 meses) ou fim do mês. Débitos abatem primeiro o
  crédito que vence antes. O que vence vira "horas a pagar".

## Limitações conhecidas

- Jornada que **atravessa a meia-noite** (turno noturno, vigia) não é tratada: a marcação pertence ao
  dia do calendário.
- A jornada é cadastrada em **minutos por dia**, sem horário de entrada e saída: a tolerância por
  marcação (5 min) não é calculada, só o limite diário (10 min).
- Feriados **municipais e estaduais** e pontes não são considerados; use abono.
- Sem **adicional noturno**, **DSR** e valores em reais: o módulo apura horas, não folha.
- Sem biometria, geolocalização e *offline* (marcação sem internet).
- Sem exportação AFD/AEJ e sem importação de relógio físico.

## Próximos passos possíveis

1. Texto integral dos Anexos V, VI, VII e IX e matriz de conformidade.
2. Exportadores AFD e AEJ.
3. Assinador ICP-Brasil.
4. Importação de AFD de REP-C (apuração no Torre360 com relógio físico).
5. Turno noturno, feriados locais por unidade e ponto por exceção (arts. 93 e 94).
6. App móvel com marcação *offline* e geolocalização, com análise de LGPD e da Portaria antes.

## Riscos e observações

- **Risco jurídico:** declarar REP-P sem os requisitos expõe a escola a autuação e a perda de valor
  probatório. Por isso o portão está no model e não só na tela.
- **LGPD:** o ponto é dado pessoal do trabalhador. Base legal: obrigação legal (quando exigido) ou
  execução de contrato. O sistema não coleta localização nem biometria; o motivo dos ajustes (que
  pode citar atestado) é criptografado e fica fora do log; o acesso do RH ao ponto de outra pessoa é
  registrado.
- **Retenção:** o sistema nunca apaga registros. Mantenha o backup do banco pelo menos pelo prazo
  prescricional.
- **Fuso e relógio:** o servidor deve ter NTP ativo e saída UDP 123 liberada; sem isso, todas as
  marcações ficam sinalizadas.
