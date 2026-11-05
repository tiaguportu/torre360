# Cobertura de testes: levantamento das lacunas (2026-11-04)

> **Status: implementado.** Levantamento das classes de `app/` sem teste, 464 testes novos para as lacunas de maior risco, dois
> defeitos reais encontrados e corrigidos no caminho. Suíte completa: 4.248 testes, 20.330 asserções, verde (ver "Como rodar").

## O pedido

Verificar o que não tem teste implementado, implementar o teste e rodar a suíte completa.

## Como o levantamento foi feito

O ambiente **não tem driver de cobertura** (nem Xdebug nem PCOV), então não existe percentual de linhas. O levantamento é por **referência**
e foi refinado em quatro passos, porque cada um sozinho engana:

1. **Por nome da classe**: a classe aparece em algum arquivo de `tests/`? Subestima a cobertura (middleware, regra de validação e cast são
   exercitados pelo comportamento, sem o nome aparecer) e não vê comando de console nem controller.
2. **Comandos do console** por assinatura (`$signature`) e **controllers** por rota (`route:list --json` cruzado com URIs e nomes de
   rota usados nos testes). Resultado: só 1 comando e 5 rotas de 40 controllers sem referência.
3. **Policies**: separar o molde do Shield (`return $authUser->can('Metodo:Modelo');`, 1.389 métodos) dos métodos com regra própria
   (43 policies). Nenhuma das 43 era citada por teste.
4. **Algum método público da classe é chamado em teste?** Separa "citada de passagem" de "exercitada". É o critério usado para
   escolher as lacunas abaixo ("nenhum método chamado").

Base: 1.882 classes em `app/` (877 delas de recursos do Filament) e 359 arquivos de teste antes deste trabalho.

## Testes acrescentados

| Arquivo | Testes | O que garante |
|---|---|---|
| `tests/Unit/Policies/PoliticasNoMoldeDoShieldTest` | 155 | As 1.389 funções no molde do Shield consultam exatamente `Metodo:Modelo`, liberam quem tem a permissão e negam quem não tem. Os desvios deliberados (`DenunciaComite`, `MovimentacaoPatrimonio`, `deleteAny` que reaproveita `Delete`...) ficam **fixados** numa lista: trocar uma permissão sem querer faz o teste falhar nomeando a policy e o método |
| `tests/Unit/Policies/PoliticasComRegraDeEstadoTest` | 145 | Regras que dependem do estado (requisição de compra, orçamento, cenário de reajuste, fatura protegida, cancelamento de matrícula, reserva de espaço, parecer, ponto, assinatura...) e as ações negadas **mesmo a quem tem todas as permissões** (excluir ponto, denúncia, reunião...) |
| `tests/Unit/Policies/PoliticasDeTitularidadeTest` | 25 | "É do próprio usuário": autoavaliação, plano de desenvolvimento, ponto, relatório salvo, lead, encaminhamento, reunião, PEI, e a `UserPolicy` (ninguém exclui a própria conta; conta de `super_admin` só é tocada por `super_admin`) |
| `tests/Feature/Policies/EscopoDoProfessorTest` | 17 | `EscopoProfessor` ("só o que eu leciono"), `FuncionarioDoUsuario` e as policies que dependem deles (parecer, portfólio, gabarito) |
| `tests/Unit/FaturaImportParserTest` | 52 | Leitura de valor, data, desconto e status na importação de faturas; fatura paga ou parcial não entra por importação |
| `tests/Unit/FormatacaoETipoColunaTest` | 14 | CPF, CNPJ, reais e alinhamento de coluna |
| `tests/Feature/InteressadoObserverTest` | 5 | Histórico de transição do lead pelo observer, sem duplicar quando o serviço já registrou |
| `tests/Feature/ReservaEspacoConflitoServiceTest` | 13 | Conflito de sala: reserva solicitada/confirmada, faixas que só se tocam, grade horária fixa só quando a turma tem aula no dia |
| `tests/Feature/ContratosRotasEMobileTokenTest` | 20 | PDF, download e geração de assinatura do contrato (visitante, sem vínculo, professor, conta inativa, aluno do contrato) e registro de token do aplicativo (validação, conta inativa, limite de 10 por minuto) |
| `tests/Feature/ArquivoPrivadoETrackerUtmTest` | 13 | Upload privado decide o tipo pelo conteúdo (HTML como `.pdf`, SVG e script são recusados e nada é gravado); captura de UTM e ligação com a campanha ativa |
| `tests/Feature/ModelosBatemComOEsquemaTest` | 1 | Para **todos** os modelos: a tabela existe, o `$fillable` aponta para colunas reais e cada relacionamento resolve (tabela, chaves e colunas de pivô). Os problemas saem juntos numa lista |
| `tests/Feature/CronogramaAulaPeriodoLetivoTest` | 4 | Regressão dos defeitos abaixo |

Apoio: `tests/Support/UsuarioComPermissoes` é um usuário **sem banco** (responde `can()` por uma lista, informa id, papel, vínculo de
professor e pessoas). Permite testar policy como função pura, em milissegundos. O modo `comTudo()` prova que uma regra nega mesmo a quem
tem toda permissão.

Validação dos testes (não passam por acaso): quebrei de propósito uma permissão em `MatriculaPolicy`, a trava de quem pediu o ajuste de
ponto e a trava de requisição recebida; cada quebra foi apontada exatamente pelo teste certo, e as policies foram restauradas.

## Defeitos encontrados e corrigidos

1. **Cronograma de aulas: período letivo lido de uma coluna que não existe mais** (`ModelosBatemComOEsquemaTest` achou). A coluna
   `periodo_letivo_id` saiu de `cronograma_aula` em abril/2026 (o período vem da turma), mas a relação `CronogramaAula::periodoLetivo()`
   e quatro lugares ainda a usavam:
   - o **filtro "Período Letivo"** da listagem dava erro de SQL (`no such column: cronograma_aula.periodo_letivo_id`) ao ser usado;
   - o **infolist** e o campo "Período Letivo" da tela de frequência saíam sempre vazios;
   - os seletores "Período Letivo" de **Editar em Lote** e **Clonar** eram no-ops silenciosos (o valor era descartado por não estar no
     `$fillable`, e a ação dizia que deu certo);
   - o formulário de cadastro guardava um campo que não grava nada.

   Correção, no padrão que `Matricula` já usa: `periodoLetivo()` virou `HasOneThrough` pela turma; o filtro passou a consultar
   `turma.periodo_letivo_id`; o campo do formulário é só um auxílio para achar a turma (`dehydrated(false)`, preenchido pela turma); os
   dois seletores no-op saíram; a replicação de aulas segue o período da turma criada. Manual: §12.1.
2. **Seis relacionamentos mortos e quebrados**, removidos: `Disciplina::habilidades()`, `Disciplina::professor()`,
   `Habilidade::avaliacoes()`, `Habilidade::professor()`, `Serie::habilidades()` e `Turma::etapaAvaliativa()`. Todos apontavam para
   colunas que não existem; os usos com esses nomes no código pertencem a outros modelos (a suíte completa confirma que ninguém
   dependia deles).

## Lacunas que ficaram (por ordem de risco)

- **Policies que precisam de banco**: `RequisicaoCompraPolicy` (`view`, `aprovar`, `receber`), `ItemEstoquePolicy::delete`,
  `CicloAvaliacaoDocente` e `InstrumentoAvaliacaoDocente` (`delete`), `ReuniaoAlunoPolicy` e `ReuniaoEncaminhamentoPolicy` (ramos que
  consultam a reunião), `QuestionarioPolicy`, `PontoRegistroPolicy::registrar`, `AvaliacaoDocentePolicy::visivelPara`,
  `DenunciaPolicy::view`. `AnotacaoOsPolicy` usa o padrão antigo de nome do Shield e fica fora do teste genérico.
- **Serviços**: `OmrLeitor` (precisa de imagens), `CopilotoPdfService`, `AssinafyDocumentosClient` (1 de 10 métodos chamados),
  `IndicacaoCaptacaoService` (2 de 6), os indicadores do Painel (`DespesasNoOrcamento`, `MargemDasTurmas`, `CaixaProjetado30Dias`, 2 de
  9 a 10 métodos), `EducacensoInstituicaoExporter` (953 linhas).
- **Support**: `CatalogoRequerimentos`, `PendenciasMatricula` (2 de 6).
- **Telas e utilitários**: `SchoolSetupWizard`, `AcceptTerms`, `PessoaExporter` e `PessoaImporter`, os componentes de boletim em
  `Filament/Schemas`, e as pastas de recurso `Candidatos`, `DenunciaComites`, `EventosEscolares`, `Pais` e `TemplateCrachaV3S`
  (nenhuma classe delas é citada nos testes).
- **Sem lógica a testar**: `DocumentoInseridoObserver` (só esqueleto vazio) e `CheckTermsOfUse` (passa direto). `db:migrate-to-mysql`
  é um utilitário de uma vez só e fica sem teste de propósito.
- **Fora do alcance do teste de esquema**: ele confere o esquema do **SQLite** dos testes. As cinco colunas que existem só no SQLite
  (ver `docs/paridade_esquema_testes_mariadb_roadmap.md`) continuam exigindo o roteiro de fumaça em MariaDB.

## Como rodar

```
php artisan test --parallel --processes=8 --compact      # suíte completa, em segundo plano (leva dezenas de minutos)
php artisan test tests/Unit/Policies                    # só as policies (sem banco, ~1 min)
```

Armadilhas do ambiente (não são defeitos do código):

- `public/build` é ignorado pelo git. Sem ele, centenas de testes que renderizam página falham com `ViteManifestNotFoundException`. Num
  worktree novo: `npm run build` (com `node_modules` do checkout principal ligado por junção) antes da suíte.
- `ValidarDocumentoIaTest` exige `GEMINI_API_KEY` no ambiente (qualquer valor falso serve): `GEMINI_API_KEY=chave-falsa-teste`.
- Não exportar `ASSINAFY_API_KEY` falso: `CredenciaisAssinafyNaSuiteTest` falha se a suíte herdar essas chaves.
- Num worktree, o `.env` só precisa da linha `APP_KEY=`; **nunca copiar o `.env` de produção**.

## Arquivos

`tests/Support/UsuarioComPermissoes.php` · `tests/Unit/Policies/{PoliticasNoMoldeDoShieldTest,PoliticasComRegraDeEstadoTest,PoliticasDeTitularidadeTest}.php`
· `tests/Unit/{FaturaImportParserTest,FormatacaoETipoColunaTest}.php` ·
`tests/Feature/{Policies/EscopoDoProfessorTest,InteressadoObserverTest,ReservaEspacoConflitoServiceTest,ContratosRotasEMobileTokenTest,ArquivoPrivadoETrackerUtmTest,ModelosBatemComOEsquemaTest,CronogramaAulaPeriodoLetivoTest}.php`
· corrigidos: `CronogramaAula`, `CronogramaAulaForm`, `CronogramaAulasTable`, `CreateCronogramaAula`, `Disciplina`, `Habilidade`,
`Serie`, `Turma`.
