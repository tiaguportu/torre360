# Relatórios Configuráveis e Agendados (Onda 72)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda das telas e na seção 91 do `MANUAL_USUARIO.md`. Tela:
> `/admin/relatorios/configuraveis` (grupo **Relatórios**, **Meus Relatórios**). Permissões:
> `ViewAny`, `View`, `Create`, `Update`, `Delete` e `Executar:RelatorioSalvo` (administração, secretaria e coordenação) e
> `VerTodos:RelatorioSalvo` (administração, que vê e edita os relatórios de todos).

## Contexto

Cada módulo do sistema tem a sua listagem com filtros, mas quem acompanha a escola precisa de **recortes próprios** que
nenhuma tela traz pronta ("inadimplentes há mais de 30 dias da turma X, com o responsável", "matrículas ativas por
turma") e de recebê-los **sem lembrar de abrir a tela**: toda segunda, no começo do mês. Hoje isso vira planilha exportada
à mão.

Esta onda deixa a pessoa **montar o relatório** (dados, colunas, filtros, ordem, formato), **gerá-lo na hora** ou
**agendá-lo** para o sistema gerar sozinho e avisar quem precisa.

## O que já existia

- Telas de relatório fixas (DRE, Fluxo de Caixa, Inadimplência, Carga Letiva) e exportação de CSV/PDF em módulos
  isolados, cada um com a sua regra de acesso.
- `CsvSanitizer` contra injeção de fórmula, `dompdf` para PDF, notificações por e-mail e no sino, e o agendador
  (`routes/console.php`) com a fila rodando de minuto em minuto.

## O que foi feito

### Fontes de dados: nunca SQL livre

O relatório **não monta consulta a partir do que a pessoa escolhe**. A pessoa escolhe entre colunas e filtros que uma
**fonte** declara, e a fonte é quem consulta o banco (contrato `FonteDeRelatorio`, registrada em
`config/relatorios.php`). Colunas e filtros guardados que a fonte não declara mais são **ignorados**; opção fora da lista,
data inválida e chave desconhecida são descartadas. Seis fontes iniciais:

| Fonte | Dados | Permissão (a mesma da tela) |
|---|---|---|
| Inadimplência | Faturas em atraso com aluno, turma, responsáveis, dias de atraso, valor, já pago e saldo | `View:RelatorioInadimplencia` |
| Matrículas por turma | Matrículas com aluno, nascimento, turma, série, curso, situação e ativação | `ViewAny:Matricula` |
| Contas a pagar | Por vencimento, com fornecedor, conta do plano e centro de custo | `ViewAny:ContaPagar` |
| Movimento bancário | Entradas e saídas com banco, conta do plano e centro de custo | `View:RelatorioFluxoCaixa` |
| Faltas excessivas | Notificações ao Conselho Tutelar com a apuração e o andamento do ofício | `ViewAny:NotificacaoConselhoTutelar` |
| Documentos para assinatura | Documentos enviados, situação e **nível da assinatura** (sem os de RH) | `ViewAny:DocumentoAssinatura` |

Colunas que levam dado pessoal (nome do aluno, nascimento, responsáveis, título do documento) vêm **marcadas** no formulário.
Um módulo novo entra nos relatórios com uma classe pequena e uma linha na configuração.

### Montar e gerar

Formulário com a fonte, as colunas (na ordem em que aparecem), os filtros da fonte (período, situação, turma...), a ordem
e o formato: **planilha CSV** (até 20.000 linhas) ou **PDF** em A4 paisagem (até 1.500 linhas). Passou do limite, o arquivo
sai **cortado** e a execução registra e avisa. Cada pessoa vê **só os seus** relatórios; quem tem `VerTodos` vê, edita e
executa os de todos.

### Agendar

Todo dia, toda semana (segunda a domingo) ou todo mês (dia 1 a 28, para valer em todos os meses), numa hora marcada, para
uma ou mais pessoas. O agendador roda de 5 em 5 minutos (`relatorios:executar-agendados`): o arquivo sai até 5 minutos
depois da hora. Detalhes que importam:

- A próxima rodada é **reservada antes** de gerar, por atualização condicional: duas rodadas simultâneas não geram o mesmo
  relatório duas vezes.
- Salvar o relatório sem mexer na frequência, no dia ou na hora **não muda** a próxima execução; mudar a agenda recalcula.
- **Três falhas seguidas desligam o agendamento** e avisam o dono, em vez de errar para sempre em silêncio.
- Cada execução fica no **histórico** do relatório (quando, origem, situação, linhas, motivo do erro, quem gerou).

### Permissão em todas as pontas

A permissão da fonte é conferida:

1. ao **montar** (a lista de fontes só traz as que a pessoa pode ver);
2. a cada **execução**, para quem a pediu ou para o dono, no agendamento (perdeu o acesso, a execução vira erro e não gera
   arquivo);
3. para cada **destinatário** do agendamento (ao salvar e ao avisar): só recebe quem está ativo e pode ver os dados;
4. ao **baixar** o arquivo: dono, destinatário ou quem tem `VerTodos`, **e** a permissão da fonte. Perder o acesso aos
   dados fecha o arquivo, mesmo para o dono.

Na tela, vale o papel ativo da sessão; no agendador, que não tem sessão, vale o conjunto dos papéis da pessoa.

### Entrega e privacidade

O e-mail e o aviso no sino levam **só o nome do relatório e o link**. O arquivo (que pode ter dado pessoal) fica em área
privada (`relatorios/{id}/`) e só se baixa dentro do sistema, com login, conferindo o SHA-256 guardado (alterado: 409;
ausente: 404). O arquivo é **apagado depois de 30 dias** (`relatorios:limpar-arquivos`, `RELATORIOS_RETENCAO_DIAS`); o registro
da execução permanece. A planilha passa pelo `CsvSanitizer` nas colunas de texto, e o PDF de relatório com dado pessoal
traz o aviso de LGPD no rodapé. Gerar e baixar entram no log de atividades.

## Decisões e limites

- **Sem construtor visual de consultas, sem junções livres.** Mais flexível, mas abre a porta para ler dado que a pessoa não
  pode ver e para consulta que derruba o banco. O ganho de segurança vale a perda de flexibilidade.
- **E-mail com link, não com anexo.** O anexo sairia do controle do sistema (encaminhamento, caixa postal compartilhada).
- **Dado pessoal em relatório agendado é decisão de quem agenda.** O sistema sinaliza e restringe os destinatários, mas não
  decide a finalidade (LGPD, arts. 6º e 7º).
- **Ordenação em memória.** A fonte devolve até o limite de linhas e a ordem é aplicada depois; por isso o limite é baixo no
  PDF.
- **Sem gráficos.** É relatório tabular.

## Testes

`tests/Feature/RelatoriosConfiguraveisTest.php` (31): fontes e permissões, planilha (colunas, formato, ordem, filtros),
filtros inválidos, fórmula na planilha, PDF, limite, execução sem permissão e fonte que sumiu, download (dono, destinatário,
quem vê todos, estranho, acesso perdido, arquivo alterado ou ausente), cálculo da próxima execução (diária, semanal,
mensal, virada de ano), validação da configuração, agendador (execução, aviso só a quem pode, rodada dupla, desligamento
após três falhas), limpeza de arquivos, comandos e as telas.

## Próximos passos (apenas documentados)

- **Mais fontes:** CRM (funil e visitas), RH (férias e contratos), Biblioteca, Patrimônio, Cantina e Loja.
- **Entrega por outros canais** (WhatsApp, pela Central de Avisos da Onda 70) e **assinatura do relatório** (hash e carimbo).
- **Compartilhar** um relatório com um papel ou uma pessoa, em vez de só "ver todos".
- **Totais e agrupamento** (por turma, por mês) e **gráficos**, quando a Onda 47/painel da direção pedir.
